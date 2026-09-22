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
        // Fall back to creating the required relations so the factory is
        // self-sufficient (previously crashed when none existed).
        $mahasiswaId = Mahasiswa::query()->inRandomOrder()->value('id')
            ?? Mahasiswa::factory()->create()->id;
        $dosenId = DosenPembimbing::query()->inRandomOrder()->value('id')
            ?? DosenPembimbing::factory()->create()->id;
        $lowonganId = LowonganMagang::query()->inRandomOrder()->value('id')
            ?? lowonganMagang()->id;

        $startDate = $this->faker->dateTimeBetween('-1 year', 'now');
        $finishDate = $this->faker->dateTimeBetween($startDate, '+1 year');

        return [
            'mahasiswa_id' => $mahasiswaId,
            'dosen_id' => $dosenId,
            'lowongan_magang_id' => $lowonganId,
            'waktu_awal' => $startDate,
            'waktu_akhir' => $finishDate,
        ];
    }
}
