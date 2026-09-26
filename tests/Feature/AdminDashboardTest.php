<?php

use App\Models\Admin;
use App\Models\FormPengajuanMagang;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    seedMasterData();
});

it('renders the admin dashboard', function () {
    $admin = Admin::factory()->create();
    actingAsAdmin($admin);

    $this->get(route('dashboard'))->assertOk();
});

it('collapses the pengajuan status counts into one grouped query', function () {
    $admin = Admin::factory()->create();
    actingAsAdmin($admin);

    DB::enableQueryLog();
    $this->get(route('dashboard'))->assertOk();
    $log = collect(DB::getQueryLog());
    DB::disableQueryLog();

    // The three per-status counts must not be three separate table scans.
    $statusCounts = $log->filter(function (array $entry) {
        $sql = strtolower($entry['query']);

        return str_contains($sql, 'form_pengajuan_magang') && str_contains($sql, 'count(');
    });

    expect($statusCounts->count())->toBeLessThanOrEqual(1);
});
