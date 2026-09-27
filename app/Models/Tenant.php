<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tenant registry row. Single tenant today; the model exists so domain rows can
 * carry a real `tenant_id` FK and a global scope can isolate them (ADR 03).
 *
 * Not itself tenant-scoped (it IS the tenant).
 */
class Tenant extends Model
{
    use HasFactory;

    protected $table = 'tenants';

    protected $fillable = [
        'name',
        'slug',
        'public_id',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
