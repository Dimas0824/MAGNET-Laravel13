<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pekerjaan extends Model
{
    use HasFactory;

    protected $table = 'pekerjaan';

    protected $fillable = [
        'nama',
    ];

    public function kriteriaPekerjaan()
    {
        return $this->hasMany(KriteriaPekerjaan::class);
    }

    public function lowonganMagang()
    {
        return $this->hasMany(LowonganMagang::class);
    }
}
