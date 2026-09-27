<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class GenerateSitemap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sitemap';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a sitemap based on this project';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $sitemap = Sitemap::create();

        foreach (Route::getRoutes() as $route) {
            if (in_array('GET', $route->methods()) && $route->uri() !== null) {
                $uri = '/'.ltrim($route->uri(), '/');

                if (
                    str_contains($uri, 'sanctum') ||
                    str_contains($uri, 'api') ||
                    str_contains($uri, 'flux') ||
                    str_contains($uri, 'livewire') ||
                    $uri == '/_ignition/health-check'
                ) {
                    continue;
                }

                $sitemap->add(Url::create(url($uri)));
            }
        }

        $sitemap->writeToFile(public_path('sitemap.xml'));

        echo 'Sitemap successfully generated! File output is in public directory';
    }
}
