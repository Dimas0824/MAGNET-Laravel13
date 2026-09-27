<?php

use App\Models\Pekerjaan;
use App\Support\Listing;

/**
 * W0-T03: Shared listing helpers for search + sort. `applySearch` applies an
 * OR-LIKE across the given columns (and is a no-op for an empty term), while
 * `applySort` only honours a column present in the caller's whitelist so an
 * attacker-supplied `?sort=` can never reach `ORDER BY`.
 */
beforeEach(function () {
    seedMasterData();
});

it('applies OR-LIKE search across columns', function () {
    $query = Listing::applySearch(
        Pekerjaan::query(),
        'Engineer',
        ['nama'],
    );

    $results = $query->pluck('nama');

    expect($results)->toContain('Software Engineer')
        ->and($results)->toContain('Data Engineer')
        ->and($results)->not->toContain('UI/UX Designer');

    // A second column must also be searched (OR semantics).
    $both = Listing::applySearch(
        Pekerjaan::query(),
        'Semua',
        ['nama'],
    )->pluck('nama');

    expect($both)->toContain('Semua');
});

it('rejects an unknown sort column (whitelist)', function () {
    $allowed = ['nama', 'id'];

    $query = Listing::applySort(
        Pekerjaan::query(),
        'nama; DROP TABLE pekerjaan',
        'asc',
        $allowed,
        'id',
    );

    expect($query->toSql())->toContain('order by `id` asc')
        ->and($query->toSql())->not->toContain('drop table');
});

it('is a no-op when the search term is empty', function () {
    $base = Pekerjaan::query()->toSql();
    $noop = Listing::applySearch(Pekerjaan::query(), '', ['nama'])->toSql();

    expect($noop)->toBe($base)
        ->and(Listing::applySearch(Pekerjaan::query(), null, ['nama'])->toSql())->toBe($base)
        ->and(Listing::applySearch(Pekerjaan::query(), '   ', ['nama'])->toSql())->toBe($base);
});
