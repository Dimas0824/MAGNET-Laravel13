<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P6-T1: `audit_logs` — a per-row change history + PII access log, hand-rolled
 * (no spatie), scoped to business-critical models + PII access.
 *
 * `auditable_type`/`auditable_id` reference the changed row; `actor_*` capture
 * WHO did it; old/new values are JSON. Indexed so "history of X" and "what did
 * user Y do" are both cheap.
 */
it('creates the audit_logs table with the expected shape', function () {
    expect(Schema::hasTable('audit_logs'))->toBeTrue();

    $cols = DB::table('information_schema.columns')
        ->select('COLUMN_NAME')
        ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
        ->where('TABLE_NAME', 'audit_logs')
        ->pluck('COLUMN_NAME')
        ->map(fn ($c) => strtolower((string) $c))
        ->all();

    foreach (['auditable_type', 'auditable_id', 'event', 'old_values', 'new_values', 'actor_user_id', 'actor_role', 'ip', 'user_agent', 'created_at'] as $col) {
        expect($cols)->toContain($col);
    }
});

it('indexes audit_logs for history and actor lookups', function () {
    $indexes = collect(DB::select('SHOW INDEX FROM `audit_logs`'))
        ->groupBy('Key_name')
        ->map(fn ($cols) => $cols->sortBy('Seq_in_index')->pluck('Column_name')->map(fn ($c) => strtolower($c))->values()->all());

    expect($indexes->contains(fn ($cols) => $cols === ['auditable_type', 'auditable_id']))->toBeTrue()
        ->and($indexes->contains(fn ($cols) => $cols === ['actor_user_id', 'created_at']))->toBeTrue();
});
