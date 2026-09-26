<?php

namespace Database\Seeders;

use App\Models\BerkasPengajuanMagang;
use Illuminate\Database\Seeder;

class BerkasPengajuanMagangSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        BerkasPengajuanMagang::factory()->count(15)->create();
    }
}
