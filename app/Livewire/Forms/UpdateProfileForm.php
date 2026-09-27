<?php

namespace App\Livewire\Forms;

use Livewire\Attributes\Validate;
use Livewire\Form;

/**
 * W0-T06c — personal-data validation extracted from the Volt profile page.
 *
 * The rules below are a 1:1 copy of `validate([...])` in
 * resources/views/pages/mahasiswa/profile.blade.php L173-180. Keep the two in
 * sync until the page is rewired to consume this form object (later task W4-T01).
 *
 * This object ONLY validates and holds data — persistence ($mahasiswa->update)
 * stays in a later Action task.
 *
 * NOTE: the page's `nim` rule also carries `unique:mahasiswa,nim,<id>`, which
 * needs the owning model id at runtime. A form-object attribute argument must be
 * a constant expression, so that runtime clause is composed in the consumer
 * (a later task) via a `Rule::unique()` override; the static copy here keeps
 * `required|string|max:20` for parity with the page's base rules.
 */
class UpdateProfileForm extends Form
{
    // nama: required|string|max:255  (profile.blade.php L174)
    #[Validate(['required', 'string', 'max:255'])]
    public string $nama = '';

    // nim: required|string|max:20|unique:mahasiswa,nim,<id>  (profile.blade.php L175)
    #[Validate(['required', 'string', 'max:20'])]
    public string $nim = '';

    // jurusan: required|string|max:255  (profile.blade.php L176)
    #[Validate(['required', 'string', 'max:255'])]
    public string $jurusan = '';

    // program_studi: required|string|max:255  (profile.blade.php L177)
    #[Validate(['required', 'string', 'max:255'])]
    public string $program_studi = '';

    // jenis_kelamin: required|in:L,P  (profile.blade.php L178)
    #[Validate(['required', 'in:L,P'])]
    public string $jenis_kelamin = '';

    // alamat: required|string|max:500  (profile.blade.php L179)
    #[Validate(['required', 'string', 'max:500'])]
    public string $alamat = '';

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

    /**
     * The base validation rules as a plain `field => rules` array.
     *
     * W4-T01: lets the Volt profile page validate through this form object's
     * rules WITHOUT binding it as a component form object (which would force a
     * `personalForm.nama` wire:model rename across the whole template and risk
     * behavior drift). The page merges the runtime `unique:mahasiswa,nim,<id>`
     * clause over the `nim` entry.
     *
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'nim' => ['required', 'string', 'max:20'],
            'jurusan' => ['required', 'string', 'max:255'],
            'program_studi' => ['required', 'string', 'max:255'],
            'jenis_kelamin' => ['required', 'in:L,P'],
            'alamat' => ['required', 'string', 'max:500'],
        ];
    }
}
