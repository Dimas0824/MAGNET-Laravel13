<?php

use Illuminate\Support\Facades\Route;

beforeEach(function () {
    seedMasterData();

    // Preserve any tracked sitemap so tests never mutate the repository.
    $path = public_path('sitemap.xml');
    $GLOBALS['__sitemap_backup'] = file_exists($path) ? file_get_contents($path) : null;
});

afterEach(function () {
    $path = public_path('sitemap.xml');
    if (($GLOBALS['__sitemap_backup'] ?? null) !== null) {
        @file_put_contents($path, $GLOBALS['__sitemap_backup']);
    } else {
        @unlink($path);
    }
});

it('runs the sitemap command and writes public/sitemap.xml', function () {
    // Serve a known GET route so the sitemap has at least one entry.
    Route::get('/__sitemap-probe', fn () => 'ok')->name('sitemap.probe');

    $this->artisan('sitemap')->assertSuccessful();

    $path = public_path('sitemap.xml');
    expect(file_exists($path))->toBeTrue();
    expect(file_get_contents($path))->toContain('<urlset');
});

it('excludes api and internal routes from the sitemap', function () {
    Route::get('/__sitemap-public', fn () => 'ok');

    $this->artisan('sitemap')->assertSuccessful();

    $xml = file_get_contents(public_path('sitemap.xml'));
    expect($xml)->not->toContain('/livewire')
        ->and($xml)->not->toContain('/api');
});

it('scaffolds a repository class with make:repository', function () {
    $target = app_path('Repositories/GeneratedProbeRepository.php');
    @unlink($target);

    $this->artisan('make:repository', ['name' => 'GeneratedProbeRepository'])->assertSuccessful();

    expect(file_exists($target))->toBeTrue();

    @unlink($target);
});
