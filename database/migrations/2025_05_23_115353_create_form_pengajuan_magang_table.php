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
        Schema::create('form_pengajuan_magang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengajuan_id')->constrained('berkas_pengajuan_magang')->onDelete('cascade');
            $table->enum('status', ['diproses', 'diterima', 'ditolak'])->default('diproses');
            $table->string('keterangan', 100)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_pengajuan_magang');
    }
};
