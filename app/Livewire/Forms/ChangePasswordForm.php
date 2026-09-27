<?php

namespace App\Livewire\Forms;

use Livewire\Attributes\Validate;
use Livewire\Form;

/**
 * W0-T06c — password-change validation extracted from the Volt profile page.
 *
 * The rules below are a 1:1 copy of `validate([...])` in
 * resources/views/pages/mahasiswa/profile.blade.php L391-395. Keep the two in
 * sync until the page is rewired to consume this form object (later task W4-T01).
 *
 * This object ONLY validates and holds data — verification (Hash::check) and
 * persistence ($mahasiswa->forceFill) stay in a later Action task.
 */
class ChangePasswordForm extends Form
{
    // current_password: required  (profile.blade.php L392)
    #[Validate(['required'])]
    public string $current_password = '';

    // new_password: required|min:8|confirmed  (profile.blade.php L393)
    #[Validate(['required', 'min:8', 'confirmed'])]
    public string $new_password = '';

    // new_password_confirmation: required  (profile.blade.php L394)
    #[Validate(['required'])]
    public string $new_password_confirmation = '';

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
     * The validation rules as a plain `field => rules` array.
     *
     * W4-T01: lets the Volt profile page validate through this form object's
     * rules WITHOUT binding it as a component form object (which would force a
     * `passwordForm.new_password` wire:model rename across the whole template
     * and risk behavior drift).
     *
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'current_password' => ['required'],
            'new_password' => ['required', 'min:8', 'confirmed'],
            'new_password_confirmation' => ['required'],
        ];
    }
}
