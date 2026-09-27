<?php

use App\Livewire\Forms\UlasanMagangForm;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/*
|--------------------------------------------------------------------------
| UlasanMagangForm — validation parity with the internship-review block
|--------------------------------------------------------------------------
|
| W0-T06d: the internship-review ("ulasan magang") validation is being
| extracted into a Livewire form object from the Volt completion component:
|
|   resources/views/components/mahasiswa/pembaruan-status-magang/
|       selesai-magang.blade.php
|
| Source rules:
|   - rules([...])          L20-24
|   - inline $this->validate L94-98 (identical copy)
|
| The review fields are:
|   - review_rating    => required|integer|min:1|max:5
|   - review_komentar  => required|string|min:10|max:500
|
| The form exposes them under their domain names (`rating`, `komentar`).
| This suite pins the EXACT rules so the component and the form can never
| drift apart. No database access is required, so this stays a pure Unit test.
|
*/

/**
 * A bare Livewire component used purely as the host for the form object.
 * Livewire form objects are validated through their owning component, so a
 * throwaway host lets us exercise UlasanMagangForm in isolation without wiring
 * up the real Volt component (that is a later task, W2-T02).
 */
class UlasanMagangFormHost extends Component
{
    public UlasanMagangForm $form;

    public function render()
    {
        return <<<'HTML'
        <div></div>
        HTML;
    }
}

/**
 * Return a complete, valid payload mirroring the review happy path.
 */
function validUlasanMagangPayload(): array
{
    return [
        'rating' => 5,
        'komentar' => 'Pengalaman magang yang sangat berkesan dan banyak ilmu baru.',
    ];
}

/**
 * Build the form inside its host component through the Livewire test harness.
 */
function ulasanMagangForm(): UlasanMagangForm
{
    return Livewire::test(UlasanMagangFormHost::class)->instance()->form;
}

/*
|--------------------------------------------------------------------------
| Green path
|--------------------------------------------------------------------------
*/

it('passes valid review data', function () {
    $form = ulasanMagangForm();
    $form->fillFrom(validUlasanMagangPayload());

    // Should not throw...
    $validated = $form->validate();

    expect($validated)->toBeArray()
        ->and($validated['rating'])->toBe(5)
        ->and($validated['komentar'])->toBe('Pengalaman magang yang sangat berkesan dan banyak ilmu baru.');
});

/*
|--------------------------------------------------------------------------
| Bounded rating: required|integer|min:1|max:5
|--------------------------------------------------------------------------
*/

it('fails when rating is out of range', function () {
    // min:1
    $form = ulasanMagangForm();
    $form->fillFrom([...validUlasanMagangPayload(), 'rating' => 0]);
    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class, null, 'rating min:1 failed');

    // max:5
    $form = ulasanMagangForm();
    $form->fillFrom([...validUlasanMagangPayload(), 'rating' => 6]);
    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class, null, 'rating max:5 failed');
});

/*
|--------------------------------------------------------------------------
| Required comment: required|string|min:10|max:500
|--------------------------------------------------------------------------
*/

it('fails when komentar is empty', function () {
    $form = ulasanMagangForm();
    $form->fillFrom([...validUlasanMagangPayload(), 'komentar' => '']);

    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class);
});

/*
|--------------------------------------------------------------------------
| Full rule parity with the component
|--------------------------------------------------------------------------
|
| One assertion per rule copied from the component `rules([...])` block.
|
*/

it('enforces the same rules as the component', function () {
    // rating: required|integer|min:1|max:5
    $form = ulasanMagangForm();
    $form->fillFrom([...validUlasanMagangPayload(), 'rating' => 'not-an-integer']);
    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class, null, 'rating integer failed');

    // komentar: required|string|min:10|max:500
    $form = ulasanMagangForm();
    $form->fillFrom([...validUlasanMagangPayload(), 'komentar' => 'pendek']);
    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class, null, 'komentar min:10 failed');

    $form = ulasanMagangForm();
    $form->fillFrom([...validUlasanMagangPayload(), 'komentar' => str_repeat('a', 501)]);
    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class, null, 'komentar max:500 failed');
});
