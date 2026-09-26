<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * One audit entry: a per-row change (created/updated/deleted) or a PII access.
 *
 * Append-only; never updated or deleted by application code (retention prunes
 * old rows in bulk).
 */
class AuditLog extends Model
{
    use MassPrunable;

    public const EVENT_CREATED = 'created';
    public const EVENT_UPDATED = 'updated';
    public const EVENT_DELETED = 'deleted';
    public const EVENT_ACCESSED = 'accessed';

    /** Retention window: audit rows are pruned after 730 days. */
    public const RETENTION_DAYS = 730;

    protected $table = 'audit_logs';

    public $timestamps = false;

    /**
     * Rows eligible for pruning: older than the retention window.
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<=', now()->subDays(self::RETENTION_DAYS));
    }

    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'event',
        'old_values',
        'new_values',
        'actor_user_id',
        'actor_role',
        'ip',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function auditable()
    {
        return $this->morphTo();
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
