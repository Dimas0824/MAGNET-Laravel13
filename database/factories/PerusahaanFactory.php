<?php

namespace Database\Factories;

use App\Models\Perusahaan;
use App\Models\BidangIndustri;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Perusahaan>
 */
class PerusahaanFactory extends Factory
{
    protected $model = Perusahaan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $bidangIndustriIds = BidangIndustri::where('nama', '!=', 'Semua')
                ->orderBy('id')
                ->pluck('id')
                ->toArray();

        return [
            'nama' => $this->faker->company(),
            'bidang_industri_id' => $this->faker->randomElement($bidangIndustriIds),
            'lokasi' => $this->faker->address(),
            'kategori' => $this->faker->randomElement(['mitra', 'non_mitra']),
            'rating' => $this->faker->optional()->randomFloat(1, 0, 5),
            'website' => $this->faker->url(),
            'deskripsi' => $this->faker->paragraph()
        ];
    }
}
