<?php

use App\Models\Admin;
use App\Models\BerkasPengajuanMagang;
use App\Models\Chat;
use App\Models\DosenPembimbing;
use App\Models\FormPengajuanMagang;
use App\Models\KontrakMagang;
use App\Models\LogMagang;
use App\Models\Mahasiswa;
use App\Models\Perusahaan;
use App\Models\UlasanMagang;
use App\Models\UmpanBalikMagang;
use Illuminate\Support\Carbon;

beforeEach(function () {
    seedMasterData();
});

it('exposes the role name on each user model', function () {
    expect((new Mahasiswa)->getRoleName())->toBe('mahasiswa')
        ->and((new DosenPembimbing)->getRoleName())->toBe('dosen')
        ->and((new Admin)->getRoleName())->toBe('admin');
});

it('resolves the mahasiswa criteria preference relations', function () {
    $mahasiswa = mahasiswaDenganPreferensi();

    expect($mahasiswa->kriteriaPekerjaan)->not->toBeNull()
        ->and($mahasiswa->kriteriaBidangIndustri)->not->toBeNull()
        ->and($mahasiswa->kriteriaLokasiMagang)->not->toBeNull()
        ->and($mahasiswa->kriteriaJenisMagang)->not->toBeNull()
        ->and($mahasiswa->kriteriaOpenRemote)->not->toBeNull();
});

it('resolves a lowongan magang with company, job and location', function () {
    $lowongan = lowonganMagang();

    expect($lowongan->perusahaan)->toBeInstanceOf(Perusahaan::class)
        ->and($lowongan->pekerjaan)->not->toBeNull()
        ->and($lowongan->lokasiMagang)->not->toBeNull();
});

it('links a kontrak magang to mahasiswa, dosen and openings', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $dosen = DosenPembimbing::factory()->create();
    $lowongan = lowonganMagang();

    $kontrak = KontrakMagang::create([
        'mahasiswa_id' => $mahasiswa->id,
        'dosen_id' => $dosen->id,
        'lowongan_magang_id' => $lowongan->id,
        'waktu_awal' => now(),
        'waktu_akhir' => now()->addMonths(3),
    ]);

    expect($kontrak->mahasiswa->id)->toBe($mahasiswa->id)
        ->and($kontrak->dosenPembimbing->id)->toBe($dosen->id)
        ->and($kontrak->lowonganMagang->id)->toBe($lowongan->id)
        ->and($kontrak->waktu_awal)->toBeInstanceOf(Carbon::class);
});

it('cascades logs, ulasan, umpan balik and chats under a kontrak', function () {
    $kontrak = KontrakMagang::factory()->create();

    LogMagang::create([
        'kontrak_magang_id' => $kontrak->id,
        'kegiatan' => 'Mengerjakan fitur',
        'tanggal' => now()->toDateString(),
        'jam_masuk' => '08:00:00',
        'jam_keluar' => '17:00:00',
    ]);

    UlasanMagang::create([
        'kontrak_magang_id' => $kontrak->id,
        'rating' => 5,
        'komentar' => 'Bagus',
    ]);

    UmpanBalikMagang::create([
        'kontrak_magang_id' => $kontrak->id,
        'komentar' => 'Tingkatkan',
        'tanggal' => now()->toDateString(),
    ]);

    Chat::create([
        'kontrak_magang_id' => $kontrak->id,
        'sender_id' => $kontrak->mahasiswa_id,
        'receiver_id' => $kontrak->dosen_id,
        'message' => 'Halo',
    ]);

    expect($kontrak->logMagang)->toHaveCount(1)
        ->and($kontrak->ulasanMagang)->not->toBeNull()
        ->and($kontrak->umpanBalikMagang)->toHaveCount(1)
        ->and($kontrak->chats)->toHaveCount(1);
});

it('links a berkas pengajuan to its form with the correct status cast', function () {
    $mahasiswa = Mahasiswa::factory()->create();

    $berkas = BerkasPengajuanMagang::create([
        'mahasiswa_id' => $mahasiswa->id,
        'cv' => 'cv.pdf',
        'transkrip_nilai' => 'transkrip.pdf',
        'portfolio' => null,
    ]);

    FormPengajuanMagang::forceCreate([
        'pengajuan_id' => $berkas->id,
        'status' => 'diproses',
        'keterangan' => 'sedang review',
    ]);

    expect($berkas->formPengajuanMagang->status)->toBe('diproses');
});

it('hides the password attribute on serialization', function () {
    $mahasiswa = Mahasiswa::factory()->create();

    expect($mahasiswa->toArray())->not->toHaveKey('password');
});

it('identifies chat sender as mahasiswa when sender_id matches the kontrak', function () {
    $kontrak = KontrakMagang::factory()->create();

    $chat = Chat::create([
        'kontrak_magang_id' => $kontrak->id,
        'sender_id' => $kontrak->mahasiswa_id,
        'receiver_id' => $kontrak->dosen_id,
        'message' => 'Hi',
    ]);

    expect($chat->isSentByMahasiswa())->toBeTrue()
        ->and($chat->isSentByDosen())->toBeFalse();
});
