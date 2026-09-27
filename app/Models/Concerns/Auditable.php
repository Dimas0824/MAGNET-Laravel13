<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Hand-rolled auditing (no spatie): an observer writes one `audit_logs` row per
 * create/update/delete for a business-critical model, in the SAME transaction
 * as the change so the trail cannot drift from the data.
 *
 * Sensitive attributes are REDACTED before they reach the log.
 */
trait Auditable
{
    /**
     * Attributes never written to the audit trail.
     *
     * @return array<int, string>
     */
    protected function auditRedacted(): array
    {
        return array_merge(
            ['password', 'remember_token', 'email_verified_at'],
            $this->auditRedactedCustom ?? []
        );
    }

    /**
     * Write an audit row for this model instance.
     *
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public function writeAudit(string $event, ?array $old = null, ?array $new = null): void
    {
        $redacted = $this->auditRedacted();

        $clean = function (?array $values) use ($redacted): ?array {
            if ($values === null) {
                return null;
            }

            foreach ($redacted as $key) {
                unset($values[$key]);
            }

            return $values;
        };

        AuditLog::create([
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'event' => $event,
            'old_values' => $clean($old),
            'new_values' => $clean($new),
            'actor_user_id' => $this->auditActorUserId(),
            'actor_role' => $this->auditActorRole(),
            'ip' => app()->runningInConsole() ? null : request()->ip(),
            'user_agent' => app()->runningInConsole() ? null : substr((string) request()->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }

    protected function auditActorUserId(): ?int
    {
        foreach (['mahasiswa', 'dosen', 'admin'] as $guard) {
            $user = auth($guard)->user();
            if ($user !== null) {
                return $user->user_id;
            }
        }

        return null;
    }

    protected function auditActorRole(): ?string
    {
        foreach (['mahasiswa', 'dosen', 'admin'] as $guard) {
            if (auth($guard)->check()) {
                return $guard;
            }
        }

        return null;
    }
}
