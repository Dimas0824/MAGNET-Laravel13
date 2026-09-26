<?php

use App\Models\Tenant;
use Database\Seeders\TenantSeeder;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    seedMasterData();
});

it('has a tenants table with a unique slug and public_id', function () {
    expect(Schema::hasTable('tenants'))->toBeTrue()
        ->and(Schema::hasColumns('tenants', ['id', 'name', 'slug', 'public_id']))->toBeTrue();
});

it('creates a tenant via the model', function () {
    $tenant = Tenant::create([
        'name' => 'Polinema',
        'slug' => 'polinema',
        'public_id' => '01HZZZZZZZZZZZZZZZZZZZZZZZ',
    ]);

    expect($tenant->id)->not->toBeNull()
        ->and($tenant->slug)->toBe('polinema')
        ->and(strlen($tenant->public_id))->toBe(26);
});

it('rejects a duplicate slug', function () {
    Tenant::create(['name' => 'A', 'slug' => 'dup', 'public_id' => '01HZZZZZZZZZZZZZZZZZZZZZZA']);

    expect(fn () => Tenant::create(['name' => 'B', 'slug' => 'dup', 'public_id' => '01HZZZZZZZZZZZZZZZZZZZZZZB']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('seeds exactly one default tenant and is idempotent', function () {
    (new TenantSeeder)->run();
    (new TenantSeeder)->run();

    expect(Tenant::where('slug', 'default')->count())->toBe(1);
});
