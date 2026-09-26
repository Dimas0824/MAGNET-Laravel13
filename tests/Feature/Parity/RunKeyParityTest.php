<?php

use App\Helpers\DecisionMaking\ROC;
use App\Models\RecommendationRun;

/**
 * GATE-PARITY: proves the recommendation `run_key` is byte-stable.
 *
 * `RecommendationRun::makeKey()` is a sha256 over (mahasiswa_id, sorted encoded
 * alternatives, canonicalized weights). P3 (collapse) and P4b (bobot resize) must
 * NOT orphan existing runs. P4b DOES change the physical bobot string
 * (`0.456666666666670` -> `0.457`), so makeKey canonicalizes every weight to a
 * fixed 3-decimal string (WEIGHT_SCALE) before hashing: the key is a function of
 * the weight VALUE, not its storage precision. The golden below is the canonical
 * (6,3) hash; the SAME key is produced for both the (30,15) and (6,3) DB forms.
 *
 * The pipeline feeds `makeKey()` the `bobot` values READ FROM THE DB, so the
 * fixture below uses the exact DB-cast strings (post-resize `decimal(6,3)`).
 * Because makeKey canonicalizes to 3 decimals, the (30,15) and (6,3) forms yield
 * the identical golden — proven by the precision-independence test. The
 * fixture is deterministic (no DB ids) so the golden hash is stable.
 */
const PARITY_MAHASISWA_ID = 4242;

const PARITY_ALTERNATIVES = [
    ['lowongan_magang_id' => 11, 'pekerjaan' => 2, 'open_remote' => 2, 'jenis_magang' => 1, 'bidang_industri' => 2, 'lokasi_magang' => 2],
    ['lowongan_magang_id' => 22, 'pekerjaan' => 1, 'open_remote' => 2, 'jenis_magang' => 2, 'bidang_industri' => 1, 'lokasi_magang' => 2],
    ['lowongan_magang_id' => 33, 'pekerjaan' => 2, 'open_remote' => 1, 'jenis_magang' => 2, 'bidang_industri' => 2, 'lokasi_magang' => 1],
];

/** The exact DB-cast bobot strings the pipeline hashes (decimal(6,3), post-P4b). */
const PARITY_WEIGHTS_DB = [
    'pekerjaan' => '0.457',
    'bidang_industri' => '0.257',
    'lokasi_magang' => '0.157',
    'jenis_magang' => '0.090',
    'open_remote' => '0.040',
];

const PARITY_GOLDEN_RUN_KEY = '98c876c86a6c7cfd4876e33e24d166c628b9869bbfa9ef7b49ad9a66f39685bf';

beforeEach(function () {
    seedMasterData();
});

it('produces the frozen golden run_key for the parity fixture (DB-cast weights)', function () {
    $key = RecommendationRun::makeKey(PARITY_MAHASISWA_ID, PARITY_ALTERNATIVES, PARITY_WEIGHTS_DB);

    expect($key)->toBe(PARITY_GOLDEN_RUN_KEY);
});

it('reads a real criteria row as the exact DB-cast weight string', function () {
    $mahasiswa = mahasiswaDenganPreferensi();

    expect((string) $mahasiswa->kriteriaPekerjaan->bobot)->toBe('0.457')
        ->and((string) $mahasiswa->kriteriaBidangIndustri->bobot)->toBe('0.257')
        ->and((string) $mahasiswa->kriteriaLokasiMagang->bobot)->toBe('0.157')
        ->and((string) $mahasiswa->kriteriaJenisMagang->bobot)->toBe('0.090')
        ->and((string) $mahasiswa->kriteriaOpenRemote->bobot)->toBe('0.040');
});

it('is precision-independent: (30,15) and (6,3) bobot strings hash identically', function () {
    $legacy = [
        'pekerjaan' => '0.456666666666670',
        'bidang_industri' => '0.256666666666670',
        'lokasi_magang' => '0.156666666666670',
        'jenis_magang' => '0.090000000000000',
        'open_remote' => '0.040000000000000',
    ];

    $resized = RecommendationRun::makeKey(PARITY_MAHASISWA_ID, PARITY_ALTERNATIVES, PARITY_WEIGHTS_DB);
    $legacyKey = RecommendationRun::makeKey(PARITY_MAHASISWA_ID, PARITY_ALTERNATIVES, $legacy);

    expect($legacyKey)->toBe($resized)
        ->and($legacyKey)->toBe(PARITY_GOLDEN_RUN_KEY);
});

it('keeps ROC::getWeight stable against the config total_criteria', function () {
    $total = config('recommendation-system.roc.total_criteria');

    expect($total)->toBe(5)
        ->and((string) ROC::getWeight(1, $total))->toBe('0.45666666666667')
        ->and((string) ROC::getWeight(2, $total))->toBe('0.25666666666667')
        ->and((string) ROC::getWeight(3, $total))->toBe('0.15666666666667')
        ->and((string) ROC::getWeight(4, $total))->toBe('0.09')
        ->and((string) ROC::getWeight(5, $total))->toBe('0.04');
});
