<?php

namespace App\Models;

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
