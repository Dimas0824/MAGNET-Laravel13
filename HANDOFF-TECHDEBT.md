# MAGNET — Tech Debt Removal — HANDOFF (resume tomorrow)

**Date paused:** (sesi berjalan)
**Repo:** D:\MiniProject\Magnet\DockerFrankenPHP\MAGNET-Laravel13
**Branch:** `upgrade/laravel-13` — HEAD `db5a937` — **pushed to origin** (safe)
**Remote:** https://github.com/Dimas0824/MAGNET-Laravel13
**PR:** #1

---

## HOW TO RUN (copy-paste)

Tests (MySQL db_magnet_test; xdebug loaded via the in-repo scan dir so artisan test works too):
```powershell
$env:PHP_INI_SCAN_DIR="D:\MiniProject\Magnet\DockerFrankenPHP\MAGNET-Laravel13\scripts\testing\phpini"; php artisan test
```

Coverage (statement %, from repo root):
```powershell
powershell -File scripts\testing\coverage.ps1
```

Format touched files only (NOT whole repo — repo-wide Pint would touch 100+ files):
```powershell
vendor\bin\pint <changed files>
```

MySQL access (FlyEnv): host 127.0.0.1:3306, user root, password `root`.
Test DB: `db_magnet_test` (extra a/b/c created but UNUSED — per-agent parallelism was abandoned).

---

## GOAL (still active)
Setelah coverage 80% (DONE, 82.1%) -> baca skill laravel-specialist & skill Laravel lain -> buat ULW plan -> hapus tech debt. Minimal scope: "security bugs + config" style; deferred: private-disk PII, mass-assignment, full multi-driver SQL, KriteriaLokasiMagang rename.

## OPEN DECISIONS ALREADY MADE
- D1 relations: FULL camelCase rename (DONE for LowonganMagang.lokasiMagang, Perusahaan.lowonganMagang)
- D2 queue: IMPLEMENT ASYNC — chosen design = SINGLE queued orchestrator `RunRecommendationPipeline` (ShouldQueue) that calls dataEncoding THEN computeMultiMOORA in order. (NOT YET DONE — Wave 4)
- D3/D4/D5: defer private disk; DO fix roc.total_criteria config + phpunit schema (schema DONE)
- Mass assignment: DEFER

---

## PROGRESS: WAVES

### DONE + committed + pushed
- **Wave 0** (commit f7eafd3): Pekerjaan typo, unused imports, event broadcast cruft, TemplateController allow-list, config multimoora removed, .env.example mailgun/postmark, phpunit schema 12.0, ModelNamingTest, RecommendationConfigTest
- **Wave 0.5** (f7eafd3): relation camelCase rename + all call sites
- **Wave 1** (8275419): DataPreprocessing upsert-by-id + lokasi fallback (+ updated DataPreprocessingTest)
- **W2-1** (bc6e050): mahasiswa/dashboard — parameterized subquery + eager-load (N+1 26-><=20), dropped nama
- **W2-2** (e44848e): riwayat/index — deleted dead $riwayat, portable SQL, fixed FATAL "redeclare getTimePeriodStyle"
- **W2-3/4** (5d4596b): detail-lowongan — declared lowonganId, fixed broken route detail-perusahaan->profil-perusahaan
- **W2-5/6/H2** (82e8777): profil-perusahaan — declared ids, real stats (was hardcoded 20/3.2), removed getter DB write
- **W2-9 + W2-L** (35273fe): hasil-pencarian orderBy whitelist; logout route role-middleware
- **W2-10/11** (216753e): log-magang search route, log-mahasiswa dropped nama
- **W2-12/13/14** (db5a937): detail-rekomendasi preferensi_open_remote + O(n^2) index

Tests at last full run: 88 passing (before W2 added more). Individual W2 tests all green.

### REMAINING (tomorrow)

**Wave 2 leftovers:**
- **W2-7/8/K** — resources/views/pages/dosen/dashboard.blade.php:
  - N+1: 2x ->exists() per mahasiswa inside ->map (lines ~43-75) -> use withCount aggregates
  - dropped nama: `$kontrak->lowonganMagang->nama` (~line 70) -> pekerjaan name
  - pagination: get()+PHP slice+LengthAwarePaginator (~78-87) -> ->paginate()
  - Test via GET /dashboard while actingAsDosen (NO dosen.dashboard route; shared `dashboard`)
- **W2-15/16/19** — app/Http/Controllers/PengajuanMagangController.php:
  - generateFileName (lines ~35-41) no uniqueness -> append Str::uuid()/uniqid()
  - debug leak (lines ~202-203) returns $e->getFile().':'.$e->getLine() to user -> log, return generic
  - masukan-magang.blade.php:30 unvalidated query('id') -> (int) cast + guard
- **W2-J** — resources/views/pages/mahasiswa/saran-dari-dosen.blade.php:63,88-95:
  - get()+PHP paginate -> ->paginate($perPage); repeated count() scans

**Wave 3:**
- **W3-1** — app/Helpers/DecisionMaking/MultiMOORA.php:89,467,474,481: LowonganMagang::count() called 4x -> cache once in constructor
- **W4-2** — resources/views/pages/mahasiswa/persiapan-preferensi.blade.php:68,75,82,89,96: hardcoded `5` -> config('recommendation-system.roc.total_criteria')

**Wave 4 (async queue — the big one):**
- Create app/Listeners/RunRecommendationPipeline.php implementing ShouldQueue:
  handle(MahasiswaPreferenceUpdated $e): dataEncoding($e->mahasiswa) THEN (new MultiMOORA($e->mahasiswa))->computeMultiMOORA()
  use InteractsWithQueue, Queueable, SerializesModels
- Wire in EventServiceProvider: MahasiswaPreferenceUpdated => [RunRecommendationPipeline::class]
- Remove/retire RunEncodingData + RunMultiMOORA from the mapping (keep classes for BC or delete)
- RunDataCategorization also -> implements ShouldQueue
- Tests: Queue::fake + event() assertPushed(RunRecommendationPipeline); with QUEUE_CONNECTION=sync existing pipeline tests still green; assert order (encoded before FinalRank)
- NOTE: phpunit.xml QUEUE_CONNECTION=sync so queued listeners run inline in tests

**FINAL GATE:**
```powershell
$env:PHP_INI_SCAN_DIR="D:\MiniProject\Magnet\DockerFrankenPHP\MAGNET-Laravel13\scripts\testing\phpini"; php artisan test   # all green
powershell -File scripts\testing\coverage.ps1                                                                                # statement coverage >= 80%
vendor\bin\pint <changed files>                                                                                              # PSR-12 on touched files
# smoke greps:
#  rg "mahasiswa\.search|preferensi_open_remote|detail-perusahaan', \['id'" resources/views
#  rg "ROW_NUMBER|LPAD\(|HOUR\(|MINUTE\(" resources/views/pages
```

---

## PENDING VERIFICATION (owed)
- **W2-12 RED proof was NOT captured**: a temporary revert of the preferensi_open_remote line was aborted before the failing output printed, then reverted back to the fix. The test `it('shows the open-remote preference value ...')` uses `assertSee('<td class="px-6 py-3">Ya', false)` which is precise, but no recorded RED run. TOMORROW: temporarily restore `{{ $mahasiswa->preferensi_open_remote ? 'Ya' : 'Tidak' }}`, run the test, capture RED, restore fix. (Low priority — fix is correct.)

## IMPORTANT GOTCHAS
- ReferencePoint model `$fillable` is MISSING `max_score` (required column). Tests insert via DB::table. Consider adding max_score to $fillable as a small debt fix.
- ReferencePointFactory / other factories: static caching already removed earlier (test phase).
- `Tests\Feature` uses RefreshDatabase (migrate:fresh each test) on shared db_magnet_test -> NEVER run tests in parallel across agents (DB contention). Do it sequentially.
- Pint whole-repo = 100+ files churn; only run Pint on files you touched.
- `sitemap.xml` is tracked in repo; CommandsTest preserves/restores it.

## NOTEPADS / ARTIFACTS (all in-repo now)
- Full tech-debt inventory + plan: `docs/techdebt-notepad.md`
- Coverage script: `scripts/testing/coverage.ps1`
- xdebug coverage ini (template): `scripts/testing/xdebug-coverage.ini`
- This handoff: `HANDOFF-TECHDEBT.md` (repo root)

## SESSION CONTINUITY
Plan agent session (full context): ses_f3847bc0dffeDuw2ySv0roqkN1

## STATUS: ALL WAVES COMPLETE — 09/22/2026 22:15:26
Merged all parallel-agent branches into upgrade/laravel-13.

FINAL RESULTS:
- Full suite: 126 passed (363 assertions), 0 failed
- Coverage: 86.8% (593/683 statements) — target >=80% MET
- Pint: clean on all changed files
- Smoke greps: no stale route/attr refs; no MySQL-only SQL in pages
- Laravel 13.32.0, 78 routes, app boots

Waves done (this session's merge commits 7cfeecd..3fa5706):
- W3-1: MultiMOORA lowongan count once (lazy accessor, white-box safe)
- W4-2: persiapan-preferensi reads roc.total_criteria from config
- W4: RunRecommendationPipeline queued orchestrator + RunDataCategorization ShouldQueue
      + bootstrap/app.php ->withEvents(discover:false)  [IMPORTANT: framework event
        auto-discovery was registering retired listeners]
- W2-15/16/19: pengajuan unique filenames + no debug leak + masukan id cast
- W2-J: saran-dari-dosen query-level pagination

Worktrees wt-a/b/c removed; helper branches wt/* deleted.

REMAINING (optional / deferred tech debt, not in scope of the 80% gate):
- WP private-disk PII (deferred by decision)
- mass-assignment  hardening (deferred)
- repo-wide Pint (100+ untouched files)
- ReferencePoint:: missing max_score
- KriteriaLokasiMagang::lokasi_magang() snake_case (different model; not renamed)
