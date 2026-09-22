<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('perusahaan', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->enum('bidang_industri', [
                'Perbankan',
                'Kesehatan',
                'Pendidikan',
                'E-Commerce',
                'Telekomunikasi',
                'Transportasi',
                'Pemerintahan',
                'Manufaktur',
                'Energi',
                'Media',
                'Teknologi',
                'Agrikultur',
                'Pariwisata',
                'Keamanan',
            ]);
            $table->string('lokasi', 100);
            $table->enum('kategori', ['mitra', 'non_mitra']);
            $table->float('rating')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('perusahaan');
    }
};
