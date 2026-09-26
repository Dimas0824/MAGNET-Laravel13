<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;

class DosenPembimbing extends UserBase
{
    use BelongsToTenant;

    protected $table = 'dosen_pembimbing';

    protected $fillable = [
        'nama',
        'nidn',
        'jenis_kelamin',
        'foto',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'jenis_kelamin' => 'string',
    ];

    public function getRoleName(): string
    {
        return 'dosen';
    }

    public function kontrakMagang()
    {
        return $this->hasMany(KontrakMagang::class, 'dosen_id');
    }
}
