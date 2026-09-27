<?php

namespace Database\Seeders;

use App\Models\FormPengajuanMagang;
use Illuminate\Database\Seeder;

class FormPengajuanMagangSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        FormPengajuanMagang::factory()->count(15)->create();
    }
}
