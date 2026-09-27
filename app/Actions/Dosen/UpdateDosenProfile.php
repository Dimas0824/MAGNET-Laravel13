<?php

namespace App\Actions\Dosen;

use App\Actions\Action;
use App\Models\DosenPembimbing;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * W4-T03 — persist a dosen's personal data (nama/nidn/jenis_kelamin) and,
 * optionally, a new profile photo.
 *
 * Extracted verbatim from the `$savePersonalData` closure in
 * resources/views/pages/dosen/profile.blade.php: the photo upload block
 * (store to the `public` disk under `foto_dosen/`, delete the previous file,
 * expose the authored filename) and the `$dosen->update([...])` write.
 *
 * Validation is intentionally NOT performed here: the page keeps calling its
 * own `$this->validate([...])` so the `unique:dosen,nidn,<id>` rule (which
 * references a non-existent `dosen` table and therefore throws) and the
 * resulting modal/error-bag behavior stay byte-for-byte identical. This action
 * only runs once validation has already passed.
 *
 * The caller owns the modal text, the `isUpdatePersonalData` flag and the
 * `foto` reset; this action only performs the domain write + photo side effects
 * and returns the authored filename (or null when no photo was uploaded).
 */
class UpdateDosenProfile implements Action
{
    /**
     * @param  array{nama?: mixed, nidn?: mixed, jenis_kelamin?: mixed}  $data  already-validated personal data
     *
     * @return string|null  the authored photo filename (for the caller's preview), or null when none uploaded
     */
    public function handle(?DosenPembimbing $dosen = null, array $data = [], ?UploadedFile $foto = null): ?string
    {
        if (! $dosen) {
            return null;
        }

        $updateData = [
            'nama' => $data['nama'],
            'nidn' => $data['nidn'],
            'jenis_kelamin' => $data['jenis_kelamin'],
            'updated_at' => now(),
        ];

        $filename = null;

        if ($foto) {
            // Delete old photo if exists.
            if ($dosen->foto && Storage::disk('public')->exists('foto_dosen/' . $dosen->foto)) {
                Storage::disk('public')->delete('foto_dosen/' . $dosen->foto);
            }

            // Store new photo.
            $filename = time() . '_' . $foto->getClientOriginalName();
            $foto->storeAs('foto_dosen', $filename, 'public');
            $updateData['foto'] = $filename;
        }

        $dosen->update($updateData);

        return $filename;
    }
}
