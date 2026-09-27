<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * P5-T2: backfill `perusahaan.lokasi_magang_id` from the legacy free text.
 *
 * Match strategy (in order): exact text, then case-insensitive exact, then a
 * loose contains-match on the city token, else fall back to the 'Semua lokasi'
 * category row so NO perusahaan is left unmatched (the pipeline needs a value).
 *
 * down() is intentionally a no-op: the column drop in P5-T1 down() reverses
 * the schema; un-filling data is meaningless.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('perusahaan') || ! Schema::hasColumn('perusahaan', 'lokasi_magang_id')) {
            return;
        }

        $lokasi = DB::table('lokasi_magang')->get(['id', 'lokasi', 'kategori_lokasi']);

        // Exact + case-insensitive lookup.
        $exact = [];
        foreach ($lokasi as $row) {
            $exact[$row->lokasi] = $row->id;
            $exact[strtolower(trim($row->lokasi))] = $row->id;
        }

        $fallbackId = $lokasi->firstWhere('kategori_lokasi', 'Semua')?->id
            ?? $lokasi->first()?->id;

        DB::table('perusahaan')->whereNull('lokasi_magang_id')->orderBy('id')
            ->chunkById(200, function ($rows) use ($exact, $lokasi, $fallbackId) {
                foreach ($rows as $p) {
                    $raw = trim((string) ($p->lokasi ?? ''));

                    $id = $exact[$raw] ?? $exact[strtolower($raw)] ?? null;

                    if ($id === null && $raw !== '') {
                        // Loose contains-match on a location's distinctive token.
                        foreach ($lokasi as $row) {
                            if (Str::contains(strtolower($row->lokasi), strtolower($raw), ignoreCase: false)
                                || Str::contains(strtolower($raw), strtolower($row->lokasi))) {
                                $id = $row->id;
                                break;
                            }
                        }
                    }

                    DB::table('perusahaan')
                        ->where('id', $p->id)
                        ->update(['lokasi_magang_id' => $id ?? $fallbackId]);
                }
            });
    }

    public function down(): void
    {
        // Intentionally empty: data backfill is not reverted.
    }
};
