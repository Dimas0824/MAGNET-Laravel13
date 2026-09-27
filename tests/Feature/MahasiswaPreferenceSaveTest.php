<?php

use App\Models\Mahasiswa;
use App\Models\MahasiswaKriteria;
use Livewire\Volt\Volt;

/**
 * Regression: saving preferences from the profile page ("Simpan Preferensi")
 * must persist jenis_magang / open_remote into the collapsed
 * `mahasiswa_kriteria.value_enum` column.
 *
 * Bug (found in live Docker): the save used
 *   $mahasiswa->kriteriaJenisMagang()->update(['jenis_magang' => ...])
 * — a Builder update that BYPASSES BaseKriteriaModel::setAttribute()'s
 * legacy->value_enum remap, emitting `set jenis_magang` and failing with
 * SQLSTATE[42S22] Unknown column 'jenis_magang'.
 */
beforeEach(function () {
    seedMasterData();
});

it('saves jenis_magang and open_remote preferences from the profile page', function () {
    $mahasiswa = mahasiswaDenganPreferensi();
    actingAsMahasiswa($mahasiswa);

    Volt::test('pages.mahasiswa.profile')
        ->set('pekerjaan', 'Software Engineer')
        ->set('bidang_industri', 'Teknologi')
        ->set('lokasi_magang', 'Area Malang Raya')
        ->set('jenis_magang', 'tidak berbayar')
        ->set('open_remote', 'tidak')
        ->call('saveNewPreference')
        ->assertHasNoErrors();

    $jenis = MahasiswaKriteria::where('mahasiswa_id', $mahasiswa->id)
        ->where('criteria_key', MahasiswaKriteria::KEY_JENIS_MAGANG)
        ->first();
    $remote = MahasiswaKriteria::where('mahasiswa_id', $mahasiswa->id)
        ->where('criteria_key', MahasiswaKriteria::KEY_OPEN_REMOTE)
        ->first();

    expect($jenis)->not->toBeNull()
        ->and($jenis->value_enum)->toBe('tidak berbayar')
        ->and($remote)->not->toBeNull()
        ->and($remote->value_enum)->toBe('tidak');
});

it('creates the criteria rows when the student had none yet', function () {
    // A student with NO criteria rows (e.g. never completed the wizard).
    $mahasiswa = Mahasiswa::factory()->create();
    actingAsMahasiswa($mahasiswa);

    Volt::test('pages.mahasiswa.profile')
        ->set('pekerjaan', 'Software Engineer')
        ->set('bidang_industri', 'Teknologi')
        ->set('lokasi_magang', 'Area Malang Raya')
        ->set('jenis_magang', 'berbayar')
        ->set('open_remote', 'ya')
        ->call('saveNewPreference')
        ->assertHasNoErrors();

    expect(MahasiswaKriteria::where('mahasiswa_id', $mahasiswa->id)->count())->toBe(5);
});

it('saves the criteria ranking priorities from the profile page', function () {
    $mahasiswa = mahasiswaDenganPreferensi();
    actingAsMahasiswa($mahasiswa);

    // Open the ranking editor.
    $component = Volt::test('pages.mahasiswa.profile')->call('updateRanking');

    // Reorder the EXISTING rows (keep every key the view needs, incl. icon),
    // moving lokasi_magang to the top.
    $temp = collect($component->get('temp_rankings'))
        ->sortBy(fn ($c) => $c['key'] === 'lokasi_magang' ? -1 : 0)
        ->values()
        ->map(fn ($c, $i) => array_merge($c, ['rank' => $i + 1]))
        ->all();

    $component->set('temp_rankings', $temp)->call('saveRanking')->assertHasNoErrors();

    $lokasi = MahasiswaKriteria::where('mahasiswa_id', $mahasiswa->id)
        ->where('criteria_key', MahasiswaKriteria::KEY_LOKASI_MAGANG)->first();

    expect($lokasi->rank)->toBe(1);
});


