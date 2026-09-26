<?php

use App\Models\AuditLog;
use App\Models\KontrakMagang;

/**
 * P6-T2: business-critical models are audited automatically via a hand-rolled
 * `Auditable` trait + observer. Each create/update/delete writes an audit row
 * with the changed values; sensitive fields (password) are REDACTED.
 */
beforeEach(function () {
    seedMasterData();
});

it('records an audit row when a business-critical row is updated', function () {
    $kontrak = KontrakMagang::factory()->create();

    AuditLog::query()->delete();

    $kontrak->update(['status' => 'disetujui']);

    $log = AuditLog::where('auditable_type', KontrakMagang::class)
        ->where('auditable_id', $kontrak->id)
        ->where('event', AuditLog::EVENT_UPDATED)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->new_values)->toHaveKey('status')
        ->and($log->new_values['status'])->toBe('disetujui');
});

it('never records a password value in the audit trail', function () {
    $mahasiswa = \App\Models\Mahasiswa::factory()->create();

    AuditLog::query()->delete();

    $mahasiswa->update(['nama' => 'Nama Baru']);

    $logs = AuditLog::where('auditable_type', \App\Models\Mahasiswa::class)
        ->where('auditable_id', $mahasiswa->id)
        ->get();

    foreach ($logs as $log) {
        $payload = array_merge($log->old_values ?? [], $log->new_values ?? []);
        expect($payload)->not->toHaveKey('password');
    }
});
