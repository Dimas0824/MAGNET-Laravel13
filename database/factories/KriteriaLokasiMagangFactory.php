<?php

namespace Database\Factories;

use App\Helpers\DecisionMaking\ROC;
use App\Models\KriteriaLokasiMagang;
use App\Models\LokasiMagang;
use App\Models\Mahasiswa;
use App\Traits\BaseKriteriaFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KriteriaLokasiMagang>
 */
class KriteriaLokasiMagangFactory extends Factory
{
    use BaseKriteriaFactory;

    protected $model = KriteriaLokasiMagang::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {

        $lokasiIds = LokasiMagang::orderBy('id')->pluck('id')->toArray();
        $mahasiswaIds = Mahasiswa::orderBy('id')->pluck('id')->toArray();

        $rank = $this->faker->numberBetween(1, config('recommendation-system.roc.total_criteria'));

        return [
            'lokasi_magang_id' => $this->faker->randomElement($lokasiIds),
            'mahasiswa_id' => $this->faker->randomElement($mahasiswaIds),
        ];
    }

    public function configure()
    {
        return $this->afterMaking(function ($model) {
            $rank = $this->faker->numberBetween(1, config('recommendation-system.roc.total_criteria'));
            $model->forceFill([
                'rank' => $rank,
                'bobot' => ROC::getWeight($rank, config('recommendation-system.roc.total_criteria')),
            ]);
        });
    }
}
