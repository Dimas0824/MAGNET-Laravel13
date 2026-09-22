<?php

namespace Database\Seeders;

use App\Models\LokasiMagang;
use Illuminate\Database\Seeder;

class LokasiMagangSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            'Semua' => ['Semua lokasi'],
            'Area Malang Raya' => [
                'Lowokwaru, Kota Malang, Jawa Timur',
                'Singosari, Kabupaten Malang, Jawa Timur',
                'Klojen, Kota Malang, Jawa Timur',
                'Kedungkandang, Kota Malang, Jawa Timur',
                'Blimbing, Kota Malang, Jawa Timur',
            ],
            'Luar area Malang Raya (dalam Jawa Timur)' => [
                'Kertosono, Kabupaten Nganjuk, Jawa Timur',
                'Jatirejo, Kabupaten Mojokerto, Jawa Timur',
                'Jetis, Kabupaten Mojokerto, Jawa Timur',
                'Jogoroto, Kabupaten Jombang, Jawa Timur',
                'Bungah, Kabupaten Gresik, Jawa Timur',
            ],
            'Luar provinsi Jawa Timur' => [
                'Semarang, Jawa Tengah',
                'Yogyakarta, Daerah Istimewa Yogyakarta',
                'Bandung, Jawa Barat',
                'Jakarta Selatan, DKI Jakarta',
                'Bogor, Jawa Barat',
            ],
            'Luar negeri' => [
                'Tokyo, Jepang',
                'Butik Batok, Singapura',
                'Kuala Lumpur, Malaysia',
                'Bangkok, Thailand',
                'Seoul, Korea Selatan',
            ],
        ];

        foreach ($locations as $kategori => $locs) {
            foreach ($locs as $loc) {
                LokasiMagang::create([
                    'kategori_lokasi' => $kategori,
                    'lokasi' => $loc,
                ]);
            }
        }
    }
}
