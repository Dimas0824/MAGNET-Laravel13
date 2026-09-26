<?php

namespace App\Helpers\DecisionMaking;

use App\Models\EncodedAlternatives;
use App\Models\LokasiMagang;
use App\Models\LowonganMagang;
use App\Models\Mahasiswa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DataPreprocessing
{
    /**
     * Compute data categorization from raw alternatives data
     */
    public static function dataCategorization(LowonganMagang $lowonganMagang): void
    {
        $alternative = [
            'id' => $lowonganMagang->id,
            'pekerjaan' => $lowonganMagang->pekerjaan->nama,
            'open_remote' => $lowonganMagang->open_remote,
            'jenis_magang' => $lowonganMagang->jenis_magang,
            'bidang_industri' => $lowonganMagang->perusahaan->bidangIndustri->nama,
            'lokasi_magang' => $lowonganMagang->lokasiMagang->lokasi,
        ];

        $lokasi_magang_list = LokasiMagang::pluck('kategori_lokasi', 'lokasi')
            ->toArray();

        $lokasi = $alternative['lokasi_magang'];
        $alternative['lokasi_magang'] = $lokasi_magang_list[$lokasi] ?? 'Semua lokasi';

        $path = config('recommendation-system.preprocessing.alternatives_categorized_path');
        $fileContent = Storage::json($path) ?? [];

        // Upsert by lowongan id so repeated create/update events do not
        // accumulate duplicate entries.
        $replaced = false;
        foreach ($fileContent as $index => $existing) {
            if (($existing['id'] ?? null) === $alternative['id']) {
                $fileContent[$index] = $alternative;
                $replaced = true;
                break;
            }
        }

        if (! $replaced) {
            $fileContent[] = $alternative;
        }

        Storage::put($path, json_encode($fileContent, JSON_PRETTY_PRINT));
    }

    /**
     * Compute data encoding based from to all alternatives data based on user preference
     *
     * @return array<int, array<string, int>>
     */
    public static function dataEncoding(Mahasiswa $mahasiswa): void
    {
        $preference = [
            'pekerjaan' => $mahasiswa->kriteriaPekerjaan->pekerjaan->nama,
            'bidang_industri' => $mahasiswa->kriteriaBidangIndustri->bidangIndustri->nama,
            'jenis_magang' => $mahasiswa->kriteriaJenisMagang->jenis_magang,
            'lokasi_magang' => $mahasiswa->kriteriaLokasiMagang->lokasiMagang->kategori_lokasi,
            'open_remote' => $mahasiswa->kriteriaOpenRemote->open_remote,
        ];

        // Storage::json() returns null when the file has not been written yet
        // (a fresh install, or before any opening has been categorized). Treat
        // that as "no alternatives" instead of crashing the pipeline: the
        // queued RunRecommendationPipeline runs this on every preference
        // update and previously threw
        // "array_map(): Argument #2 must be of type array, null given".
        $dataCategorized = Storage::json(config('recommendation-system.preprocessing.alternatives_categorized_path')) ?? [];

        // Drop entries whose opening no longer exists so the insert below
        // cannot violate encoded_alternatives.lowongan_magang_id foreign key
        // (the categorized file persists across resets and can go stale).
        $existingOpeningIds = LowonganMagang::whereIn('id', array_column($dataCategorized, 'id'))
            ->pluck('id')
            ->all();
        $dataCategorized = array_values(array_filter(
            $dataCategorized,
            fn (array $item): bool => in_array($item['id'] ?? null, $existingOpeningIds, true)
        ));

        $now = now();

        $result = array_map(
            fn (array $item): array => [
                'mahasiswa_id' => $mahasiswa->id,
                'lowongan_magang_id' => $item['id'],
                'pekerjaan' => match ($preference['pekerjaan']) {
                    'Semua', $item['pekerjaan'] => 2,
                    default => 1
                },
                'open_remote' => match ($preference['open_remote']) {
                    'Semua', $item['open_remote'] => 2,
                    default => 1
                },
                'jenis_magang' => match ($preference['jenis_magang']) {
                    'Semua', $item['jenis_magang'] => 2,
                    default => 1
                },
                'bidang_industri' => match ($preference['bidang_industri']) {
                    'Semua', $item['bidang_industri'] => 2,
                    default => 1
                },
                'lokasi_magang' => match ($preference['lokasi_magang']) {
                    'Semua', $item['lokasi_magang'] => 2,
                    default => 1
                },
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $dataCategorized
        );

        DB::transaction(function () use ($mahasiswa, $result) {
            // Replace this mahasiswa's encodings instead of appending, so
            // repeated pipeline runs stay idempotent and never accumulate
            // stale rows (encoded_alternatives has no unique constraint).
            EncodedAlternatives::where('mahasiswa_id', $mahasiswa->id)->delete();

            if ($result !== []) {
                EncodedAlternatives::insert($result);
            }
        });
    }
}
