<?php

use App\Models\BerkasPengajuanMagang;
use App\Models\Admin;
use App\Models\KontrakMagang;
use App\Models\Mahasiswa;
use App\Models\DosenPembimbing;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    seedMasterData();
    Storage::fake('private');
    Storage::fake('public');
});

function validBerkasFiles(): array
{
    return [
        'cv' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
        'transkrip_nilai' => UploadedFile::fake()->create('transkrip.pdf', 100, 'application/pdf'),
        'portfolio' => UploadedFile::fake()->create('portfolio.pdf', 100, 'application/pdf'),
    ];
}

it('stores pengajuan documents on the private disk, not the public disk', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $this->actingAs($mahasiswa, 'mahasiswa');

    $this->post(route('mahasiswa.store-pengajuan-magang'), validBerkasFiles())
        ->assertRedirect(route('mahasiswa.pengajuan-magang'));

    $berkas = BerkasPengajuanMagang::where('mahasiswa_id', $mahasiswa->id)->firstOrFail();

    Storage::disk('private')->assertExists($berkas->cv);
    Storage::disk('private')->assertExists($berkas->transkrip_nilai);
    Storage::disk('public')->assertMissing($berkas->cv);
});

it('lets the owning mahasiswa download their own document', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $this->actingAs($mahasiswa, 'mahasiswa');
    $this->post(route('mahasiswa.store-pengajuan-magang'), validBerkasFiles());

    $berkas = BerkasPengajuanMagang::where('mahasiswa_id', $mahasiswa->id)->firstOrFail();

    $this->get(route('berkas.download', ['berkas' => $berkas->id, 'type' => 'cv']))
        ->assertOk();
});

it('lets an admin download a document', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $this->actingAs($mahasiswa, 'mahasiswa');
    $this->post(route('mahasiswa.store-pengajuan-magang'), validBerkasFiles());
    $berkas = BerkasPengajuanMagang::where('mahasiswa_id', $mahasiswa->id)->firstOrFail();

    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin');

    $this->get(route('berkas.download', ['berkas' => $berkas->id, 'type' => 'cv']))
        ->assertOk();
});

it('forbids an unrelated mahasiswa from downloading another student document', function () {
    $owner = Mahasiswa::factory()->create();
    $this->actingAs($owner, 'mahasiswa');
    $this->post(route('mahasiswa.store-pengajuan-magang'), validBerkasFiles());
    $berkas = BerkasPengajuanMagang::where('mahasiswa_id', $owner->id)->firstOrFail();

    $intruder = Mahasiswa::factory()->create();
    $this->actingAs($intruder, 'mahasiswa');

    $this->get(route('berkas.download', ['berkas' => $berkas->id, 'type' => 'cv']))
        ->assertForbidden();
});

it('lets the supervising dosen download a document', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $this->actingAs($mahasiswa, 'mahasiswa');
    $this->post(route('mahasiswa.store-pengajuan-magang'), validBerkasFiles());
    $berkas = BerkasPengajuanMagang::where('mahasiswa_id', $mahasiswa->id)->firstOrFail();

    $dosen = DosenPembimbing::factory()->create();
    KontrakMagang::factory()->create([
        'mahasiswa_id' => $mahasiswa->id,
        'dosen_id' => $dosen->id,
    ]);

    $this->actingAs($dosen, 'dosen');
    $this->get(route('berkas.download', ['berkas' => $berkas->id, 'type' => 'cv']))
        ->assertOk();
});

it('rejects an invalid document type', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $this->actingAs($mahasiswa, 'mahasiswa');
    $this->post(route('mahasiswa.store-pengajuan-magang'), validBerkasFiles());
    $berkas = BerkasPengajuanMagang::where('mahasiswa_id', $mahasiswa->id)->firstOrFail();

    $this->get(route('berkas.download', ['berkas' => $berkas->id, 'type' => 'secret']))
        ->assertNotFound();
});
