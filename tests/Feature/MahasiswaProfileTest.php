<?php

use App\Events\MahasiswaPreferenceUpdated;
use App\Models\Mahasiswa;
use App\Models\MahasiswaKriteria;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;

beforeEach(function () {
    seedMasterData();
});

it('renders the mahasiswa profile page', function () {
    $mahasiswa = mahasiswaDenganPreferensi();
    actingAsMahasiswa($mahasiswa);

    $this->get(route('profile'))->assertOk();
});

it('loads the criteria preferences without repeating the same relations', function () {
    $mahasiswa = mahasiswaDenganPreferensi();
    actingAsMahasiswa($mahasiswa);

    DB::enableQueryLog();
    $this->get(route('profile'))->assertOk();
    $log = collect(DB::getQueryLog());
    DB::disableQueryLog();

    // The lookup tables joined for the criteria relations must not be re-read
    // once per access site (mount + loadCriteriaRankings + cancel path). The
    // dropdown-option plucks are separate and expected, so count only reads
    // that come from a join/where on the criteria tables.
    foreach (['pekerjaan', 'bidang_industri', 'lokasi_magang'] as $table) {
        $reads = $log->filter(function (array $entry) use ($table) {
            $sql = strtolower($entry['query']);

            return preg_match('/from `'.$table.'` where `id` in \(/', $sql) === 1;
        });

        expect($reads->count())->toBeLessThanOrEqual(1, "{$table} case read {$reads->count()} times");
    }
});

it('saves new preferences and emits one event', function () {
    $mahasiswa = mahasiswaDenganPreferensi();
    actingAsMahasiswa($mahasiswa);

    Event::fake([MahasiswaPreferenceUpdated::class]);

    Volt::test('pages.mahasiswa.profile')
        ->set('pekerjaan', 'Software Engineer')
        ->set('bidang_industri', 'Teknologi')
        ->set('lokasi_magang', 'Area Malang Raya')
        ->set('jenis_magang', 'tidak berbayar')
        ->set('open_remote', 'tidak')
        ->call('saveNewPreference')
        ->assertHasNoErrors();

    $jenis = MahasiswaKriteria::where('mahasiswa_id', $mahasiswa->id)
        ->where('criteria_key', MahasiswaKriteria::KEY_JENIS_MAGANG)->first();
    $remote = MahasiswaKriteria::where('mahasiswa_id', $mahasiswa->id)
        ->where('criteria_key', MahasiswaKriteria::KEY_OPEN_REMOTE)->first();

    expect($jenis->value_enum)->toBe('tidak berbayar')
        ->and($remote->value_enum)->toBe('tidak');

    Event::assertDispatchedTimes(MahasiswaPreferenceUpdated::class, 1);
});

it('saves ranking priorities', function () {
    $mahasiswa = mahasiswaDenganPreferensi();
    actingAsMahasiswa($mahasiswa);

    Event::fake([MahasiswaPreferenceUpdated::class]);

    $component = Volt::test('pages.mahasiswa.profile')->call('updateRanking');

    $temp = collect($component->get('temp_rankings'))
        ->sortBy(fn ($c) => $c['key'] === 'lokasi_magang' ? -1 : 0)
        ->values()
        ->map(fn ($c, $i) => array_merge($c, ['rank' => $i + 1]))
        ->all();

    $component->set('temp_rankings', $temp)->call('saveRanking')->assertHasNoErrors();

    $lokasi = MahasiswaKriteria::where('mahasiswa_id', $mahasiswa->id)
        ->where('criteria_key', MahasiswaKriteria::KEY_LOKASI_MAGANG)->first();

    expect($lokasi->rank)->toBe(1);

    Event::assertDispatchedTimes(MahasiswaPreferenceUpdated::class, 1);
});

it('updates personal data', function () {
    $mahasiswa = mahasiswaDenganPreferensi();
    actingAsMahasiswa($mahasiswa);

    Volt::test('pages.mahasiswa.profile')
        ->set('nama', 'Nama Baru')
        ->set('nim', '9999999999')
        ->set('jurusan', 'Teknik Elektro')
        ->set('program_studi', 'D4 Teknik Informatika')
        ->set('jenis_kelamin', 'P')
        ->set('alamat', 'Jl. Baru No. 1')
        ->call('savePersonalData')
        ->assertHasNoErrors();

    $mahasiswa->refresh();

    expect($mahasiswa->nama)->toBe('Nama Baru')
        ->and($mahasiswa->nim)->toBe('9999999999')
        ->and($mahasiswa->jurusan)->toBe('Teknik Elektro')
        ->and($mahasiswa->jenis_kelamin)->toBe('P')
        ->and($mahasiswa->alamat)->toBe('Jl. Baru No. 1');
});

it('changes password', function () {
    $mahasiswa = mahasiswaDenganPreferensi();
    actingAsMahasiswa($mahasiswa);

    Volt::test('pages.mahasiswa.profile')
        ->set('current_password', 'mahasiswa123')
        ->set('new_password', 'passwordbaru')
        ->set('new_password_confirmation', 'passwordbaru')
        ->call('saveNewPassword')
        ->assertHasNoErrors();

    $mahasiswa->refresh();

    expect(Hash::check('passwordbaru', $mahasiswa->password))->toBeTrue();
});
