<?php

namespace Database\Factories;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Mahasiswa;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Mahasiswa>
 */
class MahasiswaFactory extends Factory
{
    protected $model = Mahasiswa::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $length = $this->faker->randomElement([10, 11]);

        return [
            'tenant_id' => BelongsToTenant::defaultTenantId(),
            'nama' => $this->faker->name(),
            'nim' => (string) $this->faker->unique()->numerify(str_repeat('#', $length)),
            'email' => $this->faker->unique()->safeEmail(),
            'angkatan' => $this->faker->numberBetween(20, 30),
            'jenis_kelamin' => $this->faker->randomElement(['L', 'P']),
            'tanggal_lahir' => $this->faker->dateTimeBetween('-23 years', '-17 years')->format('Y-m-d'),
            'jurusan' => 'Teknologi Informasi',
            'program_studi' => $this->faker->randomElement([
                'D4 Teknik Informatika', 'D4 Sistem Informasi Bisnis', 'D2 Pengembangan Piranti Lunak Situs',
            ]),
            'alamat' => $this->faker->address(),
        ];
    }

    public function configure()
    {
        return $this->afterMaking(function ($model) {
            $model->forceFill([
                'password' => Hash::make('mahasiswa123'),
                'status_magang' => $this->faker->randomElement(['belum magang', 'sedang magang', 'selesai magang']),
            ]);
        });
    }
}
