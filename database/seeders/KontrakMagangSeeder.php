<?php

namespace Database\Seeders;

use App\Models\KontrakMagang;
use Illuminate\Database\Seeder;

class KontrakMagangSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        KontrakMagang::factory()->count(12)->create();
    }
}
