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
        Schema::create('mahasiswa', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('nim', 11)->unique();
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->integer('angkatan');
            $table->enum('jenis_kelamin', ['L', 'P']);
            $table->date('tanggal_lahir');
            $table->string('jurusan')->default('Teknologi Informasi');
            $table->enum('program_studi', [
                'D4 Teknik Informatika', 'D4 Sistem Informasi Bisnis', 'D2 Pengembangan Piranti Lunak Situs',
            ]);
            $table->enum('status_magang', [
                'belum magang', 'sedang magang', 'selesai magang',
            ])->default('belum magang');
            $table->text('alamat');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mahasiswa');
    }
};
