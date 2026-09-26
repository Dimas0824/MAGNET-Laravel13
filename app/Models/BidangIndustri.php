<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class BidangIndustri extends Model
{
    use BelongsToTenant;

    protected $table = 'bidang_industri';

    protected $fillable = [
        'nama',
    ];

    public function perusahaan()
    {
        return $this->hasMany(Perusahaan::class);
    }
}
