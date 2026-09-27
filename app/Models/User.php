<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The identity registry: one row per mahasiswa/dosen/admin.
 *
 * NOT an auth provider (config/auth.php keeps its 3 guards/providers). Its job
 * is to give every identity a stable canonical id that cross-role references
 * (chats participants, audit actors) can point at.
 */
class User extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'users';

    protected $fillable = [
        'tenant_id',
        'role',
        'name',
        'email',
        'public_id',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function mahasiswa()
    {
        return $this->hasOne(Mahasiswa::class);
    }

    public function dosenPembimbing()
    {
        return $this->hasOne(DosenPembimbing::class);
    }

    public function admin()
    {
        return $this->hasOne(Admin::class);
    }
}
