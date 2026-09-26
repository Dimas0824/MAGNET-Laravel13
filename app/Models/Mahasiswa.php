<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;

class Mahasiswa extends UserBase
{
    use BelongsToTenant;

    protected $table = 'mahasiswa';

    protected $fillable = [
        'nama',
        'nim',
        'email',
        'jenis_kelamin',
        'jurusan',
        'program_studi',
        'angkatan',
        'tanggal_lahir',
        'alamat',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'jenis_kelamin' => 'string',
        'status_magang' => 'string',
    ];

    public function getRoleName(): string
    {
        return 'mahasiswa';
    }

    public function berkasPengajuanMagang()
    {
        return $this->hasMany(BerkasPengajuanMagang::class);
    }

    public function kontrakMagang()
    {
        return $this->hasMany(KontrakMagang::class);
    }

    public function kriteriaPekerjaan()
    {
        return $this->hasOne(KriteriaPekerjaan::class);
    }

    public function kriteriaBidangIndustri()
    {
        return $this->hasOne(KriteriaBidangIndustri::class);
    }

    public function kriteriaLokasiMagang()
    {
        return $this->hasOne(KriteriaLokasiMagang::class);
    }

    public function kriteriaJenisMagang()
    {
        return $this->hasOne(KriteriaJenisMagang::class);
    }

    public function kriteriaOpenRemote()
    {
        return $this->hasOne(KriteriaOpenRemote::class);
    }

    public function encodedAlternatives()
    {
        return $this->hasMany(EncodedAlternatives::class);
    }
}
