<?php

use App\Models\DosenPembimbing;
use App\Models\KontrakMagang;
use App\Models\Mahasiswa;
use App\Models\UmpanBalikMagang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Pest's boot files are resolved from the vendor location, which is a junction
// to the sibling checkout in this worktree, so the shared tests/Pest.php applies
// only to that sibling's tests directory. Declare the base case + RefreshDatabase
// here so this file boots the application and refreshes the schema regardless.
uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    seedMasterData();
});

/**
 * Build a mahasiswa with a KontrakMagang and the given number of
 * UmpanBalikMagang rows. Returns [mahasiswa, kontrak].
 */
function mahasiswaWithFeedback(int $feedbackCount = 3): array
{
    $mahasiswa = Mahasiswa::factory()->create();
    $dosen = DosenPembimbing::factory()->create();
    $lowongan = lowonganMagang();

    $kontrak = KontrakMagang::factory()->create([
        'mahasiswa_id' => $mahasiswa->id,
        'dosen_id' => $dosen->id,
        'lowongan_magang_id' => $lowongan->id,
    ]);

    for ($i = 0; $i < $feedbackCount; $i++) {
        UmpanBalikMagang::create([
            'kontrak_magang_id' => $kontrak->id,
            'komentar' => 'Saran bimbingan '.$i,
            'tanggal' => now()->subDays($i)->toDateString(),
        ]);
    }

    return [$mahasiswa, $kontrak];
}

it('renders the saran-dari-dosen page', function () {
    [$mahasiswa] = mahasiswaWithFeedback(3);
    actingAsMahasiswa($mahasiswa);

    $this->get(route('mahasiswa.saran-dari-dosen'))
        ->assertOk()
        ->assertSee('Saran bimbingan 0');
});

it('paginates feedback at the query level (no full-collection slice)', function () {
    // 15 rows against a per-page of 6 => 3 pages worth of data. The old
    // implementation loads every row into PHP and slices the collection;
    // a DB-level paginate() must instead select just one page of rows.
    [$mahasiswa] = mahasiswaWithFeedback(15);
    actingAsMahasiswa($mahasiswa);

    DB::enableQueryLog();
    $response = $this->get(route('mahasiswa.saran-dari-dosen'));
    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();

    $response->assertOk();

    // The feedback SELECT must carry a LIMIT so only one page is fetched.
    $feedbackSelects = $queries->filter(
        fn ($q) => str_contains($q['query'], 'from `umpan_balik_magang`')
            && str_contains($q['query'], 'select')
    );

    expect($feedbackSelects)->not->toBeEmpty();
    expect($feedbackSelects->contains(fn ($q) => str_contains($q['query'], 'limit')))->toBeTrue();

    // Query count must stay bounded and independent of the row count.
    expect($queries->count())->toBeLessThanOrEqual(20);
});

it('only renders one page worth of feedback even when more rows exist', function () {
    [$mahasiswa] = mahasiswaWithFeedback(15);
    actingAsMahasiswa($mahasiswa);

    $response = $this->get(route('mahasiswa.saran-dari-dosen'));
    $response->assertOk();

    // per-page is 6, so at most 6 komentar blocks render on page one.
    $html = $response->getContent();
    $rendered = preg_match_all('/Saran bimbingan \d+/', $html, $matches);

    expect($rendered)->toBeLessThanOrEqual(6);
});
