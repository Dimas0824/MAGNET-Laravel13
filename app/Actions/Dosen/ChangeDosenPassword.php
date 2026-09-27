<?php

namespace App\Actions\Dosen;

use App\Actions\Action;
use App\Models\DosenPembimbing;
use Illuminate\Support\Facades\Hash;

/**
 * W4-T03 — change a dosen's password after verifying the current one.
 *
 * Extracted verbatim from the `$saveNewPassword` closure in
 * resources/views/pages/dosen/profile.blade.php: verify the supplied current
 * password against the stored hash, then force-write the re-hashed new password
 * (bypassing mass-assignment protection, exactly as the page did).
 *
 * Returns `false` when the current password does not match so the caller can
 * show its "Password Lama Salah" modal and return early — matching the page's
 * `if (!Hash::check(...)) { ...; return; }` guard. Returns `true` on success.
 *
 * Validation (`Password::min(8)->mixedCase()->numbers()` + `confirmed`) stays in
 * the page's own `$this->validate([...])` so the error bag / modal behavior is
 * unchanged.
 */
class ChangeDosenPassword implements Action
{
    public function handle(?DosenPembimbing $dosen = null, string $currentPassword = '', string $newPassword = ''): bool
    {
        if (! $dosen) {
            return false;
        }

        // Verify current password.
        if (! Hash::check($currentPassword, $dosen->password)) {
            return false;
        }

        // Update password.
        $dosen->forceFill([
            'password' => Hash::make($newPassword),
            'updated_at' => now(),
        ])->save();

        return true;
    }
}
