<?php

use App\Livewire\Forms\ChangePasswordForm;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/*
|--------------------------------------------------------------------------
| ChangePasswordForm — validation parity with the profile page
|--------------------------------------------------------------------------
|
| W0-T06c: the password-change validation is being extracted from the Volt page
| (resources/views/pages/mahasiswa/profile.blade.php L391-395) into a Livewire
| form object. This suite pins the EXACT rules so the page and the form can
| never drift apart.
|
| This test stays db-less (plain Unit test): the copied rules carry no database
| constraints, so no transaction wrapper is needed.
|
*/

/**
 * A bare Livewire component used purely as the host for the form object.
 */
class ChangePasswordFormHost extends Component
{
    public ChangePasswordForm $form;

    public function render()
    {
        return <<<'HTML'
        <div></div>
        HTML;
    }
}

/**
 * Build the form inside its host component through the Livewire test harness.
 */
function changePasswordForm(): ChangePasswordForm
{
    return Livewire::test(ChangePasswordFormHost::class)->instance()->form;
}

/**
 * Capture the validation errors instead of letting the exception bubble, so a
 * single field can be asserted in isolation. Livewire keys form-object errors
 * with the owning component's property name (`form.new_password`), so the
 * `form.` prefix is stripped to leave the bare field names asserted below.
 */
function changePasswordErrors(ChangePasswordForm $form): MessageBag
{
    try {
        $form->validate();
    } catch (ValidationException $e) {
        $errors = new MessageBag();

        foreach ($e->validator->errors()->messages() as $key => $messages) {
            $errors->add(\Illuminate\Support\Str::after($key, 'form.'), $messages);
        }

        return $errors;
    }

    return new MessageBag();
}

/*
|--------------------------------------------------------------------------
| Green path
|--------------------------------------------------------------------------
*/

it('passes when new_password matches confirmation', function () {
    $form = changePasswordForm();
    $form->fillFrom([
        'current_password' => 'OldPassword123!',
        'new_password' => 'NewPassword123!',
        'new_password_confirmation' => 'NewPassword123!',
    ]);

    // Should not throw...
    $validated = $form->validate();

    expect($validated)->toBeArray()
        ->and($validated['new_password'])->toBe('NewPassword123!');
});

/*
|--------------------------------------------------------------------------
| Confirmation mismatch
|--------------------------------------------------------------------------
*/

it('fails when confirmation mismatches', function () {
    $form = changePasswordForm();
    $form->fillFrom([
        'current_password' => 'OldPassword123!',
        'new_password' => 'NewPassword123!',
        'new_password_confirmation' => 'Different123!',
    ]);

    $errors = changePasswordErrors($form);

    expect($errors->has('new_password'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Minimum length
|--------------------------------------------------------------------------
*/

it('enforces the min length', function () {
    $form = changePasswordForm();
    $form->fillFrom([
        'current_password' => 'OldPassword123!',
        'new_password' => 'short',
        'new_password_confirmation' => 'short',
    ]);

    $errors = changePasswordErrors($form);

    expect($errors->has('new_password'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Full rule parity with the page (profile.blade.php L391-395)
|--------------------------------------------------------------------------
*/

it('enforces the same rules as the page', function () {
    // current_password: required
    $form = changePasswordForm();
    $form->fillFrom([
        'current_password' => '',
        'new_password' => 'NewPassword123!',
        'new_password_confirmation' => 'NewPassword123!',
    ]);
    expect(changePasswordErrors($form)->has('current_password'))->toBeTrue();

    // new_password: required
    $form = changePasswordForm();
    $form->fillFrom([
        'current_password' => 'OldPassword123!',
        'new_password' => '',
        'new_password_confirmation' => '',
    ]);
    expect(changePasswordErrors($form)->has('new_password'))->toBeTrue();

    // new_password_confirmation: required
    $form = changePasswordForm();
    $form->fillFrom([
        'current_password' => 'OldPassword123!',
        'new_password' => 'NewPassword123!',
        'new_password_confirmation' => '',
    ]);
    expect(changePasswordErrors($form)->has('new_password_confirmation'))->toBeTrue();
});
