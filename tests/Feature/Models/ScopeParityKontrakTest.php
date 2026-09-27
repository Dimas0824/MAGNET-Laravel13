<?php

use App\Models\DosenPembimbing;
use App\Models\KontrakMagang;
use App\Models\Mahasiswa;
use App\Models\UmpanBalikMagang;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Scope parity (W0-T04b)
|--------------------------------------------------------------------------
|
| The reusable #[Scope] methods on KontrakMagang are extracted verbatim from
| inline query chains in resources/views/pages/dosen/dashboard.blade.php.
| These tests pin the scopes to the OLD inline chains (the oracle): given the
| same fixtures, the scope must return the exact same kontrak ids.
|
| NOTE: distinct filename from W0-T04a's ScopeParityTest to avoid collision.
|
*/

beforeEach(function () {
    seedMasterData();
});

/**
 * Build a dosen (plus a second dosen) with a mixed set of kontrak + feedback
 * fixtures so both the mahasiswa filter and the feedback-exists/recency filter
 * have differing rows.
 *
 * NOTE: kontrak_magang has a UNIQUE(mahasiswa_id) constraint
 * (migration 2026_09_26_150200_add_unique_indexes.php), so a mahasiswa can own
 * at most ONE kontrak — the fixtures respect that.
 *
 * Layout:
 *  - mahasiswaA owns 1 kontrak belonging to $dosen, with RECENT + non-empty
 *    feedback                                            -> matches feedback.
 *  - mahasiswaB owns 1 kontrak belonging to $dosen, feedback comment EMPTY
 *                                                       -> excluded from exists.
 *  - mahasiswaC owns 1 kontrak belonging to a DIFFERENT dosen, recent feedback
 *                                                       -> excluded (other dosen).
 *
 * @return array{
 *     dosen: DosenPembimbing,
 *     other: DosenPembimbing,
 *     mahasiswaA: Mahasiswa,
 *     mahasiswaB: Mahasiswa,
 *     mahasiswaC: Mahasiswa,
 *     kontrakA: KontrakMagang,
 *     kontrakB: KontrakMagang,
 *     kontrakC: KontrakMagang,
 * }
 */
function seedScopeParityFixtures(): array
{
    $dosen = DosenPembimbing::factory()->create();
    $other = DosenPembimbing::factory()->create();

    $mahasiswaA = Mahasiswa::factory()->create(['nama' => 'Scope A']);
    $mahasiswaB = Mahasiswa::factory()->create(['nama' => 'Scope B']);
    $mahasiswaC = Mahasiswa::factory()->create(['nama' => 'Scope C']);

    $kontrakA = KontrakMagang::factory()->create([
        'mahasiswa_id' => $mahasiswaA->id,
        'dosen_id' => $dosen->id,
        'lowongan_magang_id' => lowonganMagang()->id,
    ]);
    $kontrakB = KontrakMagang::factory()->create([
        'mahasiswa_id' => $mahasiswaB->id,
        'dosen_id' => $dosen->id,
        'lowongan_magang_id' => lowonganMagang()->id,
    ]);
    $kontrakC = KontrakMagang::factory()->create([
        'mahasiswa_id' => $mahasiswaC->id,
        'dosen_id' => $other->id,
        'lowongan_magang_id' => lowonganMagang()->id,
    ]);

    // mahasiswaA / kontrakA: recent feedback with non-empty comment -> MATCH.
    UmpanBalikMagang::create([
        'kontrak_magang_id' => $kontrakA->id,
        'komentar' => 'Feedback A',
        'tanggal' => now()->toDateString(),
    ]);
    // mahasiswaB / kontrakB: empty comment -> excluded from the exists set.
    UmpanBalikMagang::create([
        'kontrak_magang_id' => $kontrakB->id,
        'komentar' => '',
        'tanggal' => now()->toDateString(),
    ]);
    // mahasiswaC / kontrakC: recent + non-empty, but a different dosen -> excluded.
    UmpanBalikMagang::create([
        'kontrak_magang_id' => $kontrakC->id,
        'komentar' => 'Feedback C',
        'tanggal' => now()->toDateString(),
    ]);

    return [
        'dosen' => $dosen,
        'other' => $other,
        'mahasiswaA' => $mahasiswaA,
        'mahasiswaB' => $mahasiswaB,
        'mahasiswaC' => $mahasiswaC,
        'kontrakA' => $kontrakA,
        'kontrakB' => $kontrakB,
        'kontrakC' => $kontrakC,
    ];
}

it('scope forMahasiswa returns the same kontrak ids as the inline chain', function () {
    $fx = seedScopeParityFixtures();

    // ORACLE: the inline chain copied verbatim from the views, e.g.
    // resources/views/pages/mahasiswa/log-mahasiswa.blade.php:34
    //   KontrakMagang::where('mahasiswa_id', $this->mahasiswa->id)
    $oracle = KontrakMagang::where('mahasiswa_id', $fx['mahasiswaA']->id)
        ->orderBy('id')
        ->pluck('id')
        ->all();

    $scoped = KontrakMagang::forMahasiswa($fx['mahasiswaA']->id)
        ->orderBy('id')
        ->pluck('id')
        ->all();

    expect($scoped)->toBe($oracle)
        ->and($scoped)->toBe([$fx['kontrakA']->id])
        ->and($scoped)->not->toContain($fx['kontrakB']->id)
        ->and($scoped)->not->toContain($fx['kontrakC']->id);
});

it('scope withFeedbackSince(exists) returns the same ids as the inline DB::raw(1) exists chain', function () {
    $fx = seedScopeParityFixtures();

    $days = 30;

    // ORACLE: the inline chain copied verbatim from
    // resources/views/pages/dosen/dashboard.blade.php:107-117.
    $oracle = KontrakMagang::where('dosen_id', $fx['dosen']->id)
        ->whereExists(function ($query) use ($days) {
            $query
                ->select(DB::raw(1))
                ->from('umpan_balik_magang')
                ->whereRaw('umpan_balik_magang.kontrak_magang_id = kontrak_magang.id')
                ->where('umpan_balik_magang.created_at', '>=', now()->subDays($days))
                ->whereNotNull('umpan_balik_magang.komentar')
                ->where('umpan_balik_magang.komentar', '!=', '');
        })
        ->orderBy('id')
        ->pluck('id')
        ->all();

    $scoped = KontrakMagang::where('dosen_id', $fx['dosen']->id)
        ->withFeedbackSince($days)
        ->orderBy('id')
        ->pluck('id')
        ->all();

    expect($scoped)->toBe($oracle)
        ->and($scoped)->toHaveCount(1)
        ->and($scoped)->toBe([$fx['kontrakA']->id]);
});
