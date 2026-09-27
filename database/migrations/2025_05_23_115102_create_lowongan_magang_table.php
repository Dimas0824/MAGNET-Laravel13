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
        Schema::create('lowongan_magang', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100);
            $table->integer('kuota');
            $table->text('deskripsi');
            $table->text('persyaratan');
            $table->enum('jenis_magang', ['berbayar', 'tidak berbayar']);
            $table->enum('open_remote', ['ya', 'tidak']);
            $table->enum('status', ['buka', 'tutup'])->default('buka');
            $table->string('lokasi', 100);
            $table->foreignId('perusahaan_id')->constrained('perusahaan');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lowongan_magang');
    }
};
