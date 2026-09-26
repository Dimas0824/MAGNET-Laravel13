<?php

use Illuminate\Support\Facades\Schema;

/**
 * P5-T4 CONTRACT: after both consumers moved onto the lokasi_magang FK, the
 * legacy `perusahaan.lokasi` free-text column is dropped so it can never be
 * read again.
 */
it('drops the legacy perusahaan.lokasi free-text column', function () {
    expect(Schema::hasColumn('perusahaan', 'lokasi'))->toBeFalse();
});

it('keeps lokasi_magang_id as the single location source', function () {
    expect(Schema::hasColumn('perusahaan', 'lokasi_magang_id'))->toBeTrue();
});

it('has a reversible down() that restores lokasi', function () {
    $migration = glob(database_path('migrations/*_drop_lokasi_from_perusahaan.php'));

    expect($migration)->not->toBeEmpty();

    $source = file_get_contents($migration[0]);

    expect($source)->toContain('function down')
        ->and($source)->toContain("'lokasi'");
});
