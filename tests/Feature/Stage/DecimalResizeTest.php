<?php

use App\Models\FullMultiplicativeForm;
use App\Models\RatioSystem;
use App\Models\ReferencePoint;
use App\Models\VectorNormalization;
use Illuminate\Support\Facades\DB;

/**
 * P4a-T2: stage numeric columns are sized to the values they actually hold.
 * decimal(30,15) is absurdly wide (and slow to index); the design fixes them
 * at decimal(9,6) — six fractional digits is far more than the scores need and
 * still round-trips the pipeline exactly.
 */
const STAGE_DECIMAL_COLUMNS = [
    'ratio_system' => ['score'],
    'full_multiplicative_form' => ['score'],
    'reference_point' => ['pekerjaan', 'open_remote', 'jenis_magang', 'bidang_industri', 'lokasi_magang', 'max_score'],
    'vector_normalization' => ['pekerjaan', 'open_remote', 'jenis_magang', 'bidang_industri', 'lokasi_magang'],
    'final_rank_recommendation' => ['avg_rank'],
];

it('resizes every stage decimal column to decimal(9,6)', function () {
    foreach (STAGE_DECIMAL_COLUMNS as $table => $columns) {
        foreach ($columns as $column) {
            $type = DB::table('information_schema.columns')
                ->where('table_schema', DB::connection()->getDatabaseName())
                ->where('table_name', $table)
                ->where('column_name', $column)
                ->value('column_type');

            expect($type)->toBe('decimal(9,6)', "{$table}.{$column} should be decimal(9,6), got {$type}");
        }
    }
});

it('leaves no decimal(30,15) column on any stage table', function () {
    $stageTables = array_keys(STAGE_DECIMAL_COLUMNS);

    // information_schema column names are returned UPPERCASE; group so the
    // assertion names the offending table:column.
    $stillWideOnStage = DB::table('information_schema.columns')
        ->select('TABLE_NAME', 'COLUMN_NAME')
        ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
        ->where('COLUMN_TYPE', 'decimal(30,15)')
        ->whereIn('TABLE_NAME', $stageTables)
        ->get()
        ->all();

    expect($stillWideOnStage)->toBe([]);
});

it('casts stage money-like columns to a fixed-precision decimal', function () {
    expect((new RatioSystem)->getCasts()['score'] ?? null)->not->toBeNull();
    expect((new FullMultiplicativeForm)->getCasts()['score'] ?? null)->not->toBeNull();
    expect((new ReferencePoint)->getCasts()['max_score'] ?? null)->not->toBeNull();
    // VectorNormalization holds normalized scores -> decimal; the discrete
    // EncodedAlternatives values stay integers (1/2 encodings).
    expect((new VectorNormalization)->getCasts()['pekerjaan'] ?? null)->not->toBeNull();
    expect((new \App\Models\EncodedAlternatives)->getCasts()['pekerjaan'] ?? null)->toBe('integer');
});
