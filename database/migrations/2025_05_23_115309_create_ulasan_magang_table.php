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
        Schema::create('ulasan_magang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kontrak_magang_id')->constrained('kontrak_magang')->onDelete('cascade');
            $table->integer('rating');
            $table->text('komentar');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ulasan_magang');
    }
};
