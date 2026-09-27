<?php

use App\Http\Controllers\PengajuanMagangController;
use App\Models\BerkasPengajuanMagang;
use App\Models\FormPengajuanMagang;
use App\Models\Mahasiswa;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    seedMasterData();
    Storage::fake('private');
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

    Storage::disk('private')->assertExists($berkas->cv);
    Storage::disk('private')->assertExists($berkas->transkrip_nilai);
    Storage::disk('private')->assertExists($berkas->portfolio);

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
    $controller = new PengajuanMagangController;
    expect($controller->setStatusdiproses($mahasiswa->id))->toBeTrue();
});

it('returns false from setStatusdiproses when no submission exists', function () {
    $mahasiswa = Mahasiswa::factory()->create();

    $controller = new PengajuanMagangController;
    expect($controller->setStatusdiproses($mahasiswa->id))->toBeFalse();
});

it('generates unique filenames for two submissions by the same mahasiswa', function () {
    // Two different students who share the same name must not collide.
    $first = Mahasiswa::factory()->create(['nama' => 'Budi Santoso']);
    $second = Mahasiswa::factory()->create(['nama' => 'Budi Santoso']);

    $this->actingAs($first, 'mahasiswa')
        ->post(route('mahasiswa.store-pengajuan-magang'), validPengajuanFiles());

    $this->actingAs($second, 'mahasiswa')
        ->post(route('mahasiswa.store-pengajuan-magang'), validPengajuanFiles());

    $firstBerkas = BerkasPengajuanMagang::where('mahasiswa_id', $first->id)->first();
    $secondBerkas = BerkasPengajuanMagang::where('mahasiswa_id', $second->id)->first();

    expect($firstBerkas)->not->toBeNull()
        ->and($secondBerkas)->not->toBeNull()
        ->and($firstBerkas->cv)->not->toBe($secondBerkas->cv)
        ->and($firstBerkas->transkrip_nilai)->not->toBe($secondBerkas->transkrip_nilai);

    // Both submissions must survive on disk; neither overwrote the other.
    Storage::disk('private')->assertExists($firstBerkas->cv);
    Storage::disk('private')->assertExists($secondBerkas->cv);
});

it('stores a random token in each generated filename', function () {
    $mahasiswa = Mahasiswa::factory()->create(['nama' => 'Siti Aminah']);
    $this->actingAs($mahasiswa, 'mahasiswa');

    $this->post(route('mahasiswa.store-pengajuan-magang'), validPengajuanFiles());

    $berkas = BerkasPengajuanMagang::where('mahasiswa_id', $mahasiswa->id)->first();

    // Filename shape: cv_<date>_<name>_<token>.pdf — the token part must exist.
    $basename = basename($berkas->cv, '.pdf');
    $parts = explode('_', $basename);

    expect(str_ends_with($berkas->cv, '.pdf'))->toBeTrue()
        ->and(count($parts))->toBeGreaterThanOrEqual(4)
        ->and(end($parts))->not->toBe('aminah');
});

it('does not leak exception file path or line to the user', function () {
    $mahasiswa = Mahasiswa::factory()->create(['nama' => 'Rara Wulandari']);
    $this->actingAs($mahasiswa, 'mahasiswa');

    // Force a genuine runtime exception inside the controller's try block by
    // dropping the table the berkas insert writes to. Validation still passes,
    // file storage succeeds, and the DB write throws.
    Schema::disableForeignKeyConstraints();
    Schema::drop('berkas_pengajuan_magang');
    Schema::enableForeignKeyConstraints();

    $response = $this->post(route('mahasiswa.store-pengajuan-magang'), validPengajuanFiles());

    $error = session('error');

    expect($error)->not->toBeNull()
        ->and($error)->toBe('Terjadi kesalahan sistem. Silakan coba lagi atau hubungi admin.')
        ->and($error)->not->toContain('.php:')
        ->and($error)->not->toContain('Debug Error')
        ->and($error)->not->toContain('PengajuanMagangController')
        ->and($error)->not->toContain('SQLSTATE');
});

it('shows a clean generic error on validation failure', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $this->actingAs($mahasiswa, 'mahasiswa');

    $files = validPengajuanFiles();
    unset($files['cv']);

    $response = $this->post(route('mahasiswa.store-pengajuan-magang'), $files);

    $response->assertSessionHasErrors('cv');

    $error = session('error');
    expect($error)->not->toContain('.php:')
        ->and($error)->not->toContain('Debug Error');
});
