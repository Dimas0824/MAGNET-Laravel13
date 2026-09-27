<?php

use App\Livewire\Forms\RegisterForm;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/*
|--------------------------------------------------------------------------
| RegisterForm — validation parity with the register page
|--------------------------------------------------------------------------
|
| W0-T06a: the registration validation is being extracted from the Volt page
| (resources/views/pages/auth/register.blade.php L27-36) into a Livewire
| form object. This suite pins the EXACT rules so the page and the form can
| never drift apart.
|
| Lives under Feature/ because the `unique:mahasiswa` rule queries the DB and
| therefore needs the migrated schema (Feature tests get RefreshDatabase via
| tests/Pest.php). It self-provisions its schema, so it is not order-dependent.
|
*/

/**
 * A bare Livewire component used purely as the host for the form object.
 * Livewire form objects are validated through their owning component, so a
 * throwaway host lets us exercise RegisterForm in isolation without wiring
 * up the real registration page (that is a later task).
 */
class RegisterFormHost extends Component
{
    public RegisterForm $form;

    public function render()
    {
        return <<<'HTML'
        <div></div>
        HTML;
    }
}

/**
 * Return a complete, valid payload mirroring the register page's happy path.
 */
function validRegisterPayload(): array
{
    return [
        'nim' => '2022110001',
        'nama' => 'Budi Santoso',
        'email' => 'budi.santoso@example.com',
        'program_studi' => 'D4 Teknik Informatika',
        'angkatan' => 23,
        'jenis_kelamin' => 'L',
        'tanggal_lahir' => '2003-01-15',
        'alamat' => 'Jl. Contoh No. 1, Kota Malang',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ];
}

/**
 * Build the form inside its host component through the Livewire test harness.
 */
function registerForm(): RegisterForm
{
    return Livewire::test(RegisterFormHost::class)->instance()->form;
}

/*
|--------------------------------------------------------------------------
| Green path
|--------------------------------------------------------------------------
*/

it('passes valid data', function () {
    $form = registerForm();
    $form->fillFrom(validRegisterPayload());

    // Should not throw...
    $validated = $form->validate();

    expect($validated)->toBeArray()
        ->and($validated['nim'])->toBe('2022110001')
        ->and($validated['email'])->toBe('budi.santoso@example.com');
});

/*
|--------------------------------------------------------------------------
| Required fields
|--------------------------------------------------------------------------
*/

it('fails when a required field is missing', function () {
    $form = registerForm();
    // Missing nim (and every other required field)...
    $form->fillFrom([]);

    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class);
});

/*
|--------------------------------------------------------------------------
| Full rule parity with the page
|--------------------------------------------------------------------------
|
| One assertion per rule copied from register.blade.php L27-36.
|
*/

it('enforces the same rules as the page (unique email / password confirmation etc.)', function () {
    // nim: required|string|min:10|regex:/^[0-9]+$/
    $form = registerForm();
    $form->fillFrom([...validRegisterPayload(), 'nim' => '12345']);
    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class, null, 'nim min:10 failed');

    $form = registerForm();
    $form->fillFrom([...validRegisterPayload(), 'nim' => '20221100AB']);
    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class, null, 'nim regex (digits only) failed');

    // nama: required|string|max:255
    $form = registerForm();
    $form->fillFrom([...validRegisterPayload(), 'nama' => str_repeat('a', 256)]);
    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class, null, 'nama max:255 failed');

    // email: required|string|email|max:255|unique:mahasiswa
    $form = registerForm();
    $form->fillFrom([...validRegisterPayload(), 'email' => 'not-an-email']);
    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class, null, 'email format failed');

    // program_studi: required|string
    $form = registerForm();
    $form->fillFrom([...validRegisterPayload(), 'program_studi' => '']);
    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class, null, 'program_studi required failed');

    // angkatan: required|numeric|min:1|max:100
    $form = registerForm();
    $form->fillFrom([...validRegisterPayload(), 'angkatan' => 0]);
    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class, null, 'angkatan min:1 failed');

    $form = registerForm();
    $form->fillFrom([...validRegisterPayload(), 'angkatan' => 101]);
    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class, null, 'angkatan max:100 failed');

    // jenis_kelamin: required|in:L,P
    $form = registerForm();
    $form->fillFrom([...validRegisterPayload(), 'jenis_kelamin' => 'X']);
    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class, null, 'jenis_kelamin in:L,P failed');

    // tanggal_lahir: required|date|before:today
    $form = registerForm();
    $form->fillFrom([...validRegisterPayload(), 'tanggal_lahir' => now()->addDay()->toDateString()]);
    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class, null, 'tanggal_lahir before:today failed');

    // alamat: required|string|max:500
    $form = registerForm();
    $form->fillFrom([...validRegisterPayload(), 'alamat' => str_repeat('a', 501)]);
    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class, null, 'alamat max:500 failed');

    // password: required|string|confirmed|Password::default() (min:8)
    $form = registerForm();
    $form->fillFrom([...validRegisterPayload(), 'password' => 'short', 'password_confirmation' => 'short']);
    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class, null, 'password min failed');

    $form = registerForm();
    $form->fillFrom([...validRegisterPayload(), 'password_confirmation' => 'Mismatch123!']);
    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class, null, 'password confirmed failed');

    // unique:mahasiswa — an existing row with this email must be rejected.
    \App\Models\Mahasiswa::forceCreate([
        'nim' => '2022999999',
        'nama' => 'Existing User',
        'email' => 'taken@example.com',
        'password' => bcrypt('Password123!'),
        'angkatan' => 23,
        'jenis_kelamin' => 'L',
        'tanggal_lahir' => '2003-01-15',
        'jurusan' => 'Teknologi Informasi',
        'program_studi' => 'D4 Teknik Informatika',
        'alamat' => 'Alamat lama',
    ]);

    $form = registerForm();
    $form->fillFrom([...validRegisterPayload(), 'email' => 'taken@example.com']);
    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class, null, 'email unique:mahasiswa failed');
});
