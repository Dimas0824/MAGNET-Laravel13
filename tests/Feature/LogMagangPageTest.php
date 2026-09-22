<?php

use App\Models\KontrakMagang;
use App\Models\LogMagang;
use App\Models\Mahasiswa;

beforeEach(function () {
    seedMasterData();
});

it('renders the log-magang edit page for a given log id', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    actingAsMahasiswa($mahasiswa);

    $lowongan = lowonganMagang();
    $kontrak = KontrakMagang::factory()->create([
        'mahasiswa_id' => $mahasiswa->id,
        'lowongan_magang_id' => $lowongan->id,
    ]);

    $log = LogMagang::create([
        'kontrak_magang_id' => $kontrak->id,
        'kegiatan' => 'Mengerjakan fitur absensi',
        'tanggal' => now()->toDateString(),
        'jam_masuk' => '08:00:00',
        'jam_keluar' => '17:00:00',
    ]);

    $this->get(route('mahasiswa.log-magang', ['id' => $log->id]))->assertOk();
});

it('redirects log-magang to detail-log when no log id is provided', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    actingAsMahasiswa($mahasiswa);

    $this->get(route('mahasiswa.log-magang'))
        ->assertRedirect(route('mahasiswa.detail-log'));
});

it('renders the log-mahasiswa page without the dropped nama column', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    actingAsMahasiswa($mahasiswa);

    $lowongan = lowonganMagang();
    KontrakMagang::factory()->create([
        'mahasiswa_id' => $mahasiswa->id,
        'lowongan_magang_id' => $lowongan->id,
    ]);

    $this->get(route('mahasiswa.log-mahasiswa'))->assertOk();
});
