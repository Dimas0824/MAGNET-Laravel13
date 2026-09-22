<?php

namespace Database\Factories;

use App\Helpers\DecisionMaking\ROC;
use App\Models\KriteriaPekerjaan;
use App\Models\Mahasiswa;
use App\Models\Pekerjaan;
use App\Traits\BaseKriteriaFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\KriteriaPekerjaan>
 */
class KriteriaPekerjaanFactory extends Factory
{
    use BaseKriteriaFactory;

    protected $model = KriteriaPekerjaan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $pekerjaanIds = Pekerjaan::orderBy('id')->pluck('id')->toArray();
        $mahasiswaIds = Mahasiswa::orderBy('id')->pluck('id')->toArray();

        $rank = $this->faker->numberBetween(1, config('recommendation-system.roc.total_criteria'));

        return [
            'pekerjaan_id' => $this->faker->randomElement($pekerjaanIds),
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
