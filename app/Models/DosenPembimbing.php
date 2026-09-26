<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;

class DosenPembimbing extends UserBase
{
    use BelongsToTenant;

    protected $table = 'dosen_pembimbing';

    protected $fillable = [
        'tenant_id',
        'user_id',
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

    /**
     * The registry identity backing this dosen row.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function kontrakMagang()
    {
        return $this->hasMany(KontrakMagang::class, 'dosen_id');
    }
}
