<?php

use App\Helpers\DecisionMaking\DataPreprocessing;
use App\Helpers\DecisionMaking\ROC;
use App\Models\BidangIndustri;
use App\Models\KriteriaBidangIndustri;
use App\Models\KriteriaJenisMagang;
use App\Models\KriteriaLokasiMagang;
use App\Models\KriteriaOpenRemote;
use App\Models\KriteriaPekerjaan;
use App\Models\LokasiMagang;
use App\Models\Mahasiswa;
use App\Models\Pekerjaan;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

beforeEach(function () {
    Storage::fake('local');
    seedMasterData();
});

/**
 * Drive the multi-step preference wizard straight to its final step so
 * storePreferensiMahasiswa() runs, then return the persisted criteria rows.
 */
function submitPreferensi(Mahasiswa $mahasiswa): \Livewire\Features\SupportTesting\Testable
{
    return Volt::test('pages.mahasiswa.persiapan-preferensi')
        ->set('pekerjaan', Pekerjaan::where('nama', 'Software Engineer')->value('id'))
        ->set('bidang_industri', BidangIndustri::where('nama', 'Teknologi')->value('id'))
        ->set('lokasi_magang', LokasiMagang::where('kategori_lokasi', 'Area Malang Raya')->value('id'))
        ->set('jenis_magang', 'berbayar')
        ->set('open_remote', 'ya')
        ->dispatch('update-step', [
            'step' => 3,
            'pekerjaan_rank' => 1,
            'bidang_industri_rank' => 2,
            'lokasi_magang_rank' => 3,
            'jenis_magang_rank' => 4,
            'open_remote_rank' => 5,
        ]);
}

it('stores preference weights using the configured roc total_criteria', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $this->actingAs($mahasiswa, 'mahasiswa');

    // Categorize one opening so the listener pipeline has encoding data.
    DataPreprocessing::dataCategorization(lowonganMagang());

    // Override the configured criteria count so a hard-coded literal would
    // produce a different weight than the config-driven value.
    $total = 7;
    config(['recommendation-system.roc.total_criteria' => $total]);

    submitPreferensi($mahasiswa);

    $pekerjaan = KriteriaPekerjaan::where('mahasiswa_id', $mahasiswa->id)->firstOrFail();
    $bidang = KriteriaBidangIndustri::where('mahasiswa_id', $mahasiswa->id)->firstOrFail();
    $lokasi = KriteriaLokasiMagang::where('mahasiswa_id', $mahasiswa->id)->firstOrFail();
    $jenis = KriteriaJenisMagang::where('mahasiswa_id', $mahasiswa->id)->firstOrFail();
    $remote = KriteriaOpenRemote::where('mahasiswa_id', $mahasiswa->id)->firstOrFail();

    // bobot is stored at decimal(6,3) (P4b), so the persisted value is the ROC
    // weight canonicalized to 3 decimals; the config total (7, not a literal) still
    // governs it.
    expect((float) $pekerjaan->bobot)->toEqualWithDelta(round(ROC::getWeight(1, $total), 3), 1e-9)
        ->and((float) $bidang->bobot)->toEqualWithDelta(round(ROC::getWeight(2, $total), 3), 1e-9)
        ->and((float) $lokasi->bobot)->toEqualWithDelta(round(ROC::getWeight(3, $total), 3), 1e-9)
        ->and((float) $jenis->bobot)->toEqualWithDelta(round(ROC::getWeight(4, $total), 3), 1e-9)
        ->and((float) $remote->bobot)->toEqualWithDelta(round(ROC::getWeight(5, $total), 3), 1e-9);
});

it('is idempotent: submitting preferences twice keeps one criteria row per table', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $this->actingAs($mahasiswa, 'mahasiswa');

    DataPreprocessing::dataCategorization(lowonganMagang());

    // First submission creates the five criteria rows.
    submitPreferensi($mahasiswa);

    // Second submission (user re-runs the wizard) must UPDATE the existing rows,
    // not insert a duplicate set and not silently fail on the unique index.
    $component = submitPreferensi($mahasiswa);

    $component->assertHasNoErrors();

    // The wizard flashes the outcome; a swallowed failure would flash 'failed'.
    expect(session('status'))->not->toBe('failed');

    expect(KriteriaPekerjaan::where('mahasiswa_id', $mahasiswa->id)->count())->toBe(1)
        ->and(KriteriaBidangIndustri::where('mahasiswa_id', $mahasiswa->id)->count())->toBe(1)
        ->and(KriteriaLokasiMagang::where('mahasiswa_id', $mahasiswa->id)->count())->toBe(1)
        ->and(KriteriaJenisMagang::where('mahasiswa_id', $mahasiswa->id)->count())->toBe(1)
        ->and(KriteriaOpenRemote::where('mahasiswa_id', $mahasiswa->id)->count())->toBe(1);
});
