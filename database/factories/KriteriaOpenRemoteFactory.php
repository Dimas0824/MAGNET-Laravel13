<?php

namespace Database\Factories;

use App\Helpers\DecisionMaking\ROC;
use App\Models\KriteriaOpenRemote;
use App\Models\Mahasiswa;
use App\Traits\BaseKriteriaFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\KriteriaOpenRemote>
 */
class KriteriaOpenRemoteFactory extends Factory
{
    use BaseKriteriaFactory;

    protected $model = KriteriaOpenRemote::class;

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
            'open_remote' => $this->faker->randomElement(['ya', 'tidak']),
            'mahasiswa_id' => $this->faker->randomElement($mahasiswaIds),
            'rank' => $rank,
            'bobot' => ROC::getWeight($rank, config('recommendation-system.roc.total_criteria')),
        ];
    }

    public function configure()
    {
        return $this->withBobotCalculation();
    }
}
