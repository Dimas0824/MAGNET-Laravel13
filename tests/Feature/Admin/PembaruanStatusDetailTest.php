<?php

use App\Models\Admin;
use App\Models\DosenPembimbing;
use App\Models\KontrakMagang;
use App\Models\Mahasiswa;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    seedMasterData();
});

it('renders the kontrak detail page with the dosen options and status badge', function () {
    $admin = Admin::factory()->create();
    actingAsAdmin($admin);

    $mahasiswa = Mahasiswa::factory()->create();

    $dosenA = DosenPembimbing::factory()->create(['nama' => 'Dr. Alpha Pembimbing']);
    $dosenB = DosenPembimbing::factory()->create(['nama' => 'Dr. Beta Pembimbing']);

    $kontrak = KontrakMagang::factory()->create([
        'mahasiswa_id' => $mahasiswa->id,
        'dosen_id' => $dosenA->id,
        'status' => 'menunggu_persetujuan',
    ]);

    $response = $this->get(route('admin.detail-pengajuan-pembaruan-status-magang', $mahasiswa->id));

    $response->assertOk();

    // Dosen options must be rendered in the approval select.
    $response->assertSee($dosenA->nama, false);
    $response->assertSee($dosenB->nama, false);

    // Status badge text for the seeded status.
    $response->assertSee('Menunggu Persetujuan', false);
});

it('stays within the query budget', function () {
    $admin = Admin::factory()->create();
    actingAsAdmin($admin);

    $mahasiswa = Mahasiswa::factory()->create();

    $dosenA = DosenPembimbing::factory()->create(['nama' => 'Dr. Alpha Pembimbing']);
    DosenPembimbing::factory()->create(['nama' => 'Dr. Beta Pembimbing']);

    KontrakMagang::factory()->create([
        'mahasiswa_id' => $mahasiswa->id,
        'dosen_id' => $dosenA->id,
        'status' => 'menunggu_persetujuan',
    ]);

    DB::enableQueryLog();
    $html = $this->get(route('admin.detail-pengajuan-pembaruan-status-magang', $mahasiswa->id))
        ->assertOk()
        ->getContent();
    $log = collect(DB::getQueryLog());
    DB::disableQueryLog();

    // The dosen options list must be resolved exactly once (now at mount),
    // never re-issued as a render-time read.
    $listReads = $log->filter(
        fn (array $entry) => str_contains($entry['query'], '`nama`, `nidn`')
    );
    expect($listReads->count())->toBe(1);

    // The whole read set stays within budget.
    $dosenReads = $log->filter(
        fn (array $entry) => str_contains(strtolower($entry['query']), 'dosen_pembimbing')
    );
    expect($dosenReads->count())->toBeLessThanOrEqual(3);

    // The options are carried in component state (mount), so a re-render does
    // not need to read them back from the database.
    expect($html)->toContain('dosenList');
});
