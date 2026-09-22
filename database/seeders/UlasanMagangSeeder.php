<?php

namespace Database\Seeders;

use App\Models\UlasanMagang;
use Illuminate\Database\Seeder;

class UlasanMagangSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        UlasanMagang::factory()->count(100)->create();
    }
}
