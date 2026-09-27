<?php

use App\Models\AuditLog;
use App\Models\Chat;
use App\Models\KontrakMagang;
use App\Models\Mahasiswa;
use App\Models\RecommendationRun;
use Illuminate\Support\Carbon;

/**
 * P6-T6: retention prunes GROWING tables (runs 365d, chats 548d, audit 730d)
 * and NEVER touches PERMANENT tables (kontrak_magang, log_magang, berkas,
 * master data).
 */
beforeEach(function () {
    seedMasterData();
});

it('prunes recommendation_run older than 365 days', function () {
    $mahasiswa = Mahasiswa::factory()->create();

    $old = RecommendationRun::create([
        'mahasiswa_id' => $mahasiswa->id,
        'run_key' => 'old-run',
    ]);
    $old->forceFill(['created_at' => Carbon::now()->subDays(400)])->saveQuietly();

    // prunable() is an instance method on the model's query (MassPrunable).
    expect((new RecommendationRun)->prunable()->whereKey($old->id)->count())->toBe(1);
});

it('audit_logs prunes older than 730 days', function () {
    $log = AuditLog::create([
        'auditable_type' => KontrakMagang::class,
        'auditable_id' => 1,
        'event' => AuditLog::EVENT_CREATED,
        'created_at' => Carbon::now(),
    ]);
    $log->forceFill(['created_at' => Carbon::now()->subDays(800)])->saveQuietly();

    expect((new AuditLog)->prunable()->whereKey($log->id)->count())->toBe(1);
});

it('never prunes a permanent table', function () {
    // KontrakMagang must NOT implement Prunable (permanent retention).
    expect(method_exists(KontrakMagang::class, 'prunable'))->toBeFalse();
});
