<?php

use App\Models\Mahasiswa;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Livewire\Volt\Volt;

/**
 * Characterization for the public registration flow (W4-T10).
 *
 * Locks the CURRENT behavior so the refactor (extract RegisterForm + a
 * RegisterMahasiswa action) cannot change it: validation errors, the created
 * Mahasiswa row, the Registered event, the guard login, the flash message and
 * the redirect to the preference wizard.
 */
beforeEach(function () {
    seedMasterData();
});

it('registers a new mahasiswa, logs them in, fires Registered, and redirects', function () {
    Event::fake([Registered::class]);

    Volt::test('pages.auth.register')
        ->set('form.nim', '2022110001')
        ->set('form.nama', 'Budi Santoso')
        ->set('form.email', 'budi@example.com')
        ->set('form.program_studi', 'D4 Teknik Informatika')
        ->set('form.angkatan', 23)
        ->set('form.jenis_kelamin', 'L')
        ->set('form.tanggal_lahir', '2003-01-15')
        ->set('form.alamat', 'Jl. Contoh No. 1, Malang')
        ->set('form.password', 'Password123!')
        ->set('form.password_confirmation', 'Password123!')
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('mahasiswa.persiapan-preferensi', absolute: false));

    $mahasiswa = Mahasiswa::where('email', 'budi@example.com')->first();
    expect($mahasiswa)->not->toBeNull()
        ->and($mahasiswa->nim)->toBe('2022110001')
        ->and($mahasiswa->nama)->toBe('Budi Santoso')
        ->and(auth('mahasiswa')->id())->toBe($mahasiswa->id);

    Event::assertDispatched(Registered::class);
});

it('rejects a registration with an already-registered email', function () {
    Mahasiswa::factory()->create(['email' => 'taken@example.com']);

    Volt::test('pages.auth.register')
        ->set('form.nim', '2022110002')
        ->set('form.nama', 'Siti Aminah')
        ->set('form.email', 'taken@example.com')
        ->set('form.program_studi', 'D4 Sistem Informasi Bisnis')
        ->set('form.angkatan', 22)
        ->set('form.jenis_kelamin', 'P')
        ->set('form.tanggal_lahir', '2002-08-17')
        ->set('form.alamat', 'Jl. Ijen No. 12, Malang')
        ->set('form.password', 'Password123!')
        ->set('form.password_confirmation', 'Password123!')
        ->call('register')
        ->assertHasErrors(['form.email']);

    expect(Mahasiswa::where('email', 'taken@example.com')->count())->toBe(1);
});

it('requires the NIM to be at least 10 digits', function () {
    Volt::test('pages.auth.register')
        ->set('form.nim', '123')
        ->set('form.nama', 'Andi')
        ->set('form.email', 'andi@example.com')
        ->set('form.program_studi', 'D4 Teknik Informatika')
        ->set('form.angkatan', 23)
        ->set('form.jenis_kelamin', 'L')
        ->set('form.tanggal_lahir', '2003-01-15')
        ->set('form.alamat', 'Jl. Test')
        ->set('form.password', 'Password123!')
        ->set('form.password_confirmation', 'Password123!')
        ->call('register')
        ->assertHasErrors(['form.nim']);
});
