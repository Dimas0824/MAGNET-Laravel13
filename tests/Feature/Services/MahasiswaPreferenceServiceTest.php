<?php

use App\Events\MahasiswaPreferenceUpdated;
use App\Helpers\DecisionMaking\ROC;
use App\Models\Mahasiswa;
use App\Models\MahasiswaKriteria;
use App\Models\Pekerjaan;
use App\Services\MahasiswaPreferenceService;
use Illuminate\Support\Facades\Event;

/**
 * W0-T05b: MahasiswaPreferenceService extracts the criteria preference-writing
 * logic shared by the profile page and the preference wizard. These tests pin
 * the current behavior EXACTLY (two recent bugfixes depend on it):
 *  - firstOrNew + forceFill + save (NOT Builder update) so the value_enum remap runs
 *  - rank/bobot preserved when a row already exists, defaulted (ROC) when new
 *  - exactly ONE MahasiswaPreferenceUpdated event per save
 */
beforeEach(function () {
    seedMasterData();
});

it('writes all five criteria and emits exactly one MahasiswaPreferenceUpdated event', function () {
    Event::fake();

    $mahasiswa = Mahasiswa::factory()->create();

    (new MahasiswaPreferenceService)->savePreferences($mahasiswa, [
        'pekerjaan' => 'Software Engineer',
        'bidang_industri' => 'Teknologi',
        'lokasi_magang' => 'Area Malang Raya',
        'jenis_magang' => 'berbayar',
        'open_remote' => 'ya',
    ]);

    expect(MahasiswaKriteria::where('mahasiswa_id', $mahasiswa->id)->count())->toBe(5);

    Event::assertDispatchedTimes(MahasiswaPreferenceUpdated::class, 1);
});

it('updates an existing row preserving its rank and bobot', function () {
    $mahasiswa = mahasiswaDenganPreferensi();

    $before = MahasiswaKriteria::where('mahasiswa_id', $mahasiswa->id)
        ->where('criteria_key', MahasiswaKriteria::KEY_PEKERJAAN)
        ->first();

    (new MahasiswaPreferenceService)->savePreferences($mahasiswa, [
        'pekerjaan' => 'Data Engineer',
        'bidang_industri' => 'Teknologi',
        'lokasi_magang' => 'Area Malang Raya',
        'jenis_magang' => 'berbayar',
        'open_remote' => 'ya',
    ]);

    $after = MahasiswaKriteria::where('mahasiswa_id', $mahasiswa->id)
        ->where('criteria_key', MahasiswaKriteria::KEY_PEKERJAAN)
        ->first();

    expect($after->pekerjaan_id)->toBe(Pekerjaan::where('nama', 'Data Engineer')->value('id'))
        ->and($after->rank)->toBe($before->rank)
        ->and((string) $after->bobot)->toBe((string) $before->bobot);
});

it('creates rows with default rank/bobot when the student has none', function () {
    $mahasiswa = Mahasiswa::factory()->create();

    (new MahasiswaPreferenceService)->savePreferences($mahasiswa, [
        'pekerjaan' => 'Software Engineer',
        'bidang_industri' => 'Teknologi',
        'lokasi_magang' => 'Area Malang Raya',
        'jenis_magang' => 'berbayar',
        'open_remote' => 'ya',
    ]);

    $rows = MahasiswaKriteria::where('mahasiswa_id', $mahasiswa->id)
        ->get()
        ->keyBy('criteria_key');

    $total = config('recommendation-system.roc.total_criteria');

    $expectedRank = [
        MahasiswaKriteria::KEY_PEKERJAAN => 1,
        MahasiswaKriteria::KEY_BIDANG_INDUSTRI => 2,
        MahasiswaKriteria::KEY_LOKASI_MAGANG => 3,
        MahasiswaKriteria::KEY_JENIS_MAGANG => 4,
        MahasiswaKriteria::KEY_OPEN_REMOTE => 5,
    ];

    foreach ($expectedRank as $key => $rank) {
        expect($rows[$key]->rank)->toBe($rank)
            ->and((string) $rows[$key]->bobot)->toBe(number_format(ROC::getWeight($rank, $total), 3, '.', ''));
    }
});

it('maps jenis_magang/open_remote into value_enum', function () {
    $mahasiswa = Mahasiswa::factory()->create();

    (new MahasiswaPreferenceService)->savePreferences($mahasiswa, [
        'pekerjaan' => 'Software Engineer',
        'bidang_industri' => 'Teknologi',
        'lokasi_magang' => 'Area Malang Raya',
        'jenis_magang' => 'tidak berbayar',
        'open_remote' => 'tidak',
    ]);

    $jenis = MahasiswaKriteria::where('mahasiswa_id', $mahasiswa->id)
        ->where('criteria_key', MahasiswaKriteria::KEY_JENIS_MAGANG)
        ->first();
    $remote = MahasiswaKriteria::where('mahasiswa_id', $mahasiswa->id)
        ->where('criteria_key', MahasiswaKriteria::KEY_OPEN_REMOTE)
        ->first();

    expect($jenis->value_enum)->toBe('tidak berbayar')
        ->and($remote->value_enum)->toBe('tidak');
});

it('throws a RuntimeException when a referenced master row is missing', function () {
    $mahasiswa = Mahasiswa::factory()->create();

    expect(fn () => (new MahasiswaPreferenceService)->savePreferences($mahasiswa, [
        'pekerjaan' => 'Tidak Ada Pekerjaan Ini',
        'bidang_industri' => 'Teknologi',
        'lokasi_magang' => 'Area Malang Raya',
        'jenis_magang' => 'berbayar',
        'open_remote' => 'ya',
    ]))->toThrow(\RuntimeException::class, 'Pekerjaan tidak ditemukan');
});

it('saves a ranking by ordered keys and emits exactly one event', function () {
    Event::fake();

    $mahasiswa = mahasiswaDenganPreferensi();

    (new MahasiswaPreferenceService)->saveRanking($mahasiswa, [
        MahasiswaKriteria::KEY_LOKASI_MAGANG,
        MahasiswaKriteria::KEY_PEKERJAAN,
        MahasiswaKriteria::KEY_BIDANG_INDUSTRI,
        MahasiswaKriteria::KEY_JENIS_MAGANG,
        MahasiswaKriteria::KEY_OPEN_REMOTE,
    ]);

    $total = config('recommendation-system.roc.total_criteria');

    $lokasi = MahasiswaKriteria::where('mahasiswa_id', $mahasiswa->id)
        ->where('criteria_key', MahasiswaKriteria::KEY_LOKASI_MAGANG)->first();
    $pekerjaan = MahasiswaKriteria::where('mahasiswa_id', $mahasiswa->id)
        ->where('criteria_key', MahasiswaKriteria::KEY_PEKERJAAN)->first();

    expect($lokasi->rank)->toBe(1)
        ->and((string) $lokasi->bobot)->toBe(number_format(ROC::getWeight(1, $total), 3, '.', ''))
        ->and($pekerjaan->rank)->toBe(2)
        ->and((string) $pekerjaan->bobot)->toBe(number_format(ROC::getWeight(2, $total), 3, '.', ''));

    Event::assertDispatchedTimes(MahasiswaPreferenceUpdated::class, 1);
});
