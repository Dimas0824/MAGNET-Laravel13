<?php

namespace Database\Factories;

use App\Models\KontrakMagang;
use App\Models\UlasanMagang;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UlasanMagang>
 */
class UlasanMagangFactory extends Factory
{
    protected $model = UlasanMagang::class;

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
            'rating' => $this->faker->numberBetween(1, 5),
            'komentar' => $this->faker->sentence(),
        ];
    }
}
