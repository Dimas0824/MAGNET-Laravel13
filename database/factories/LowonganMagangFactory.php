<?php

namespace Database\Factories;

use App\Models\LokasiMagang;
use App\Models\LowonganMagang;
use App\Models\Pekerjaan;
use App\Models\Perusahaan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LowonganMagang>
 */
class LowonganMagangFactory extends Factory
{
    protected $model = LowonganMagang::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $pekerjaanIds = Pekerjaan::where('nama', '!=', 'Semua')
            ->pluck('id')
            ->toArray();
        $lokasiIds = LokasiMagang::where('kategori_lokasi', '!=', 'Semua')
            ->pluck('id')
            ->toArray();
        $perusahaanIds = Perusahaan::pluck('id')->toArray();

        return [
            'kuota' => $this->faker->numberBetween(1, 50),
            'pekerjaan_id' => $this->faker->randomElement($pekerjaanIds),
            'deskripsi' => $this->faker->paragraph(),
            'persyaratan' => $this->faker->paragraph(),
            'jenis_magang' => $this->faker->randomElement(['berbayar', 'tidak berbayar']),
            'open_remote' => $this->faker->randomElement(['ya', 'tidak']),
            'status' => $this->faker->randomElement(['buka', 'tutup']),
            'lokasi_magang_id' => $this->faker->randomElement($lokasiIds),
            'perusahaan_id' => $this->faker->randomElement($perusahaanIds)
        ];
    }
}
