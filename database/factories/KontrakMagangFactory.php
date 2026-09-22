<?php

namespace Database\Factories;

use App\Models\DosenPembimbing;
use App\Models\KontrakMagang;
use App\Models\LowonganMagang;
use App\Models\Mahasiswa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\KontrakMagang>
 */
class KontrakMagangFactory extends Factory
{
    protected $model = KontrakMagang::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {

        $mahasiswaIds = Mahasiswa::orderBy('id')->pluck('id')->toArray();
        $dosenIds = DosenPembimbing::orderBy('id')->pluck('id')->toArray();
        $lowonganIds = LowonganMagang::orderBy('id')->pluck('id')->toArray();

        $startDate = $this->faker->dateTimeBetween('-1 year', 'now');
        $finishDate = $this->faker->dateTimeBetween($startDate, '+1 year');

        return [
            'mahasiswa_id' => $this->faker->randomElement($mahasiswaIds),
            'dosen_id' => $this->faker->randomElement($dosenIds),
            'lowongan_magang_id' => $this->faker->randomElement($lowonganIds),
            'waktu_awal' => $startDate,
            'waktu_akhir' => $finishDate,
        ];
    }
}
