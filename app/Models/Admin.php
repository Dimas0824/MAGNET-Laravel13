<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy(AuditObserver::class)]
class Admin extends UserBase
{
    use BelongsToTenant, Auditable;

    protected $table = 'admin';

    protected $fillable = [
        'tenant_id',
        'user_id',
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

    /**
     * The registry identity backing this admin row.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
