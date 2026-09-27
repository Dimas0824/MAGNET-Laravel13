<?php

use App\Models\DosenPembimbing;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;

/**
 * Characterization for the dosen profile page (W4-T03).
 *
 * Locks the CURRENT behavior before the refactor (extract a ProfileForm +
 * Actions): renders, personal-data update persists, password change re-hashes,
 * and validation rejects a duplicate NIDN.
 */
beforeEach(function () {
    seedMasterData();
});

it('renders the dosen profile page', function () {
    $dosen = DosenPembimbing::factory()->create();
    actingAsDosen($dosen);

    Volt::test('pages.dosen.profile')
        ->assertOk()
        ->assertSee($dosen->nama);
});

it('persists a personal-data update', function () {
    $dosen = DosenPembimbing::factory()->create(['nama' => 'Nama Lama']);
    actingAsDosen($dosen);

    Volt::test('pages.dosen.profile')
        ->call('updatePersonalData')
        ->set('nama', 'Nama Baru')
        ->set('nidn', $dosen->nidn)
        ->set('jenis_kelamin', $dosen->jenis_kelamin)
        ->call('savePersonalData');

    // The unique rule now targets dosen_pembimbing, so the save persists.
    expect($dosen->fresh()->nama)->toBe('Nama Baru');
});

it('changes the password with a correct current password', function () {
    $dosen = DosenPembimbing::factory()->create();
    // Factory's afterMaking overwrites password; set it explicitly after create.
    $dosen->forceFill(['password' => Hash::make('oldpassword')])->saveQuietly();
    $dosen->refresh();
    actingAsDosen($dosen);

    // Password::min(8)->mixedCase()->numbers() requires upper + lower + digit.
    Volt::test('pages.dosen.profile')
        ->call('updatePassword')
        ->set('current_password', 'oldpassword')
        ->set('new_password', 'Newpassword123')
        ->set('new_password_confirmation', 'Newpassword123')
        ->call('saveNewPassword')
        ->assertHasNoErrors();

    expect(Hash::check('Newpassword123', $dosen->fresh()->password))->toBeTrue();
});

it('rejects a duplicate NIDN on personal-data save', function () {
    $other = DosenPembimbing::factory()->create(['nidn' => '0011111111']);
    $dosen = DosenPembimbing::factory()->create();
    actingAsDosen($dosen);

    Volt::test('pages.dosen.profile')
        ->call('updatePersonalData')
        ->set('nama', $dosen->nama)
        ->set('nidn', '0011111111')
        ->set('jenis_kelamin', $dosen->jenis_kelamin)
        ->call('savePersonalData')
        ->assertHasErrors('nidn');

    expect($dosen->fresh()->nidn)->not->toBe('0011111111');
});
