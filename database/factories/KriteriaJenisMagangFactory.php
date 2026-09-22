<?php

namespace Database\Factories;

use App\Helpers\DecisionMaking\ROC;
use App\Models\KriteriaJenisMagang;
use App\Models\Mahasiswa;
use App\Traits\BaseKriteriaFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\KriteriaJenisMagang>
 */
class KriteriaJenisMagangFactory extends Factory
{
    use BaseKriteriaFactory;

    protected $model = KriteriaJenisMagang::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $mahasiswaIds = Mahasiswa::orderBy('id')->pluck('id')->toArray();

        $rank = $this->faker->numberBetween(1, config('recommendation-system.roc.total_criteria'));

        return [
            'jenis_magang' => $this->faker->randomElement(['berbayar', 'tidak berbayar']),
            'mahasiswa_id' => $this->faker->randomElement($mahasiswaIds),
        ];
    }

    public function configure()
    {
        return $this->afterMaking(function ($model) {
            $rank = $this->faker->numberBetween(1, config('recommendation-system.roc.total_criteria'));
            $model->forceFill([
                'rank' => $rank,
                'bobot' => \App\Helpers\DecisionMaking\ROC::getWeight($rank, config('recommendation-system.roc.total_criteria')),
            ]);
        });
    }
}
