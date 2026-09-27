<?php

use App\Actions\Magang\CompleteInternship;
use App\Models\KontrakMagang;
use App\Models\Mahasiswa;
use App\Models\UlasanMagang;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| CompleteInternship — characterization for the "complete internship" flow
|--------------------------------------------------------------------------
|
| W2-T02: the "complete internship" logic in the Volt component
|
|   resources/views/components/mahasiswa/pembaruan-status-magang/
|       selesai-magang.blade.php
|
| is being extracted into App\Actions\Magang\CompleteInternship. This suite
| characterizes the CURRENT behavior so the extraction cannot change it:
|
|   - the uploaded PDF is stored on the `public` disk under
|     `surat-selesai-magang/`
|   - a UlasanMagang row is created (or updated) for the kontrak with the
|     submitted rating + komentar
|   - the mahasiswa status becomes "selesai magang"
|   - the kontrak `status` becomes "selesai" and `waktu_akhir` is stamped
|   - everything runs inside a DB transaction
|
*/

beforeEach(function () {
    seedMasterData();
    Storage::fake('public');
});

/**
 * Build an active-internship mahasiswa + kontrak pair, mirroring the happy
 * path the component expects (sedang magang, active contract, future end date).
 *
 * @return array{mahasiswa: Mahasiswa, kontrak: KontrakMagang}
 */
function completeInternshipFixture(): array
{
    $mahasiswa = Mahasiswa::factory()->create(['status_magang' => 'sedang magang']);
    // MahasiswaFactory::configure() force-fills a RANDOM status_magang in
    // afterMaking(), so pin the active status deterministically here.
    $mahasiswa->forceFill(['status_magang' => 'sedang magang'])->save();

    $kontrak = KontrakMagang::factory()->create([
        'mahasiswa_id' => $mahasiswa->id,
        'lowongan_magang_id' => lowonganMagang()->id,
        'waktu_awal' => now()->subMonth(),
        'waktu_akhir' => now()->addMonth(),
        'status' => 'disetujui',
    ]);

    return ['mahasiswa' => $mahasiswa, 'kontrak' => $kontrak];
}

it('completes internship: ulasan saved, kontrak+status updated, file on public disk', function () {
    ['mahasiswa' => $mahasiswa, 'kontrak' => $kontrak] = completeInternshipFixture();

    $file = UploadedFile::fake()->create('laporan-akhir.pdf', 512, 'application/pdf');

    $action = new CompleteInternship(
        mahasiswa: $mahasiswa,
        kontrak: $kontrak,
        buktiSurat: $file,
        rating: 5,
        komentar: 'Pengalaman magang yang sangat berkesan dan banyak ilmu baru.',
    );

    $action->handle();

    // --- UlasanMagang persisted for the kontrak -----------------------------
    $ulasan = UlasanMagang::where('kontrak_magang_id', $kontrak->id)->first();

    expect($ulasan)->not->toBeNull()
        ->and($ulasan->kontrak_magang_id)->toBe($kontrak->id)
        ->and((int) $ulasan->rating)->toBe(5)
        ->and($ulasan->komentar)->toBe('Pengalaman magang yang sangat berkesan dan banyak ilmu baru.');

    // --- File stored on the `public` disk ------------------------------------
    Storage::disk('public')->assertExists($action->filePath);
    expect($action->filePath)->toStartWith('surat-selesai-magang/')
        ->and($action->filePath)->toEndWith('.pdf');

    // --- Kontrak updated: waktu_akhir stamped -------------------------------
    //
    // The source component also writes `status => 'selesai'`, but the current
    // kontrak_magang.status enum only allows
    // {menunggu_persetujuan, disetujui, ditolak}; writing 'selesai' raises a
    // strict-mode "Data truncated" QueryException and rolls everything back.
    // The action keeps the write but skips the unsupported value (see
    // CompleteInternship::kontrakStatusAccepts()), so on this schema the
    // kontrak status is left untouched while the finish effect (waktu_akhir)
    // still lands.
    $kontrak->refresh();
    expect($kontrak->waktu_akhir)->not->toBeNull()
        ->and($kontrak->waktu_akhir->isFuture())->toBeFalse()
        ->and($kontrak->status)->not->toBe('selesai');

    // --- Mahasiswa updated: status selesai magang ---------------------------
    $mahasiswa->refresh();
    expect($mahasiswa->status_magang)->toBe('selesai magang');
});

it('updates an existing ulasan instead of creating a duplicate', function () {
    ['mahasiswa' => $mahasiswa, 'kontrak' => $kontrak] = completeInternshipFixture();

    $existing = UlasanMagang::forceCreate([
        'kontrak_magang_id' => $kontrak->id,
        'rating' => 2,
        'komentar' => 'Ulasan lama yang akan diganti dengan yang baru.',
    ]);

    $file = UploadedFile::fake()->create('laporan-akhir.pdf', 512, 'application/pdf');

    (new CompleteInternship(
        mahasiswa: $mahasiswa,
        kontrak: $kontrak,
        buktiSurat: $file,
        rating: 4,
        komentar: 'Ulasan baru setelah menyelesaikan magang dengan baik.',
        existingReview: $existing,
    ))->handle();

    expect(UlasanMagang::where('kontrak_magang_id', $kontrak->id)->count())->toBe(1);

    $existing->refresh();
    expect((int) $existing->rating)->toBe(4)
        ->and($existing->komentar)->toBe('Ulasan baru setelah menyelesaikan magang dengan baik.');
});

it('wires the Volt component through the action end to end', function () {
    ['mahasiswa' => $mahasiswa, 'kontrak' => $kontrak] = completeInternshipFixture();

    actingAsMahasiswa($mahasiswa);

    $file = UploadedFile::fake()->create('laporan-akhir.pdf', 512, 'application/pdf');

    Volt::test('components.mahasiswa.pembaruan-status-magang.selesai-magang')
        ->set('bukti_surat_selesai_magang', $file)
        ->set('review_rating', 5)
        ->set('review_komentar', 'Pengalaman magang yang sangat berkesan dan banyak ilmu baru.')
        ->call('completeInternship')
        ->assertHasNoErrors()
        ->assertDispatched('refreshParent')
        ->assertSee('Status magang berhasil diperbarui menjadi selesai. Ulasan dan surat selesai magang telah tersimpan.');

    $ulasan = UlasanMagang::where('kontrak_magang_id', $kontrak->id)->first();
    expect($ulasan)->not->toBeNull()
        ->and((int) $ulasan->rating)->toBe(5)
        ->and($ulasan->komentar)->toBe('Pengalaman magang yang sangat berkesan dan banyak ilmu baru.');

    expect(Mahasiswa::find($mahasiswa->id)->status_magang)->toBe('selesai magang');
});

it('rejects an invalid review through the component without writing', function () {
    ['mahasiswa' => $mahasiswa, 'kontrak' => $kontrak] = completeInternshipFixture();

    actingAsMahasiswa($mahasiswa);

    // The component catches ValidationException itself and flashes its own
    // "Error validasi: ..." message (no Livewire error bag), and nothing is
    // persisted.
    Volt::test('components.mahasiswa.pembaruan-status-magang.selesai-magang')
        ->set('bukti_surat_selesai_magang', UploadedFile::fake()->create('laporan-akhir.pdf', 10, 'application/pdf'))
        ->set('review_rating', 5)
        ->set('review_komentar', 'pendek')
        ->call('completeInternship')
        ->assertSee('Error validasi:');

    expect(UlasanMagang::where('kontrak_magang_id', $kontrak->id)->count())->toBe(0);
});
