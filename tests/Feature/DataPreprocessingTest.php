<?php

use App\Helpers\DecisionMaking\DataPreprocessing;
use App\Models\EncodedAlternatives;
use App\Models\LokasiMagang;
use Illuminate\Support\Facades\Storage;

$path = fn () => config('recommendation-system.preprocessing.alternatives_categorized_path');

beforeEach(function () {
    Storage::fake('local');
    seedMasterData();
});

it('categorizes a lowongan into the alternatives JSON file using the location category', function () use ($path) {
    $lowongan = lowonganMagang(['open_remote' => 'ya', 'jenis_magang' => 'berbayar']);

    DataPreprocessing::dataCategorization($lowongan);

    $content = Storage::json($path());
    expect($content)->toBeArray()->toHaveCount(1);

    $row = $content[0];
    expect($row['id'])->toBe($lowongan->id)
        ->and($row['pekerjaan'])->toBe('Software Engineer')
        ->and($row['open_remote'])->toBe('ya')
        ->and($row['jenis_magang'])->toBe('berbayar')
        ->and($row['bidang_industri'])->toBeString()
        // lokasi is mapped from raw "lokasi" to its kategori_lokasi
        ->and($row['lokasi_magang'])->toBe('Area Malang Raya');
});

it('appends each categorized lowongan to the JSON file', function () use ($path) {
    DataPreprocessing::dataCategorization(lowonganMagang());
    DataPreprocessing::dataCategorization(lowonganMagang());

    expect(Storage::json($path()))->toHaveCount(2);
});

it('encodes a matching preference as 2 and a non-matching one as 1', function () use ($path) {
    // Opening that matches the mahasiswa preference on every criterion:
    // Software Engineer / Teknologi / berbayar / ya / Area Malang Raya.
    $perusahaan = App\Models\Perusahaan::factory()->create([
        'bidang_industri_id' => App\Models\BidangIndustri::where('nama', 'Teknologi')->value('id'),
    ]);
    $lowongan = lowonganMagang([
        'open_remote' => 'ya',
        'jenis_magang' => 'berbayar',
        'pekerjaan_id' => App\Models\Pekerjaan::where('nama', 'Software Engineer')->value('id'),
        'perusahaan_id' => $perusahaan->id,
        'lokasi_magang_id' => App\Models\LokasiMagang::where('kategori_lokasi', 'Area Malang Raya')->value('id'),
    ]);
    DataPreprocessing::dataCategorization($lowongan);

    // Preference matches pekerjaan, bidang, lokasi, jenis, remote => all 2
    $mahasiswa = mahasiswaDenganPreferensi();
    DataPreprocessing::dataEncoding($mahasiswa);

    $encoded = EncodedAlternatives::where('mahasiswa_id', $mahasiswa->id)->first();
    expect($encoded)->not->toBeNull()
        ->and($encoded->lowongan_magang_id)->toBe($lowongan->id)
        ->and($encoded->pekerjaan)->toBe(2)
        ->and($encoded->open_remote)->toBe(2)
        ->and($encoded->jenis_magang)->toBe(2)
        ->and($encoded->bidang_industri)->toBe(2)
        ->and($encoded->lokasi_magang)->toBe(2);
});

it('encodes a mismatching preference as 1', function () use ($path) {
    // Opening does NOT match the mahasiswa preference (which is Software Engineer/Teknologi)
    $lowongan = lowonganMagang([
        'pekerjaan_id' => App\Models\Pekerjaan::where('nama', 'Data Engineer')->value('id'),
        'jenis_magang' => 'tidak berbayar',
        'open_remote' => 'tidak',
    ]);
    DataPreprocessing::dataCategorization($lowongan);

    $mahasiswa = mahasiswaDenganPreferensi();
    DataPreprocessing::dataEncoding($mahasiswa);

    $encoded = EncodedAlternatives::where('mahasiswa_id', $mahasiswa->id)->first();
    expect($encoded->pekerjaan)->toBe(1)
        ->and($encoded->open_remote)->toBe(1)
        ->and($encoded->jenis_magang)->toBe(1);
});

it('treats a "Semua" preference as matching everything (value 2)', function () use ($path) {
    $lowongan = lowonganMagang([
        'pekerjaan_id' => App\Models\Pekerjaan::where('nama', 'Data Engineer')->value('id'),
    ]);
    DataPreprocessing::dataCategorization($lowongan);

    $mahasiswa = mahasiswaDenganPreferensi();
    // Override pekerjaan preference to "Semua"
    $mahasiswa->kriteriaPekerjaan->update([
        'pekerjaan_id' => App\Models\Pekerjaan::where('nama', 'Semua')->value('id'),
    ]);

    DataPreprocessing::dataEncoding($mahasiswa->fresh());

    $encoded = EncodedAlternatives::where('mahasiswa_id', $mahasiswa->id)->first();
    expect($encoded->pekerjaan)->toBe(2);
});

it('creates one encoded row per categorized opening', function () use ($path) {
    DataPreprocessing::dataCategorization(lowonganMagang());
    DataPreprocessing::dataCategorization(lowonganMagang());

    $mahasiswa = mahasiswaDenganPreferensi();
    DataPreprocessing::dataEncoding($mahasiswa);

    expect(EncodedAlternatives::where('mahasiswa_id', $mahasiswa->id)->count())->toBe(2);
});
