<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\DosenPembimbing;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DosenPembimbing>
 */
class DosenPembimbingFactory extends Factory
{
    protected $model = DosenPembimbing::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => $this->faker->name(),
            'nidn' => (string) $this->faker->unique()->numerify(str_repeat('#', 10)),
            'jenis_kelamin' => $this->faker->randomElement(['L', 'P']),
            'foto' => $this->faker->imageUrl(640, 480, 'people', true, 'Dosen Pembimbing', true),
        ];
    }
    public function configure()
    {
        return $this->afterMaking(function ($model) {
            $model->forceFill(['password' => Hash::make('dosen123')]);
        });
    }
}
