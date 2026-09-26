<?php

use App\Helpers\DecisionMaking\ROC;
use App\Models\RecommendationRun;

/**
 * GATE-PARITY: proves the recommendation `run_key` is byte-stable.
 *
 * `RecommendationRun::makeKey()` is a sha256 over (mahasiswa_id, sorted encoded
 * alternatives, ksort'd weights). The criteria-collapse phase (P3) and the bobot
 * resize (P4b) must NOT change any of those inputs, or every existing run is
 * orphaned and idempotency breaks.
 *
 * CRITICAL: the pipeline feeds `makeKey()` the `bobot` values READ FROM THE DB
 * (Eloquent reads `decimal(30,15)` as the string `x.xxxxxxxxxxxxxxx` — 15
 * fractional digits, trailing zeros included), NOT a freshly-computed
 * `ROC::getWeight()` float. The fixture below uses those exact DB-cast strings,
 * so this gate reflects what the pipeline really hashes. The fixture is
 * deterministic (no DB ids) so the golden hash is stable.
 */
const PARITY_MAHASISWA_ID = 4242;

const PARITY_ALTERNATIVES = [
    ['lowongan_magang_id' => 11, 'pekerjaan' => 2, 'open_remote' => 2, 'jenis_magang' => 1, 'bidang_industri' => 2, 'lokasi_magang' => 2],
    ['lowongan_magang_id' => 22, 'pekerjaan' => 1, 'open_remote' => 2, 'jenis_magang' => 2, 'bidang_industri' => 1, 'lokasi_magang' => 2],
    ['lowongan_magang_id' => 33, 'pekerjaan' => 2, 'open_remote' => 1, 'jenis_magang' => 2, 'bidang_industri' => 2, 'lokasi_magang' => 1],
];

/** The exact DB-cast bobot strings the pipeline hashes (decimal(30,15)). */
const PARITY_WEIGHTS_DB = [
    'pekerjaan' => '0.456666666666670',
    'bidang_industri' => '0.256666666666670',
    'lokasi_magang' => '0.156666666666670',
    'jenis_magang' => '0.090000000000000',
    'open_remote' => '0.040000000000000',
];

const PARITY_GOLDEN_RUN_KEY = 'e13b9d364bcf72177d12281676447f96fbe911d06c3d48c60181324ca1f4536e';

beforeEach(function () {
    seedMasterData();
});

it('produces the frozen golden run_key for the parity fixture (DB-cast weights)', function () {
    $key = RecommendationRun::makeKey(PARITY_MAHASISWA_ID, PARITY_ALTERNATIVES, PARITY_WEIGHTS_DB);

    expect($key)->toBe(PARITY_GOLDEN_RUN_KEY);
});

it('reads a real criteria row as the exact DB-cast weight string', function () {
    $mahasiswa = mahasiswaDenganPreferensi();

    expect((string) $mahasiswa->kriteriaPekerjaan->bobot)->toBe('0.456666666666670')
        ->and((string) $mahasiswa->kriteriaBidangIndustri->bobot)->toBe('0.256666666666670')
        ->and((string) $mahasiswa->kriteriaLokasiMagang->bobot)->toBe('0.156666666666670')
        ->and((string) $mahasiswa->kriteriaJenisMagang->bobot)->toBe('0.090000000000000')
        ->and((string) $mahasiswa->kriteriaOpenRemote->bobot)->toBe('0.040000000000000');
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
