<?php

namespace App\Observers;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Generic audit observer: attaches to any model using the `Auditable` trait and
 * writes created/updated/deleted audit rows with old/new values.
 */
class AuditObserver
{
    public function created(Model $model): void
    {
        if ($this->audits($model)) {
            /** @var Model&Auditable $model */
            $model->writeAudit('created', null, $model->getAttributes());
        }
    }

    public function updated(Model $model): void
    {
        if (! $this->audits($model)) {
            return;
        }

        $changed = array_keys($model->getChanges());
        $old = [];
        $new = [];

        foreach ($changed as $key) {
            if ($key === 'updated_at') {
                continue;
            }
            $old[$key] = $model->getOriginal($key);
            $new[$key] = $model->getAttribute($key);
        }

        if ($old === [] && $new === []) {
            return;
        }

        /** @var Model&Auditable $model */
        $model->writeAudit('updated', $old, $new);
    }

    public function deleted(Model $model): void
    {
        if ($this->audits($model)) {
            /** @var Model&Auditable $model */
            $model->writeAudit('deleted', $model->getOriginal(), null);
        }
    }

    private function audits(Model $model): bool
    {
        return in_array(Auditable::class, class_uses_recursive($model), true);
    }
}
