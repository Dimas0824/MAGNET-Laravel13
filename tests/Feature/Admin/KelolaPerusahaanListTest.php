<?php

use App\Models\Admin;
use App\Models\Perusahaan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    seedMasterData();
});

it('renders the perusahaan list with the first row and lokasi options', function () {
    $admin = Admin::factory()->create();
    actingAsAdmin($admin);

    $perusahaan = Perusahaan::factory()->create(['nama' => 'PT Magnet Nusantara']);

    $this->get(route('admin.data-perusahaan'))
        ->assertOk()
        ->assertSee('PT Magnet Nusantara')
        ->assertSee('Area Malang Raya — Lowokwaru, Kota Malang, Jawa Timur');
});

it('stays within the query budget', function () {
    $admin = Admin::factory()->create();
    actingAsAdmin($admin);

    Perusahaan::factory()->create(['nama' => 'PT Magnet Nusantara']);

    DB::enableQueryLog();
    $this->get(route('admin.data-perusahaan'))->assertOk();
    $log = collect(DB::getQueryLog());
    DB::disableQueryLog();

    // Measured baseline before the refactor: the render-time lokasi query
    // pushed this page to 10 queries. The budget locks in "no worse".
    expect($log->count())->toBeLessThanOrEqual(10);
});
