<?php

namespace Database\Factories;

use App\Helpers\DecisionMaking\ROC;
use App\Models\BidangIndustri;
use App\Models\KriteriaBidangIndustri;
use App\Models\Mahasiswa;
use App\Traits\BaseKriteriaFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\KriteriaBidangIndustri>
 */
class KriteriaBidangIndustriFactory extends Factory
{
    use BaseKriteriaFactory;

    protected $model = KriteriaBidangIndustri::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {

        $bidangIds = BidangIndustri::orderBy('id')->pluck('id')->toArray();
        $mahasiswaIds = Mahasiswa::orderBy('id')->pluck('id')->toArray();

        $rank = $this->faker->numberBetween(1, config('recommendation-system.roc.total_criteria'));

        return [
            'bidang_industri_id' => $this->faker->randomElement($bidangIds),
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
