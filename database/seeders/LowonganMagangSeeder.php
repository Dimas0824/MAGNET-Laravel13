<?php

namespace Database\Seeders;

use App\Models\LowonganMagang;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class LowonganMagangSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Storage::put(config('recommendation-system.preprocessing.alternatives_categorized_path'), '');

        LowonganMagang::factory()->count(20)->create();
    }
}
