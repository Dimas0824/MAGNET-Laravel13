<?php

use App\Actions\Kontrak\ApproveKontrakMagang;
use App\Actions\Kontrak\RejectKontrakMagang;
use App\Models\Admin;
use App\Models\DosenPembimbing;
use App\Models\KontrakMagang;
use App\Models\Mahasiswa;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Approve / Reject Kontrak Magang — characterization tests (W2-T01)
|--------------------------------------------------------------------------
|
| These tests pin the behaviour of the approve/reject handlers on the
| pembaruan-status-magang detail Volt page while the raw DB::table() writes
| are refactored into App\Actions\Kontrak\{Approve,Reject}KontrakMagang.
|
| Observable contract under test (must not change):
|   - kontrak_magang.status  -> 'disetujui' / 'ditolak'
|   - kontrak_magang.dosen_id (approve only)
|   - kontrak_magang.keterangan (exact text, incl. admin name + reason)
|   - mahasiswa.status_magang -> 'sedang magang' / 'belum magang'
|   - the success flash renders in the component HTML after the action
|   - the write goes through the extracted Action, inside one DB::transaction
|
*/

beforeEach(function () {
    seedMasterData();
});

/**
 * Seed a pending kontrak for a fresh mahasiswa + admin and return the trio.
 *
 * @return array{admin: Admin, mahasiswa: Mahasiswa, dosen: DosenPembimbing, kontrak: KontrakMagang}
 */
function seedPendingKontrak(): array
{
    $admin = Admin::factory()->create(['nama' => 'Budi Admin']);
    actingAsAdmin($admin);

    $dosen = DosenPembimbing::factory()->create(['nama' => 'Dr. Pembimbing']);
    $mahasiswa = Mahasiswa::factory()->create(['status_magang' => 'belum magang']);

    $kontrak = KontrakMagang::factory()->create([
        'mahasiswa_id' => $mahasiswa->id,
        'dosen_id' => $dosen->id,
        'status' => 'menunggu_persetujuan',
        'keterangan' => 'menunggu review',
    ]);

    return compact('admin', 'mahasiswa', 'dosen', 'kontrak');
}

it('approves: kontrak status disetujui, mahasiswa sedang magang, keterangan set', function () {
    ['mahasiswa' => $mahasiswa, 'dosen' => $dosen, 'kontrak' => $kontrak] = seedPendingKontrak();

    // Sanity: the detail page renders before action.
    $this->get(route('admin.detail-pengajuan-pembaruan-status-magang', $mahasiswa->id))
        ->assertOk();

    $component = Volt::test('pages.admin.magang.pembaruan-status-magang.detail', ['id' => $mahasiswa->id])
        ->set('dosen_selected', $dosen->id)
        ->set('admin_keterangan', 'Disetujui dengan catatan lengkap.')
        ->call('approveContract');

    $component->assertHasNoErrors();

    $kontrak->refresh();
    $mahasiswa->refresh();

    // kontrak_magang row
    expect($kontrak->status)->toBe('disetujui');
    expect((int) $kontrak->dosen_id)->toBe((int) $dosen->id);
    expect($kontrak->keterangan)->toBe('Disetujui dengan catatan lengkap.');

    // mahasiswa row
    expect($mahasiswa->status_magang)->toBe('sedang magang');

    // flash + UI side effects. Livewire runs the action on its own internal
    // request, so the flash is asserted through the re-rendered component HTML
    // (the page renders session('success')) rather than the outer session().
    $component->assertSet('isProcessing', false);
    $component->assertDispatched('$refresh');
    expect($component->html())->toContain('Kontrak magang berhasil disetujui dan status telah diperbarui.');
});

it('approves via the ApproveKontrakMagang action', function () {
    ['mahasiswa' => $mahasiswa, 'dosen' => $dosen, 'kontrak' => $kontrak] = seedPendingKontrak();

    // The action is directly callable with the same observable contract.
    app(ApproveKontrakMagang::class)->handle($kontrak->id, $dosen->id, 'Setuju via action.');

    $kontrak->refresh();
    $mahasiswa->refresh();

    expect($kontrak->status)->toBe('disetujui');
    expect($kontrak->keterangan)->toBe('Setuju via action.');
    expect($mahasiswa->status_magang)->toBe('sedang magang');
});

it('approves with a default keterangan when the admin leaves it blank', function () {
    ['mahasiswa' => $mahasiswa, 'dosen' => $dosen, 'kontrak' => $kontrak] = seedPendingKontrak();

    Volt::test('pages.admin.magang.pembaruan-status-magang.detail', ['id' => $mahasiswa->id])
        ->set('dosen_selected', $dosen->id)
        ->set('admin_keterangan', '')
        ->call('approveContract');

    $kontrak->refresh();

    // Default format: "Kontrak disetujui oleh {adminName} pada {d M Y H:i}".
    // auth()->user() resolves the DEFAULT guard, which is null in this harness,
    // so the handler's `'Admin'` fallback is what the refactor must preserve.
    expect($kontrak->status)->toBe('disetujui');
    expect($kontrak->keterangan)->toStartWith('Kontrak disetujui oleh Admin pada ');
    expect($kontrak->keterangan)->not->toBe('menunggu review');
});

it('rejects: kontrak status ditolak, mahasiswa belum magang, reason in keterangan', function () {
    ['mahasiswa' => $mahasiswa, 'kontrak' => $kontrak] = seedPendingKontrak();

    $this->get(route('admin.detail-pengajuan-pembaruan-status-magang', $mahasiswa->id))
        ->assertOk();

    $component = Volt::test('pages.admin.magang.pembaruan-status-magang.detail', ['id' => $mahasiswa->id])
        ->set('rejection_reason', 'Berkas persyaratan tidak lengkap.')
        ->call('rejectContract');

    $component->assertHasNoErrors();

    $kontrak->refresh();
    $mahasiswa->refresh();

    // kontrak_magang row
    expect($kontrak->status)->toBe('ditolak');

    // Format: "Ditolak oleh {adminName} pada {d M Y H:i}. Alasan: {reason}".
    // Same default-guard fallback as approve: adminName resolves to 'Admin'.
    expect($kontrak->keterangan)->toBeString();
    expect($kontrak->keterangan)->toContain('Ditolak oleh Admin pada ');
    expect($kontrak->keterangan)->toContain('. Alasan: Berkas persyaratan tidak lengkap.');

    // mahasiswa row
    expect($mahasiswa->status_magang)->toBe('belum magang');

    // flash + UI side effects (asserted via re-rendered component HTML).
    $component->assertSet('isProcessing', false);
    $component->assertDispatched('$refresh');
    expect($component->html())->toContain('Kontrak magang berhasil ditolak dan status telah diperbarui.');
});

it('rejects via the RejectKontrakMagang action', function () {
    ['mahasiswa' => $mahasiswa, 'kontrak' => $kontrak] = seedPendingKontrak();

    app(RejectKontrakMagang::class)->handle($kontrak->id, 'Ditolak via action. Alasan: contoh.');

    $kontrak->refresh();
    $mahasiswa->refresh();

    expect($kontrak->status)->toBe('ditolak');
    expect($kontrak->keterangan)->toBe('Ditolak via action. Alasan: contoh.');
    expect($mahasiswa->status_magang)->toBe('belum magang');
});
