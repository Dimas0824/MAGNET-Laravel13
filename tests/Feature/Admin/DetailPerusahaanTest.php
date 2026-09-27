<?php

use App\Models\Admin;
use App\Models\LokasiMagang;
use App\Models\Perusahaan;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;

beforeEach(function () {
    seedMasterData();
});

it('renders the perusahaan detail with lokasi options and the current lokasi name', function () {
    $admin = Admin::factory()->create();
    actingAsAdmin($admin);

    $lokasi = LokasiMagang::where('kategori_lokasi', 'Area Malang Raya')
        ->where('lokasi', 'Lowokwaru, Kota Malang, Jawa Timur')
        ->firstOrFail();

    $perusahaan = Perusahaan::factory()->create([
        'nama' => 'PT Nusantara Teknologi',
        'lokasi_magang_id' => $lokasi->id,
    ]);

    // Read-only view: the current lokasi name is resolved once and rendered.
    $this->get(route('admin.detail-perusahaan', $perusahaan->id))
        ->assertOk()
        ->assertSee($lokasi->lokasi, false);

    // Edit view: the lokasi options are resolved once and rendered as labels.
    $optionLabel = $lokasi->kategori_lokasi." \u{2014} ".$lokasi->lokasi;

    Volt::test('pages.admin.kelola-data-master.detail-perusahaan', ['id' => $perusahaan->id])
        ->call('editData')
        ->assertSee($optionLabel, false);
});

it('stays within the query budget', function () {
    $admin = Admin::factory()->create();
    actingAsAdmin($admin);

    $lokasi = LokasiMagang::where('kategori_lokasi', 'Area Malang Raya')->firstOrFail();

    $perusahaan = Perusahaan::factory()->create([
        'lokasi_magang_id' => $lokasi->id,
    ]);

    // Toggling into edit mode re-renders the component. The lokasi lookups
    // (options list + current name) are resolved at mount/state and must NOT
    // be re-run as fresh queries on that subsequent render.
    DB::enableQueryLog();
    Volt::test('pages.admin.kelola-data-master.detail-perusahaan', ['id' => $perusahaan->id])
        ->call('editData')
        ->assertOk();
    $log = collect(DB::getQueryLog());
    DB::disableQueryLog();

    $lokasiReads = $log->filter(fn (array $entry) => str_contains(strtolower($entry['query']), 'lokasi_magang'));

    // Mount/resolve-once does the lokasi work (options list + current name);
    // the edit-mode re-render must not add any fresh lokasi reads.
    expect($lokasiReads->count())->toBeLessThanOrEqual(2);
});
