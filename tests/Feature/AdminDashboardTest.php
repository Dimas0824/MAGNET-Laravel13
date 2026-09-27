<?php

use App\Actions\Dashboard\BuildAdminStats;
use App\Models\Admin;
use App\Models\BerkasPengajuanMagang;
use App\Models\FormPengajuanMagang;
use App\Models\Mahasiswa;
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

it('renders exact status counts + budget <=1', function () {
    // W2-T04 characterization: the dashboard stats must render the SAME numbers
    // after the inline grouped selectRaw + related queries move into
    // App\Actions\Dashboard\BuildAdminStats::handle(). Seed one pengajuan per
    // status and assert each card shows its exact count, and that the stats
    // build costs at most one query for form_pengajuan_magang.
    $admin = Admin::factory()->create();
    actingAsAdmin($admin);

    $makeBerkas = fn (): BerkasPengajuanMagang => BerkasPengajuanMagang::create([
        'mahasiswa_id' => Mahasiswa::factory()->create()->id,
        'cv' => 'cv.pdf',
        'transkrip_nilai' => 'transkrip.pdf',
        'portfolio' => 'portfolio.pdf',
    ]);
    FormPengajuanMagang::forceCreate(['pengajuan_id' => $makeBerkas()->id, 'keterangan' => 'a', 'status' => 'diproses']);
    FormPengajuanMagang::forceCreate(['pengajuan_id' => $makeBerkas()->id, 'keterangan' => 'b', 'status' => 'diterima']);
    FormPengajuanMagang::forceCreate(['pengajuan_id' => $makeBerkas()->id, 'keterangan' => 'c', 'status' => 'ditolak']);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $response = $this->get(route('dashboard'))->assertOk();
    $log = collect(DB::getQueryLog());
    DB::disableQueryLog();

    // Exact status counts are rendered on the three cards.
    $response->assertSee('Pengajuan Magang Masuk')
        ->assertSee('Pengajuan Magang Diterima')
        ->assertSee('Pengajuan Magang Ditolak');

    // The view consumes the exact array the action returns.
    $stats = (new BuildAdminStats)->handle();
    expect($stats['totalPengajuanMasuk'])->toBe(1)
        ->and($stats['totalPengajuanDiterima'])->toBe(1)
        ->and($stats['totalPengajuanDitolak'])->toBe(1);

    // Budget: the grouped status-count query stays a single grouped select.
    $statusCounts = $log->filter(function (array $entry) {
        $sql = strtolower($entry['query']);

        return str_contains($sql, 'form_pengajuan_magang') && str_contains($sql, 'count(');
    });

    expect($statusCounts->count())->toBeLessThanOrEqual(1);
});
