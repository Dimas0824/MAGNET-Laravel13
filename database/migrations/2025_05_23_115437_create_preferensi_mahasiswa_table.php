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
        Schema::create('preferensi_mahasiswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mahasiswa_id')->constrained('mahasiswa')->onDelete('cascade');
            $table->foreignId('skil')->constrained('kriteria')->onDelete('cascade');
            $table->foreignId('bidang_industri')->constrained('kriteria')->onDelete('cascade');
            $table->foreignId('lokasi')->constrained('kriteria')->onDelete('cascade');
            $table->foreignId('uang_saku')->constrained('kriteria')->onDelete('cascade');
            $table->foreignId('open_remote')->constrained('kriteria')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('preferensi_mahasiswa');
    }
};
