<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;

class Admin extends UserBase
{
    use BelongsToTenant;

    protected $table = 'admin';

    protected $fillable = [
        'nama',
        'nip',
    ];

    protected $hidden = [
        'password',
    ];

    public function getRoleName(): string
    {
        return 'admin';
    }
}
