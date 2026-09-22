<?php

namespace Database\Factories;

use App\Models\BerkasPengajuanMagang;
use App\Models\Mahasiswa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BerkasPengajuanMagang>
 */
class BerkasPengajuanMagangFactory extends Factory
{
    protected $model = BerkasPengajuanMagang::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $mahasiswaIds = Mahasiswa::orderBy('id')->pluck('id')->toArray();

        return [
            'mahasiswa_id' => $this->faker->randomElement($mahasiswaIds),
            'cv' => $this->faker->unique()->lexify('cv_?????').'.pdf',
            'transkrip_nilai' => $this->faker->unique()->lexify('transkrip_nilai_cv_?????').'.pdf',
            'portfolio' => $this->faker->unique()->optional()->lexify('portfolio_?????').'.pdf',
        ];
    }
}
