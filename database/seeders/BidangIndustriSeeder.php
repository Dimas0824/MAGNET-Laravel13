<?php

namespace Database\Seeders;

use App\Models\BidangIndustri;
use Illuminate\Database\Seeder;

class BidangIndustriSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sectors = [
            'Semua',
            'Perbankan',
            'Kesehatan',
            'Pendidikan',
            'E-Commerce',
            'Telekomunikasi',
            'Transportasi',
            'Pemerintahan',
            'Manufaktur',
            'Energi',
            'Media',
            'Teknologi',
            'Agrikultur',
            'Pariwisata',
            'Keamanan',
        ];

        foreach ($sectors as $sector) {
            BidangIndustri::create([
                'nama' => $sector,
            ]);
        }
    }
}
