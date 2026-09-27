<?php

namespace App\Services;

use App\Events\MahasiswaPreferenceUpdated;
use App\Helpers\DecisionMaking\ROC;
use App\Models\BaseKriteriaModel;
use App\Models\BidangIndustri;
use App\Models\LokasiMagang;
use App\Models\Mahasiswa;
use App\Models\MahasiswaKriteria;
use App\Models\Pekerjaan;
use RuntimeException;

/**
 * W0-T05b: criteria preference writing, extracted from the profile page
 * (`saveNewPreference`) and the preference wizard (`storePreferensiMahasiswa`)
 * so both surfaces share one implementation.
 *
 * This reproduces the CURRENT behavior exactly — two recent bugfixes depend on it:
 *  - Writes go through a MODEL instance (`firstOrNew()->forceFill()->save()`),
 *    never a Builder `::update()`. A Builder update BYPASSES
 *    BaseKriteriaModel::setAttribute()'s legacy->value_enum remap and emits the
 *    legacy column names as raw SQL (SQLSTATE 42S22 Unknown column).
 *  - The five writes are wrapped in `BaseKriteriaModel::withoutEvents()` so the
 *    per-row recompute trigger does not fire five times; the pipeline is
 *    dispatched EXACTLY ONCE after the writes (`MahasiswaPreferenceUpdated`).
 *
 * rank/bobot are NOT NULL with no column default, so a freshly-created row
 * (a student who never completed the wizard) needs them too: existing rank/bobot
 * are preserved; a new row falls back to a default ordering (ROC weight).
 */
class MahasiswaPreferenceService
{
    /**
     * The relation method on Mahasiswa per criteria key, in default rank order.
     *
     * @var array<string, array{0: string, 1: int}>
     */
    private const CRITERIA = [
        MahasiswaKriteria::KEY_PEKERJAAN => ['kriteriaPekerjaan', 1],
        MahasiswaKriteria::KEY_BIDANG_INDUSTRI => ['kriteriaBidangIndustri', 2],
        MahasiswaKriteria::KEY_LOKASI_MAGANG => ['kriteriaLokasiMagang', 3],
        MahasiswaKriteria::KEY_JENIS_MAGANG => ['kriteriaJenisMagang', 4],
        MahasiswaKriteria::KEY_OPEN_REMOTE => ['kriteriaOpenRemote', 5],
    ];

    /**
     * Save the five criteria preferences from the profile page input.
     *
     * `$input` keys (names/kategori -> resolved to ids like the page does):
     * pekerjaan, bidang_industri, lokasi_magang, jenis_magang, open_remote.
     *
     * @param  array{pekerjaan: string, bidang_industri: string, lokasi_magang: string, jenis_magang: mixed, open_remote: mixed}  $input
     *
     * @throws RuntimeException with the same user-facing messages the page threw
     */
    public function savePreferences(Mahasiswa $mahasiswa, array $input): void
    {
        $bidangIndustri = BidangIndustri::where('nama', $input['bidang_industri'])->first();
        if (! $bidangIndustri) {
            throw new RuntimeException('Bidang Industri tidak ditemukan');
        }

        $lokasiMagang = LokasiMagang::where('kategori_lokasi', $input['lokasi_magang'])->first();
        if (! $lokasiMagang) {
            throw new RuntimeException('Lokasi Magang tidak ditemukan');
        }

        $pekerjaan = Pekerjaan::where('nama', $input['pekerjaan'])->first();
        if (! $pekerjaan) {
            throw new RuntimeException('Pekerjaan tidak ditemukan');
        }

        $values = [
            MahasiswaKriteria::KEY_PEKERJAAN => ['pekerjaan_id' => $pekerjaan->id],
            MahasiswaKriteria::KEY_BIDANG_INDUSTRI => ['bidang_industri_id' => $bidangIndustri->id],
            MahasiswaKriteria::KEY_LOKASI_MAGANG => ['lokasi_magang_id' => $lokasiMagang->id],
            MahasiswaKriteria::KEY_JENIS_MAGANG => ['jenis_magang' => $input['jenis_magang']],
            MahasiswaKriteria::KEY_OPEN_REMOTE => ['open_remote' => $input['open_remote']],
        ];

        $this->write($mahasiswa, $values);

        $mahasiswa->refresh();

        event(new MahasiswaPreferenceUpdated($mahasiswa));
    }

    /**
     * Persist criteria priorities: each key at index i gets rank i+1 and its ROC
     * weight. Writes go through firstOrNew + forceFill + save inside
     * withoutEvents(); the pipeline event fires exactly once.
     *
     * @param  array<int, string>  $orderedKeys  criteria keys in priority order
     */
    public function saveRanking(Mahasiswa $mahasiswa, array $orderedKeys): void
    {
        $total = config('recommendation-system.roc.total_criteria');

        $write = function () use ($mahasiswa, $orderedKeys, $total) {
            foreach ($orderedKeys as $index => $key) {
                if (! isset(self::CRITERIA[$key])) {
                    continue;
                }

                [$relation] = self::CRITERIA[$key];
                $rank = $index + 1;

                $mahasiswa->{$relation}()->firstOrNew(['mahasiswa_id' => $mahasiswa->id])
                    ->forceFill([
                        'rank' => $rank,
                        'bobot' => ROC::getWeight($rank, $total),
                    ])->save();
            }
        };

        BaseKriteriaModel::withoutEvents($write);

        $mahasiswa->refresh();

        event(new MahasiswaPreferenceUpdated($mahasiswa));
    }

    /**
     * Apply the five criteria values, preserving existing rank/bobot and
     * defaulting new rows to their canonical rank + ROC weight.
     *
     * @param  array<string, array<string, mixed>>  $values  criteria_key => column value map
     */
    private function write(Mahasiswa $mahasiswa, array $values): void
    {
        $total = config('recommendation-system.roc.total_criteria');

        $write = function () use ($mahasiswa, $values, $total) {
            foreach (self::CRITERIA as $key => [$relation, $defaultRank]) {
                $row = $mahasiswa->{$relation}()->firstOrNew(['mahasiswa_id' => $mahasiswa->id]);

                $rank = $row->exists ? $row->rank : $defaultRank;

                $row->forceFill($values[$key] + [
                    'rank' => $rank,
                    'bobot' => ROC::getWeight($rank, $total),
                ])->save();
            }
        };

        BaseKriteriaModel::withoutEvents($write);
    }
}
