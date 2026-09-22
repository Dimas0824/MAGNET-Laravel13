# Ultrawork Notepad — Remove MAGNET tech debt (Laravel 13)

Started: 2026-09-22T13:02:16.8922480+07:00
Branch: upgrade/laravel-13
Repo: D:\MiniProject\Magnet\DockerFrankenPHP\MAGNET-Laravel13
Baseline: 79 tests / 259 assertions green, 82.1% coverage

## Goal (binding)
Read laravel-specialist + laravel-security + laravel-tdd skills, produce a ULW plan,
then remove technical debt with TDD (RED->GREEN), keeping coverage >= 80%.

## Skills chosen
- laravel-specialist: Eloquent/queue/Livewire/testing patterns
- laravel-security: auth/CSRF/upload/mass-assignment review
- laravel-tdd: RED->GREEN discipline, Pest
- php-security-syntax: upload + raw SQL audit
- verification-before-completion + git-master: gates + atomic commits

## Scenarios (the contract) — DECIDED AFTER inventory
(to be filled)

## Now
Awaiting explore agent tech-debt inventory (bg_c8f074d2)

## Todo (remaining, ordered)
1. Collect tech-debt inventory (explore)
2. Spawn plan agent -> ULW plan with waves + per-task skills
3. Execute waves (TDD per fix)
4. Verify coverage >= 80% + full suite green
5. Push + update PR

## Findings (confirmed so far)
- EventServiceProvider registers RunEncodingData + RunMultiMOORA (non-queued) — no dead listeners, but non-queued pipeline (slow sync requests)
- detail-rekomendasi.blade.php:374 references preferensi_open_remote (attribute does not exist)
- riwayat-rekomendasi/index.blade.php uses MySQL-only SQL (ROW_NUMBER, HOUR, LPAD, CONCAT, DATE) at lines 28-95
- routes/web.php had require_once (already fixed in test phase)

## Learnings
- Tests run against MySQL db_magnet_test (phpunit.xml), xdebug via PHP_INI_SCAN_DIR temp dir
- Coverage script: scripts/testing/coverage.ps1

## CONFIRMED FINDINGS (from own investigation)
[BUG-1] sedang-magang.blade.php:197 uses route('mahasiswa.search') -> UNDEFINED.
        Correct name: mahasiswa.hasil-pencarian. RouteNotFoundException on click.
[BUG-2] detail-rekomendasi.blade.php:374 uses preferensi_open_remote attribute that does not exist.
[BUG-3] riwayat-rekomendasi/index.blade.php:28-95 uses MySQL-only SQL
        (ROW_NUMBER() OVER, HOUR(), MINUTE(), DATE(), LPAD, CONCAT) -> not portable.
[DEBT-1] EventServiceProvider registers RunEncodingData + RunMultiMOORA (NOT queued)
         -> recommendation pipeline runs synchronously in the request lifecycle.
         RunDataCategorization imports ShouldQueue but does NOT implement it (dead import).
[DEBT-2] DataPreprocessing::dataCategorization appends to JSON with no dedup -> unbounded growth.
[SAFE]   TemplateController::previewFile is safe (prefixes templates.pdf. + View::exists check).
         dosen.komunikasi route IS defined (false positive in my check).

## FULL INVENTORY (explore agent bg_c8f074d2 — session ses_f384bb03effecLHqi3IHUR5Lni)
Branch verified: upgrade/laravel-13, HEAD 43aa921.

### A. Non-portable MySQL SQL (HIGH)
- riwayat-rekomendasi/index.blade.php:26-95 (DATE/TIME/HOUR/MINUTE/CONCAT/LPAD/ROW_NUMBER OVER + double-quoted literals)
- mahasiswa/dashboard.blade.php:14 (DB::raw interpolated subquery)
- dosen/dashboard.blade.php:109 (whereRaw correlated)

### B. Broken refs (HIGH)
- detail-rekomendasi.blade.php:374 preferensi_open_remote (attr missing)
- detail-lowongan-magang.blade.php:406 route('mahasiswa.detail-perusahaan') UNDEFINED
- detail-lowongan-magang.blade.php:29,35,53,72 ->lowonganId NEVER declared
- profil-perusahaan.blade.php:25,52 ->perusahaanId/->lowonganId NEVER declared
- profil-perusahaan.blade.php:50 with(['lokasiMagang']) WRONG (should be lokasi_magang)
- sedang-magang.blade.php:197 route('mahasiswa.search') UNDEFINED
- dashboard.blade.php:45, dosen/dashboard:70, log-mahasiswa:51 reference dropped lowongan_magang.nama
- riwayat-rekomendasi/index.blade.php:37-45 dead  computed (uses rn_minute)

### C. DataPreprocessing append-only (HIGH)
- DataPreprocessing.php:35-38 append no dedupe
- DataPreprocessing.php:33 undefined index if lokasi missing
- DataPreprocessingTest.php:33-38 ASSERTS the bug -> must update

### D. Dead ShouldQueue (MED)
- RunDataCategorization.php:7-8, RunEncodingData.php:7-8, RunMultiMOORA.php:7-8 (imports not implemented)
- RunMultiMOORA.php:25 runs sync in request
- PengajuanMagangController.php:6 (Magang), :10 (Session) unused imports
- TemplateController.php:6 (Request) unused
- Events (both): Channel/PresenceChannel/ShouldBroadcast unused + broadcastOn dead

### E. N+1 / query (HIGH/MED)
- mahasiswa/dashboard.blade.php:24-49 (4 queries per item in map)
- dosen/dashboard.blade.php:43-75 (2 exists per mahasiswa in map)
- detail-rekomendasi.blade.php:86-247 get()+PHP dedup; :797,800,803 collection scan in loop
- MultiMOORA.php:89,467,474,481 LowonganMagang::count() x4

### F. Naming (MED)
- Pekerjaan.php:18 kriteriPekerjaan() TYPO
- LowonganMagang.php:42 lokasi_magang() snake vs camel inconsistency
- EncodedAlternatives.php:13 leftover dev comment

### G. Security (HIGH/MED)
- dashboard.blade.php:14 DB::raw interpolated
- hasil-pencarian.blade.php:55 orderBy(->sortBy,->sortDirection) not whitelisted
- PengajuanMagangController.php:153-159 public disk PII; :35-41 filename no uniqueness; :202-203 debug leak
- masukan-magang.blade.php:30 unvalidated query id

### H. Config/test (HIGH/MED)
- phpunit.xml:18 mysql (already set by us), :15 hardcoded key, :3 schema 11.5 vs pest4/phpunit12
- config/recommendation-system.php:37-38 dead multimoora block; :29-31 roc.total_criteria unused (hardcoded 5 in persiapan-preferensi:68-96)
- config/services.php mailgun/postmark keys absent from .env.example
- composer.json:71 audit.block-insecure=false

### SAFE (no action)
- TemplateController::previewFile (View::exists guard)
- All models have , no =[]
- dosen.komunikasi route IS defined

## RECONCILED (2nd explore bg_68b1e18c + plan ses_f3847bc0dffeDuw2ySv0roqkN1)
Extra findings beyond first inventory:
- TemplateController:11-25 possible view-name traversal (NEEDS EMPIRICAL VERIFY - View::exists may block)
- routes/auth.php:34 POST logout has NO auth/role middleware (CSRF still applies via web group)
- Masukan-magang:30 unvalidated query id -> Mahasiswa::find (IDOR-ish, mitigated by dosen_id check)
- profil-perusahaan.blade.php:32 DB WRITE inside computed getter (update rating) - side effect in getter
- saran-dari-dosen.blade.php:63,88-95 get()+PHP paginate; :150,196 repeated count()
- dosen/dashboard.blade.php:78-87 get()+PHP slice+LengthAwarePaginator
- Mass assignment: status_magang, FormPengajuanMagang.status, LowonganMagang.status, FinalRankRecommendation.avg_rank/rank, RatioSystem/ReferencePoint/FMF.rank/score, Kriteria*.rank/bobot, Perusahaan.rating, password fields
- MakeRepository stub generates create(\)/update(\) with no fillable guard
- phpunit.xml:27 TELESCOPE_ENABLED (telescope not installed)
- config/services.php mailgun/postmark + many env keys absent from .env.example
- riwayat-rekomendasi:  vs  - ONE is dead (plan says  lines 14-48 is dead; template uses  line 222) - VERIFY WHICH

## PLAN (plan agent) - WAVES
W0 (parallel, foundation): W0-1 kriteriPekerjaan typo fix; W0-2/3 camelCase relation aliases (keep snake); W0-4 stale comment; W0-5/6 unused imports; W0-7 event broadcast cruft; W0-8 dead multimoora config; W0-9 env docs; W0-10 phpunit schema
W1 (sequential, DataPreprocessing.php): W1-1 upsert-by-id (update DataPreprocessingTest); W1-2 lokasi fallback
W2 (parallel page clusters): 2A dashboard; 2B riwayat index; 2C detail-lowongan; 2D profil-perusahaan; 2E dosen dashboard; 2F hasil-pencarian; 2G log-magang; 2H detail-rekomendasi; 2I pengajuan+masukan
W3 (parallel-safe): W3-1 MultiMOORA count once
W4 (optional): W4-1 ShouldQueue decision; W4-2 roc.total_criteria hardcoded 5

## OPEN DECISIONS (need user)
D1. Relations: add camelCase alias + keep snake (recommended) vs full rename
D2. ShouldQueue: defer (recommended) vs implement async pipeline
D3. roc.total_criteria hardcoded 5 -> fix now (W4-2) vs defer
D4. Private-disk PII: defer (recommended) vs move now
D5. phpunit APP_KEY: schema bump only vs externalize key

## SESSION PAUSED (user leaving) — 09/22/2026 15:41:11
Handoff doc: HANDOFF-TECHDEBT.md
HEAD: db5a937 (pushed)
DONE: Wave 0, 0.5, 1, W2-1,2,3/4,5/6/H2,9,L,10/11,12/13/14
REMAINING: W2-7/8/K (dosen dashboard), W2-15/16/19 (pengajuan+masukan), W2-J (saran-dari-dosen), W3-1 (MultiMOORA count), W4-2 (roc config), W4 (async queue orchestrator)
OWED: W2-12 RED proof (temporary revert was aborted before output).

## PARALLEL EXECUTION (worktrees + per-DB) â€” started
- wt-a (db_magnet_test_a): branch wt/w2-15-pengajuan -> W2-15/16/19 pengajuan security
- wt-b (db_magnet_test_b): branch wt/w2-j-saran     -> W2-J saran-dari-dosen pagination
- wt-c (db_magnet_test_c): branch wt/w3-w4          -> W3-1 + W4-2 + W4 queue orchestrator
Merge plan when all GREEN: cherry-pick/merge each branch into upgrade/laravel-13 sequentially, re-run full suite, then remove worktrees.
Setup: vendor/node_modules junction-linked from main; .env copied; phpunit.xml DB per worktree.

## OWED RESOLVED
- W2-12 RED proof CAPTURED: temporarily reverted line 388 to preferensi_open_remote; test failed with
  "To contain: <td class=\"px-6 py-3\">Ya" -> restored fix -> GREEN. Proof complete.

## MERGE NOTES (when agents finish)
- Each wt has M phpunit.xml (DB override) â€” EXCLUDE on merge (keep main's db_magnet_test).
  Use: git cherry-pick <sha> --no-commit then git checkout HEAD -- phpunit.xml, or merge then revert phpunit.xml.
- wt-a may leave probe files (bootcheck.php, tests/bootstrap-worktree.php, ZzBootProbeTest.php) â€” DELETE before merge.
- Agents commit per sub-task; cherry-pick commits in order onto upgrade/laravel-13, then run full suite.

## SESSION: 'kerjakan semua B' â€” remaining tech debt
DONE this session:
- Item4 rename KriteriaLokasiMagang::lokasi_magang -> lokasiMagang (+4 call sites) [commit 98da573]
- Item3 ReferencePoint  += max_score [commit 98da573]
- Env repairs: vendor re-installed (composer install), npm install + build (public/build)

IN PROGRESS:
- Item2 mass-assignment hardening (ALL sensitive fields out of \ + refactor ~40 sites)
  -> delegated to subagent bg_f66976f7 (main repo)
- Item1 private-disk PII (WRITTEN, not yet verified):
  config/filesystems.php: new 'private' disk (storage/app/private)
  PengajuanMagangController: DISK const = 'private'; store/delete on private;
    new downloadBerkas(,) + authorizeBerkasAccess (owner/admin/supervising dosen)
  routes/web.php: GET berkas-pengajuan/{berkas}/{type} -> berkas.download (role:admin,mahasiswa,dosen)
  admin magang/pengajuan-izin-magang/detail.blade.php: download links instead of empty inputs
  tests/Feature/BerkasPengajuanStorageTest.php (6 tests)

PENDING after both items merged: Item5 repo-wide Pint; final suite+coverage+push.

NOTE: DB contention â€” must NOT run tests while bg_f66976f7 runs.

## IDE / NEXT (tomorrow)
### IDE-REVERB: ganti polling chat -> Laravel Reverb (websocket)
Alasan: app jalan di FrankenPHP + Docker/Linux (long-running), jadi cocok websocket.
Rencana:
- composer require laravel/reverb; php artisan reverb:install
- Jalankan container 'reverb' (php artisan reverb:start --host=0.0.0.0 --port=8080)
- Konfigurasi broadcasting: BROADCAST_CONNECTION=reverb, REVERB_APP_ID/KEY/SECRET/HOST/PORT
- Buat event ChatMessageSent implements ShouldBroadcast (channel private kontrak.{id})
- Ganti polling di resources/views/pages/**/konsul-dospem.blade.php + dosen/komunikasi-mahasiswa + masukan-magang
  dgn Echo/Reverb listener (wire:model live -> event)
- Reverse proxy / Caddy (FrankenPHP) route /app & /apps ke reverb di compose
- Test: Event::fake / Broadcast::fake; pastikan suite tetap hijau
Files terkait hari ini: Chat model, kontrak_magang, chat table.

### STATUS (this session, pushed @ a8acedf)
- Item4 KriteriaLokasiMagang rename DONE
- Item3 ReferencePoint max_score DONE
- Item1 private-disk PII + signed/authorized download DONE (134 tests, 86.4% cov)
- Item2 mass-assignment hardening: NOT DONE (agent cancelled before writing) â€” TODO
- Item5 repo-wide Pint: NOT DONE â€” TODO
- Full verification: unit 15 pass, feature 119 pass, TOTAL 134 pass, 0 fail.

### FRANKENPHP GOAL (active)
- Verify docker-compose FrankerPHP stack builds & runs without error
- Optimize the Docker image
