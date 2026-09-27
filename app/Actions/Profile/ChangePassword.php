<?php

namespace App\Actions\Profile;

use App\Actions\Action;
use App\Models\Mahasiswa;
use Illuminate\Support\Facades\Hash;

/**
 * W4-T01 — change a mahasiswa's password after verifying the current one.
 *
 * Extracted verbatim from the `$saveNewPassword` closure in
 * resources/views/pages/mahasiswa/profile.blade.php: verify the supplied current
 * password against the stored hash, then force-write the re-hashed new password
 * (bypassing mass-assignment protection, exactly as the page did) with an
 * explicit `updated_at => now()`.
 *
 * Returns `false` when the current password does not match so the caller can
 * show its "Password Lama Salah" modal and return early — matching the page's
 * `if (!Hash::check(...)) { ...; return; }` guard. Returns `true` on success.
 *
 * Validation stays in the caller (the page uses ChangePasswordForm); this action
 * only runs once validation has already passed.
 */
class ChangePassword implements Action
{
    public function handle(?Mahasiswa $mahasiswa = null, string $currentPassword = '', string $newPassword = ''): bool
    {
        if (! $mahasiswa) {
            return false;
        }

        // Verify current password.
        if (! Hash::check($currentPassword, $mahasiswa->password)) {
            return false;
        }

        // Update password.
        $mahasiswa->forceFill([
            'password' => Hash::make($newPassword),
            'updated_at' => now(),
        ])->save();

        return true;
    }
}
