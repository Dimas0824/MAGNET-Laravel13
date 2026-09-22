<?php

namespace App\Models;

use App\Models\UserBase;

class Admin extends UserBase
{
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
