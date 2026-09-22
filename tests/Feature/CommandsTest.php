<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    seedMasterData();
});

it('runs the sitemap command and writes public/sitemap.xml', function () {
    // Serve a known GET route so the sitemap has at least one entry.
    Route::get('/__sitemap-probe', fn () => 'ok')->name('sitemap.probe');

    $path = public_path('sitemap.xml');
    if (file_exists($path)) {
        @unlink($path);
    }

    $this->artisan('sitemap')->assertSuccessful();

    expect(file_exists($path))->toBeTrue();
    expect(file_get_contents($path))->toContain('<urlset');

    @unlink($path);
});

it('excludes api and internal routes from the sitemap', function () {
    Route::get('/__sitemap-public', fn () => 'ok');

    $path = public_path('sitemap.xml');
    @unlink($path);

    $this->artisan('sitemap')->assertSuccessful();

    $xml = file_get_contents($path);
    expect($xml)->not->toContain('/livewire')
        ->and($xml)->not->toContain('/api');

    @unlink($path);
});

it('scaffolds a repository class with make:repository', function () {
    $target = app_path('Repositories/GeneratedProbeRepository.php');
    @unlink($target);

    $this->artisan('make:repository', ['name' => 'GeneratedProbeRepository'])->assertSuccessful();

    expect(file_exists($target))->toBeTrue();

    @unlink($target);
});
