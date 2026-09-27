<?php

namespace App\Actions\Auth;

use App\Actions\Action;
use App\Models\Mahasiswa;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Register a new mahasiswa identity.
 *
 * Extracted verbatim from the register page's `$register` closure: force-create
 * the row, hash the password, fire Registered, log the mahasiswa guard in. The
 * caller (the Volt page) owns the redirect + flash; this action only performs
 * the domain write + side effects.
 */
class RegisterMahasiswa implements Action
{
    /**
     * @param  array<string, mixed>  $data  validated registration payload
     */
    public function handle(array $data = []): Mahasiswa
    {
        $mahasiswa = Mahasiswa::forceCreate([
            'nama' => $data['nama'],
            'nim' => $data['nim'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'jurusan' => $data['jurusan'] ?? 'Teknologi Informasi',
            'program_studi' => $data['program_studi'],
            'angkatan' => $data['angkatan'],
            'jenis_kelamin' => $data['jenis_kelamin'],
            'tanggal_lahir' => $data['tanggal_lahir'],
            'alamat' => $data['alamat'],
        ]);

        event(new Registered($mahasiswa));

        Auth::guard('mahasiswa')->login($mahasiswa);

        return $mahasiswa;
    }
}
