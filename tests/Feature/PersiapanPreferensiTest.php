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
function submitPreferensi(Mahasiswa $mahasiswa): void
{
    Volt::test('pages.mahasiswa.persiapan-preferensi')
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

    expect((float) $pekerjaan->bobot)->toEqualWithDelta(ROC::getWeight(1, $total), 1e-12)
        ->and((float) $bidang->bobot)->toEqualWithDelta(ROC::getWeight(2, $total), 1e-12)
        ->and((float) $lokasi->bobot)->toEqualWithDelta(ROC::getWeight(3, $total), 1e-12)
        ->and((float) $jenis->bobot)->toEqualWithDelta(ROC::getWeight(4, $total), 1e-12)
        ->and((float) $remote->bobot)->toEqualWithDelta(ROC::getWeight(5, $total), 1e-12);
});
