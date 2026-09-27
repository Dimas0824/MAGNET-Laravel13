<?php

use App\Services\LookupService;

beforeEach(function () {
    seedMasterData();
});

it('returns pekerjaan option map', function () {
    $options = (new LookupService)->pekerjaanOptions();

    expect($options)
        ->toBeArray()
        ->not->toBeEmpty()
        ->toContain('Software Engineer');
});

it('returns bidang industri option map', function () {
    $options = (new LookupService)->bidangIndustriOptions();

    expect($options)
        ->toBeArray()
        ->not->toBeEmpty()
        ->toContain('Teknologi');
});

it('returns lokasi magang options (id => kategori_lokasi)', function () {
    $options = (new LookupService)->lokasiMagangOptions();

    expect($options)
        ->toBeArray()
        ->not->toBeEmpty()
        ->toContain('Area Malang Raya');
});
