# Migration runbook — recommendation_run + idempotency indexes

How to deploy the three migrations added by the idempotency work without
duplicate-key failures or a half-applied schema.

Added by:

- `2026_09_26_150000_create_recommendation_run_table`
- `2026_09_26_150100_dedup_before_unique_indexes`
- `2026_09_26_150200_add_unique_indexes`

## Why order matters

The unique indexes can only be created once the tables hold no duplicate rows,
and the current writer still does plain `insert()`. Adding an index while the
queue is draining jobs that insert duplicates fails the DDL (or leaves the
migration half-applied). The pipeline is also a `ShouldQueue` listener with
`tries = 3`, so a job enqueued before the deploy can still run after it.

The safe sequence is: **stop writers → migrate → deploy code → resume writers.**

## Pre-flight

```powershell
# 1. Snapshot first. There is no rollback for a failed dedup DELETE.
mysqldump -h 127.0.0.1 -P 3306 -uroot -proot db_magnet > backup_pre_idempotency.sql

# 2. Preview what the dedup migration would remove (expect 0 on a healthy DB).
mysql -h 127.0.0.1 -P 3306 -uroot -proot -D db_magnet -e "
SELECT 'encoded_alternatives' t, COUNT(*) dupes FROM (
  SELECT mahasiswa_id, lowongan_magang_id FROM encoded_alternatives
  GROUP BY mahasiswa_id, lowongan_magang_id HAVING COUNT(*) > 1) x
UNION ALL SELECT 'kriteria_pekerjaan', COUNT(*) FROM (
  SELECT mahasiswa_id FROM kriteria_pekerjaan GROUP BY mahasiswa_id HAVING COUNT(*) > 1) x
UNION ALL SELECT 'kontrak_magang', COUNT(*) FROM (
  SELECT mahasiswa_id FROM kontrak_magang GROUP BY mahasiswa_id HAVING COUNT(*) > 1) x;"
```

A non-zero `kontrak_magang` count means a student has more than one contract.
The `kontrak_magang(mahasiswa_id)` unique will then fail — decide whether to keep
it before continuing.

## Deploy

```powershell
# 3. Stop the queue so no job can insert during the DDL window.
php artisan queue:restart          # workers exit after their current job
# (or stop the worker container: podman stop magnet_worker)

# 4. Apply the schema. The dedup migration commits its DELETEs before the
#    unique migration runs, so the two are ordered correctly.
php artisan migrate --force

# 5. Deploy the application code (the run-scoped writer). In the Docker stack
#    this is a new image; on a bare host, pull the branch.

# 6. Restart the queue on the new code.
php artisan queue:work --queue=default --tries=3 --timeout=120 --sleep=3
```

## Verify

```powershell
# The idempotency guarantee is present.
mysql -h 127.0.0.1 -P 3306 -uroot -proot -D db_magnet -e "
SHOW INDEX FROM recommendation_run WHERE Non_unique = 0;
SELECT COUNT(*) AS stage_tables_with_run_id FROM information_schema.columns
  WHERE table_schema='db_magnet' AND column_name='run_id';"
# expect: the (mahasiswa_id, run_key) unique, and 6.

# A re-run must not duplicate stage rows.
php artisan tinker --execute="
  \$m = App\Models\Mahasiswa::has('kriteriaPekerjaan')->first();
  App\Helpers\DecisionMaking\DataPreprocessing::dataEncoding(\$m);
  (new App\Helpers\DecisionMaking\MultiMOORA(\$m))->computeMultiMOORA();
  (new App\Helpers\DecisionMaking\MultiMOORA(\$m))->computeMultiMOORA();
  echo App\Models\VectorNormalization::where('mahasiswa_id', \$m->id)->count();"
# expect: exactly one snapshot's worth of rows (opening count), not double.
```

## Rollback

```powershell
php artisan migrate:rollback --step=3 --force
```

Reversible: the unique migration's `down()` restores a plain index for any FK
column it was backing before dropping the unique, and the run-table migration
drops its `run_id` FKs before the table. The dedup migration's `down()` is a
documented no-op — deleted duplicate rows are not restored, which is why the
pre-flight dump exists.

## Notes

- `db_magnet_test` is the test DB; the suite applies these migrations itself via
  `RefreshDatabase`. Never point the deploy above at it.
- On a fresh install there is nothing to dedup, so the whole run is a no-op
  apart from the DDL.
