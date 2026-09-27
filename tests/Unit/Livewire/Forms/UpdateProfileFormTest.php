<?php

use App\Livewire\Forms\UpdateProfileForm;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/*
|--------------------------------------------------------------------------
| UpdateProfileForm — validation parity with the profile page
|--------------------------------------------------------------------------
|
| W0-T06c: the personal-data validation is being extracted from the Volt page
| (resources/views/pages/mahasiswa/profile.blade.php L173-180) into a Livewire
| form object. This suite pins the EXACT rules so the page and the form can
| never drift apart.
|
| The `unique:mahasiswa` rule hits the database, so this "Unit" test opts into
| DatabaseTransactions explicitly (Unit tests run db-less by default).
|
*/

uses(DatabaseTransactions::class);

/**
 * A bare Livewire component used purely as the host for the form object.
 * Livewire form objects are validated through their owning component, so a
 * throwaway host lets us exercise UpdateProfileForm in isolation without
 * wiring up the real profile page (that is a later task).
 */
class UpdateProfileFormHost extends Component
{
    public UpdateProfileForm $form;

    public function render()
    {
        return <<<'HTML'
        <div></div>
        HTML;
    }
}

/**
 * Return a complete, valid payload mirroring the profile page's happy path.
 */
function validProfilePayload(): array
{
    return [
        'nama' => 'Budi Santoso',
        'nim' => '2022110001',
        'jurusan' => 'Teknologi Informasi',
        'program_studi' => 'D4 Teknik Informatika',
        'jenis_kelamin' => 'L',
        'alamat' => 'Jl. Contoh No. 1, Kota Malang',
    ];
}

/**
 * Build the form inside its host component through the Livewire test harness.
 */
function updateProfileForm(): UpdateProfileForm
{
    return Livewire::test(UpdateProfileFormHost::class)->instance()->form;
}

/*
|--------------------------------------------------------------------------
| Green path
|--------------------------------------------------------------------------
*/

it('passes valid profile data', function () {
    $form = updateProfileForm();
    $form->fillFrom(validProfilePayload());

    // Should not throw...
    $validated = $form->validate();

    expect($validated)->toBeArray()
        ->and($validated['nama'])->toBe('Budi Santoso')
        ->and($validated['nim'])->toBe('2022110001');
});

/*
|--------------------------------------------------------------------------
| Required fields
|--------------------------------------------------------------------------
*/

it('fails when nama missing', function () {
    $form = updateProfileForm();
    $form->fillFrom([...validProfilePayload(), 'nama' => '']);

    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class);
});

/*
|--------------------------------------------------------------------------
| Full rule parity with the page (profile.blade.php L173-180)
|--------------------------------------------------------------------------
*/

it('enforces the same rules as the page', function () {
    // nama: required|string|max:255
    $form = updateProfileForm();
    $form->fillFrom([...validProfilePayload(), 'nama' => str_repeat('a', 256)]);
    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class, null, 'nama max:255 failed');

    // nim: required|string|max:20
    $form = updateProfileForm();
    $form->fillFrom([...validProfilePayload(), 'nim' => str_repeat('9', 21)]);
    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class, null, 'nim max:20 failed');

    // jurusan: required|string|max:255
    $form = updateProfileForm();
    $form->fillFrom([...validProfilePayload(), 'jurusan' => '']);
    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class, null, 'jurusan required failed');

    // program_studi: required|string|max:255
    $form = updateProfileForm();
    $form->fillFrom([...validProfilePayload(), 'program_studi' => str_repeat('a', 256)]);
    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class, null, 'program_studi max:255 failed');

    // jenis_kelamin: required|in:L,P
    $form = updateProfileForm();
    $form->fillFrom([...validProfilePayload(), 'jenis_kelamin' => 'X']);
    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class, null, 'jenis_kelamin in:L,P failed');

    // alamat: required|string|max:500
    $form = updateProfileForm();
    $form->fillFrom([...validProfilePayload(), 'alamat' => str_repeat('a', 501)]);
    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class, null, 'alamat max:500 failed');
});
