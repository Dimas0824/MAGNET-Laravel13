<?php

namespace App\Livewire\Forms;

use Livewire\Attributes\Validate;
use Livewire\Form;

/**
 * W0-T06a — registration validation extracted from the Volt register page.
 *
 * The rules below are a 1:1 copy of `rules([...])` in
 * resources/views/pages/auth/register.blade.php L27-36. Keep the two in sync
 * until the page is rewired to consume this form object (later task).
 *
 * This object ONLY validates and holds data — persistence (Mahasiswa::create,
 * password hashing, auth, events) stays in a later Action task.
 */
class RegisterForm extends Form
{
    // nim: required|string|min:10|regex:/^[0-9]+$/  (blade L28)
    #[Validate(['required', 'string', 'min:10', 'regex:/^[0-9]+$/'])]
    public string $nim = '';

    // nama: required|string|max:255  (blade L29)
    #[Validate(['required', 'string', 'max:255'])]
    public string $nama = '';

    // email: required|string|email|max:255|unique:mahasiswa  (blade L30)
    #[Validate(['required', 'string', 'email', 'max:255', 'unique:mahasiswa'])]
    public string $email = '';

    // jurusan: present on the page state but NOT validated (blade L16/L184-187),
    // carried here for parity with the registration payload.
    public string $jurusan = 'Teknologi Informasi';

    // program_studi: required|string  (blade L31)
    #[Validate(['required', 'string'])]
    public string $program_studi = '';

    // angkatan: required|numeric|min:1|max:100  (blade L32)
    #[Validate(['required', 'numeric', 'min:1', 'max:100'])]
    public string|int|null $angkatan = null;

    // jenis_kelamin: required|in:L,P  (blade L33)
    #[Validate(['required', 'in:L,P'])]
    public string $jenis_kelamin = '';

    // tanggal_lahir: required|date|before:today  (blade L34)
    #[Validate(['required', 'date', 'before:today'])]
    public string $tanggal_lahir = '';

    // alamat: required|string|max:500  (blade L35)
    #[Validate(['required', 'string', 'max:500'])]
    public string $alamat = '';

    // password: required|string|confirmed|Password::default()  (blade L36).
    // Laravel 13's Password::default() resolves to Password::min(8) for an app
    // with no Password::defaults() callback, i.e. the `min:8` rule. It is
    // written as `min:8` here because an attribute argument must be a constant
    // expression (a static method call is not allowed). This keeps byte-for-byte
    // parity with the page's `password.min` = "minimal 8 karakter" message.
    #[Validate(['required', 'string', 'confirmed', 'min:8'])]
    public string $password = '';

    public string $password_confirmation = '';

    /**
     * Hydrate the form from a plain array (e.g. a request payload or the
     * page's state). Unknown keys are ignored on purpose.
     */
    public function fillFrom(array $data): void
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }
}
