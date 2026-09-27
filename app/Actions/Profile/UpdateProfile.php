<?php

namespace App\Actions\Profile;

use App\Actions\Action;
use App\Models\Mahasiswa;

/**
 * W4-T01 — persist a mahasiswa's personal data
 * (nama/nim/jurusan/program_studi/jenis_kelamin/alamat).
 *
 * Extracted verbatim from the `$savePersonalData` closure in
 * resources/views/pages/mahasiswa/profile.blade.php: the `$mahasiswa->update([...])`
 * write, including the explicit `updated_at => now()` stamped on the payload.
 *
 * Validation stays in the caller (the page uses UpdateProfileForm); this action
 * only runs once validation has already passed and performs the domain write.
 */
class UpdateProfile implements Action
{
    /**
     * @param  array{nama: mixed, nim: mixed, jurusan: mixed, program_studi: mixed, jenis_kelamin: mixed, alamat: mixed}  $data  already-validated personal data
     */
    public function handle(?Mahasiswa $mahasiswa = null, array $data = []): mixed
    {
        if (! $mahasiswa) {
            return null;
        }

        $mahasiswa->update([
            'nama' => $data['nama'],
            'nim' => $data['nim'],
            'jurusan' => $data['jurusan'],
            'program_studi' => $data['program_studi'],
            'jenis_kelamin' => $data['jenis_kelamin'],
            'alamat' => $data['alamat'],
            'updated_at' => now(),
        ]);

        return null;
    }
}
