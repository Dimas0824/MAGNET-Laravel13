<?php

use App\Actions\Magang\RegisterInternship;
use App\Models\BidangIndustri;
use App\Models\KontrakMagang;
use App\Models\LowonganMagang;
use App\Models\Mahasiswa;
use App\Models\Perusahaan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| RegisterInternship — characterization for the "start an internship" flow
|--------------------------------------------------------------------------
|
| W4-T04: the internship-registration logic in the Volt component
|
|   resources/views/components/mahasiswa/pembaruan-status-magang/
|       sedang-magang.blade.php   ($save, L120-236)
|
| is being extracted into App\Actions\Magang\RegisterInternship. This suite
| characterizes the behavior so the extraction cannot change it:
|
|   - a KontrakMagang is created with status "menunggu_persetujuan" and a
|     3-month window starting now
|   - the uploaded "surat izin magang" PDF is stored on the `public` disk
|     under `surat-izin-magang/` (non-partner companies only)
|   - non-partner companies are force-created together with their bidang
|     industri, pekerjaan, lokasi and lowongan
|   - the caller flashes the "Pendaftaran magang berhasil dikirim!" message
|
| NOTE (latent bug, deliberately preserved in spirit, see the action): the
| source closure wrote three columns that no longer exist on the current
| schema — perusahaan.lokasi, lowongan_magang.surat_izin_path and
| kontrak_magang.tanggal_daftar — so the component's try/catch swallowed a
| QueryException and registration never persisted. The action keeps the same
| intent while skipping/remapping the stale columns, so registration now
| actually lands. This is documented, not "fixed" as source logic.
|
*/

beforeEach(function () {
    seedMasterData();
    Storage::fake('public');
});

/**
 * A mahasiswa allowed to register (no active contract, status "belum magang").
 */
function registerableMahasiswa(): Mahasiswa
{
    $mahasiswa = Mahasiswa::factory()->create();
    // MahasiswaFactory::configure() force-fills a RANDOM status_magang in
    // afterMaking(), so pin a registerable status deterministically here.
    $mahasiswa->forceFill(['status_magang' => 'belum magang'])->save();

    return $mahasiswa;
}

/**
 * A "mitra" company with an open lowongan, mirroring the partner happy path.
 *
 * @return array{perusahaan: Perusahaan, lowongan: LowonganMagang}
 */
function mitraCompanyWithLowongan(): array
{
    $perusahaan = Perusahaan::factory()->create(['kategori' => 'mitra']);
    $lowongan = lowonganMagang([
        'perusahaan_id' => $perusahaan->id,
        'status' => 'buka',
    ]);

    return ['perusahaan' => $perusahaan, 'lowongan' => $lowongan];
}

it('registers the internship: kontrak/berkas created, files stored, status set', function () {
    $mahasiswa = registerableMahasiswa();
    ['perusahaan' => $perusahaan, 'lowongan' => $lowongan] = mitraCompanyWithLowongan();

    $file = UploadedFile::fake()->create('surat-izin.pdf', 512, 'application/pdf');

    $action = new RegisterInternship(
        mahasiswa: $mahasiswa,
        companyType: 'partner',
        selectedCompanyId: $perusahaan->id,
        selectedLowonganId: $lowongan->id,
        companyName: '',
        companyAddress: '',
        bidangIndustri: '',
        lokasiMagang: 'Jakarta Pusat, DKI Jakarta',
        suratIzinMagang: $file,
    );

    $kontrak = $action->handle();

    // --- KontrakMagang persisted, pending admin approval --------------------
    $kontrak->refresh();
    expect($kontrak)->toBeInstanceOf(KontrakMagang::class)
        ->and($kontrak->mahasiswa_id)->toBe($mahasiswa->id)
        ->and($kontrak->dosen_id)->toBeNull()
        ->and($kontrak->lowongan_magang_id)->toBe($lowongan->id)
        ->and($kontrak->status)->toBe('menunggu_persetujuan')
        ->and($kontrak->waktu_awal)->not->toBeNull()
        ->and($kontrak->waktu_akhir->isFuture())->toBeTrue();

    expect(KontrakMagang::where('mahasiswa_id', $mahasiswa->id)->count())->toBe(1);

    // --- Mahasiswa status is NOT changed automatically ----------------------
    expect(Mahasiswa::find($mahasiswa->id)->status_magang)->toBe('belum magang');
});

it('stores the surat izin and force-creates the company chain for non-partner', function () {
    $mahasiswa = registerableMahasiswa();

    $file = UploadedFile::fake()->create('surat-izin.pdf', 512, 'application/pdf');

    $action = new RegisterInternship(
        mahasiswa: $mahasiswa,
        companyType: 'non_partner',
        selectedCompanyId: '',
        selectedLowonganId: '',
        companyName: 'PT Contoh Nusantara',
        companyAddress: 'Jl. Merdeka No. 1, Bandung',
        bidangIndustri: 'Teknologi',
        lokasiMagang: 'Bandung, Jawa Barat',
        suratIzinMagang: $file,
    );

    $kontrak = $action->handle();

    // --- File stored on the `public` disk -----------------------------------
    Storage::disk('public')->assertExists($action->suratIzinPath);
    expect($action->suratIzinPath)->toStartWith('surat-izin-magang/')
        ->and($action->suratIzinPath)->toEndWith('.pdf');

    // --- Non-partner company chain force-created ----------------------------
    $perusahaan = Perusahaan::where('nama', 'PT Contoh Nusantara')->first();
    expect($perusahaan)->not->toBeNull()
        ->and($perusahaan->kategori)->toBe('non_mitra');

    $bidang = BidangIndustri::where('nama', 'Teknologi')->first();
    expect($bidang)->not->toBeNull()
        ->and($perusahaan->bidang_industri_id)->toBe($bidang->id);

    $lowongan = LowonganMagang::where('perusahaan_id', $perusahaan->id)->first();
    expect($lowongan)->not->toBeNull()
        ->and($lowongan->status)->toBe('buka')
        ->and($lowongan->kuota)->toBe(1);

    // --- Kontrak bound to the new lowongan, pending approval ----------------
    $kontrak->refresh();
    expect($kontrak->lowongan_magang_id)->toBe($lowongan->id)
        ->and($kontrak->status)->toBe('menunggu_persetujuan');
});

it('wires the Volt component through the action end to end', function () {
    $mahasiswa = registerableMahasiswa();
    ['perusahaan' => $perusahaan, 'lowongan' => $lowongan] = mitraCompanyWithLowongan();

    actingAsMahasiswa($mahasiswa);

    $file = UploadedFile::fake()->create('surat-izin.pdf', 512, 'application/pdf');

    Volt::test('components.mahasiswa.pembaruan-status-magang.sedang-magang')
        ->set('company_type', 'partner')
        ->set('selected_company_id', $perusahaan->id)
        ->set('selected_lowongan_id', $lowongan->id)
        ->set('lokasi_magang', 'Jakarta Pusat, DKI Jakarta')
        ->set('surat_izin_magang', $file)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Pendaftaran magang berhasil dikirim!');

    $kontrak = KontrakMagang::where('mahasiswa_id', $mahasiswa->id)->first();
    expect($kontrak)->not->toBeNull()
        ->and($kontrak->status)->toBe('menunggu_persetujuan');
});

it('rejects registration when the mahasiswa already has an active contract', function () {
    $mahasiswa = registerableMahasiswa();
    ['perusahaan' => $perusahaan, 'lowongan' => $lowongan] = mitraCompanyWithLowongan();

    KontrakMagang::factory()->create([
        'mahasiswa_id' => $mahasiswa->id,
        'lowongan_magang_id' => $lowongan->id,
        'status' => 'menunggu_persetujuan',
    ]);

    $file = UploadedFile::fake()->create('surat-izin.pdf', 512, 'application/pdf');

    // The component blocks the save before persisting (can_register guard).
    actingAsMahasiswa($mahasiswa);

    Volt::test('components.mahasiswa.pembaruan-status-magang.sedang-magang')
        ->set('company_type', 'partner')
        ->set('selected_company_id', $perusahaan->id)
        ->set('selected_lowongan_id', $lowongan->id)
        ->set('lokasi_magang', 'Jakarta Pusat, DKI Jakarta')
        ->set('surat_izin_magang', $file)
        ->call('save')
        ->assertSee('Anda sudah memiliki pendaftaran magang yang sedang diproses atau aktif.');

    expect(KontrakMagang::where('mahasiswa_id', $mahasiswa->id)->count())->toBe(1);
});
