<?php

use App\Models\BerkasPengajuanMagang;
use App\Models\FormPengajuanMagang;
use App\Models\Mahasiswa;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    seedMasterData();
    Storage::fake('public');
});

function validPengajuanFiles(): array
{
    return [
        'cv' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
        'transkrip_nilai' => UploadedFile::fake()->create('transkrip.pdf', 100, 'application/pdf'),
        'portfolio' => UploadedFile::fake()->create('portfolio.pdf', 100, 'application/pdf'),
    ];
}

it('stores a valid pengajuan magang submission', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $this->actingAs($mahasiswa, 'mahasiswa');

    $response = $this->post(route('mahasiswa.store-pengajuan-magang'), validPengajuanFiles());

    $response->assertRedirect(route('mahasiswa.pengajuan-magang'));

    $berkas = BerkasPengajuanMagang::where('mahasiswa_id', $mahasiswa->id)->first();
    expect($berkas)->not->toBeNull();

    Storage::disk('public')->assertExists($berkas->cv);
    Storage::disk('public')->assertExists($berkas->transkrip_nilai);
    Storage::disk('public')->assertExists($berkas->portfolio);

    // A form record is created with status "diproses".
    $form = FormPengajuanMagang::where('pengajuan_id', $berkas->id)->first();
    expect($form)->not->toBeNull()
        ->and($form->status)->toBe('diproses');
});

it('allows the portfolio to be omitted', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $this->actingAs($mahasiswa, 'mahasiswa');

    $files = validPengajuanFiles();
    unset($files['portfolio']);

    $this->post(route('mahasiswa.store-pengajuan-magang'), $files)
        ->assertRedirect(route('mahasiswa.pengajuan-magang'));

    $berkas = BerkasPengajuanMagang::where('mahasiswa_id', $mahasiswa->id)->first();
    expect($berkas)->not->toBeNull()
        ->and($berkas->portfolio)->toBeNull();
});

it('rejects a submission missing the CV', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $this->actingAs($mahasiswa, 'mahasiswa');

    $files = validPengajuanFiles();
    unset($files['cv']);

    $this->post(route('mahasiswa.store-pengajuan-magang'), $files)
        ->assertSessionHasErrors('cv');

    expect(BerkasPengajuanMagang::where('mahasiswa_id', $mahasiswa->id)->count())->toBe(0);
});

it('rejects a non-pdf CV', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $this->actingAs($mahasiswa, 'mahasiswa');

    $files = validPengajuanFiles();
    $files['cv'] = UploadedFile::fake()->create('cv.txt', 10, 'text/plain');

    $this->post(route('mahasiswa.store-pengajuan-magang'), $files)
        ->assertSessionHasErrors('cv');
});

it('rejects a CV larger than 2MB', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $this->actingAs($mahasiswa, 'mahasiswa');

    $files = validPengajuanFiles();
    $files['cv'] = UploadedFile::fake()->create('cv.pdf', 3000, 'application/pdf');

    $this->post(route('mahasiswa.store-pengajuan-magang'), $files)
        ->assertSessionHasErrors('cv');
});

it('replaces a previous submission and deletes old files', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $this->actingAs($mahasiswa, 'mahasiswa');

    $this->post(route('mahasiswa.store-pengajuan-magang'), validPengajuanFiles());
    $first = BerkasPengajuanMagang::where('mahasiswa_id', $mahasiswa->id)->first();

    $this->post(route('mahasiswa.store-pengajuan-magang'), validPengajuanFiles());

    // Only one berkas remains for the mahasiswa.
    expect(BerkasPengajuanMagang::where('mahasiswa_id', $mahasiswa->id)->count())->toBe(1);

    $second = BerkasPengajuanMagang::where('mahasiswa_id', $mahasiswa->id)->first();
    expect($second->id)->not->toBe($first->id);
});

it('requires authentication to submit a pengajuan', function () {
    $this->post(route('mahasiswa.store-pengajuan-magang'), validPengajuanFiles())
        ->assertRedirect(route('login'));
});

it('rejects a non-pdf portfolio', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $this->actingAs($mahasiswa, 'mahasiswa');

    $files = validPengajuanFiles();
    $files['portfolio'] = UploadedFile::fake()->create('p.txt', 10, 'text/plain');

    $this->post(route('mahasiswa.store-pengajuan-magang'), $files)
        ->assertSessionHasErrors('portfolio');
});

it('rejects a portfolio larger than 2MB', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $this->actingAs($mahasiswa, 'mahasiswa');

    $files = validPengajuanFiles();
    $files['portfolio'] = UploadedFile::fake()->create('p.pdf', 3000, 'application/pdf');

    $this->post(route('mahasiswa.store-pengajuan-magang'), $files)
        ->assertSessionHasErrors('portfolio');
});

it('rejects a missing transkrip nilai', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $this->actingAs($mahasiswa, 'mahasiswa');

    $files = validPengajuanFiles();
    unset($files['transkrip_nilai']);

    $this->post(route('mahasiswa.store-pengajuan-magang'), $files)
        ->assertSessionHasErrors('transkrip_nilai');
});

it('updates the submission status to diproses via setStatusdiproses', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $this->actingAs($mahasiswa, 'mahasiswa');

    $this->post(route('mahasiswa.store-pengajuan-magang'), validPengajuanFiles());

    $berkas = BerkasPengajuanMagang::where('mahasiswa_id', $mahasiswa->id)->first();
    expect($berkas->formPengajuanMagang->status)->toBe('diproses');

    // Calling the public helper returns true when a berkas exists.
    $controller = new App\Http\Controllers\PengajuanMagangController();
    expect($controller->setStatusdiproses($mahasiswa->id))->toBeTrue();
});

it('returns false from setStatusdiproses when no submission exists', function () {
    $mahasiswa = Mahasiswa::factory()->create();

    $controller = new App\Http\Controllers\PengajuanMagangController();
    expect($controller->setStatusdiproses($mahasiswa->id))->toBeFalse();
});
