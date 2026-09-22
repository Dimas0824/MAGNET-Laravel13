<?php

namespace Database\Factories;

use App\Models\KontrakMagang;
use App\Models\UmpanBalikMagang;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UmpanBalikMagang>
 */
class UmpanBalikMagangFactory extends Factory
{
    protected $model = UmpanBalikMagang::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {

        $kontrakMagangIds = KontrakMagang::orderBy('id')->pluck('id')->toArray();

        return [
            'kontrak_magang_id' => $this->faker->randomElement($kontrakMagangIds),
            'komentar' => $this->faker->sentence(2),
            'tanggal' => $this->faker->dateTimeBetween('-1 years', '+1 years')->format('Y-m-d'),
        ];
    }
}
