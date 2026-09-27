<?php

use App\Events\MahasiswaPreferenceUpdated;
use App\Listeners\RunRecommendationPipeline;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Mahasiswa;
use App\Models\Tenant;
use Database\Seeders\TenantSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Queue;

/**
 * Queued pipeline work must run under the SAME tenant that queued it. On a
 * worker process there is no request to resolve a tenant, so the event has to
 * carry the acting tenant id and the listener has to restore it before the
 * pipeline touches scoped models — otherwise a non-default tenant's job would
 * run as the default tenant and (strict scope) see none of its own rows.
 */
beforeEach(function () {
    seedMasterData();
    (new TenantSeeder)->run();
});

it('captures the acting tenant id on the event', function () {
    $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'public_id' => '01HZZZZZZZZZZZZZZZZZZZZZZT']);
    $mahasiswa = Mahasiswa::factory()->create(['tenant_id' => $other->id]);

    app()->instance('currentTenant', $other);

    $event = new MahasiswaPreferenceUpdated($mahasiswa);

    expect($event->tenantId)->toBe($other->id);
});

it('carries the tenant id through the queued listener payload', function () {
    $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'public_id' => '01HZZZZZZZZZZZZZZZZZZZZZZR']);
    $mahasiswa = Mahasiswa::factory()->create(['tenant_id' => $other->id]);

    Queue::fake();

    app()->instance('currentTenant', $other);
    event(new MahasiswaPreferenceUpdated($mahasiswa));

    Queue::assertPushed(
        CallQueuedListener::class,
        fn ($job) => $job->class === RunRecommendationPipeline::class
    );
});

it('restores the tenant when the queued listener runs without a request', function () {
    $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'public_id' => '01HZZZZZZZZZZZZZZZZZZZZZZS']);
    $mahasiswa = Mahasiswa::factory()->create(['tenant_id' => $other->id]);

    $event = new MahasiswaPreferenceUpdated($mahasiswa, $other->id);

    // Simulate the worker: no request binding, no memo.
    app()->forgetInstance('currentTenant');
    BelongsToTenant::forgetResolvedTenant();

    $listener = new RunRecommendationPipeline;

    expect($listener)->toBeInstanceOf(ShouldQueue::class);

    // Restore the tenant from the event; assert the re-binding only.
    $listener->restoreTenant($event);

    expect(app('currentTenant')->id)->toBe($other->id)
        ->and(BelongsToTenant::currentTenant()->id)->toBe($other->id);
});
