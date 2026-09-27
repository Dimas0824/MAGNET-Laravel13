<?php

namespace App\Livewire\Forms;

use Livewire\Attributes\Validate;
use Livewire\Form;

/**
 * W0-T06d — internship-review ("ulasan magang") validation extracted from the
 * Volt completion component.
 *
 * The rules below are a 1:1 copy of the review rules in:
 *   resources/views/components/mahasiswa/pembaruan-status-magang/
 *       selesai-magang.blade.php
 *     - rules([...])            L22-23
 *     - inline validate([...])  L96-97  (identical copy)
 *
 * Source field names (review_rating / review_komentar) are exposed here under
 * their domain names (rating / komentar), matching the UlasanMagang model.
 *
 * Keep them in sync until the component is rewired to consume this form object
 * (later task W2-T02).
 *
 * This object ONLY validates and holds data — persistence (UlasanMagang
 * create/update + the file upload) stays in a later Action task.
 */
class UlasanMagangForm extends Form
{
    // rating: required|integer|min:1|max:5  (selesai-magang L22 / L96)
    // Untyped on purpose so the `integer` rule (not a PHP TypeError) rejects
    // non-integer input, matching the source's validation-driven behaviour.
    #[Validate(['required', 'integer', 'min:1', 'max:5'])]
    public $rating = null;

    // komentar: required|string|min:10|max:500  (selesai-magang L23 / L97)
    #[Validate(['required', 'string', 'min:10', 'max:500'])]
    public string $komentar = '';

    /**
     * Hydrate the form from a plain array (e.g. a request payload or the
     * component's state). Unknown keys are ignored on purpose.
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
