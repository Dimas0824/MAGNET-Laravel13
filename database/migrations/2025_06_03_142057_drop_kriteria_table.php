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
        Schema::table('preferensi_mahasiswa', function (Blueprint $table) {
            $table->dropForeign(['skil']);
            $table->dropForeign(['bidang_industri']);
            $table->dropForeign(['lokasi']);
            $table->dropForeign(['uang_saku']);
            $table->dropForeign(['open_remote']);

            $table->dropColumn(['skil', 'lokasi', 'uang_saku', 'bidang_industri', 'open_remote']);
        });

        Schema::dropIfExists('kriteria');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('kriteria', function (Blueprint $table) {
            $table->id();
            $table->enum('nama_kriteria', [
                'skil',
                'bidang industri',
                'open remote',
                'lokasi',
                'uang saku',
            ]);
            $table->string('nilai');
            $table->float('nilai_numerik');
            $table->integer('rank');
            $table->timestamps();
        });

        Schema::table('preferensi_mahasiswa', function (Blueprint $table) {
            $table->foreignId('skil')->constrained('kriteria')->onDelete('cascade');
            $table->foreignId('bidang_industri')->constrained('kriteria')->onDelete('cascade');
            $table->foreignId('lokasi')->constrained('kriteria')->onDelete('cascade');
            $table->foreignId('uang_saku')->constrained('kriteria')->onDelete('cascade');
            $table->foreignId('open_remote')->constrained('kriteria')->onDelete('cascade');
        });
    }
};
