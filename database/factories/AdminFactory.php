<?php

namespace Database\Factories;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Admin>
 */
class AdminFactory extends Factory
{
    protected $model = Admin::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => $this->faker->name(),
            'nip' => (string) $this->faker->unique()->numerify('##################'),
        ];
    }

    public function configure()
    {
        return $this->afterMaking(function ($model) {
            $model->forceFill(['password' => Hash::make('admin123')]);
        });
    }
}
