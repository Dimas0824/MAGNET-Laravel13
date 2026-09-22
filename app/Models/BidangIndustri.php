<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BidangIndustri extends Model
{
    protected $table = 'bidang_industri';

    protected $fillable = [
        'nama',
    ];

    public function perusahaan()
    {
        return $this->hasMany(Perusahaan::class);
    }
}
