<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kontrak_magang', function (Blueprint $table) {
            $table->enum('status', ['menunggu_persetujuan', 'disetujui', 'ditolak'])
                ->default('menunggu_persetujuan')
                ->after('waktu_akhir');
            $table->string('keterangan')->nullable()
                ->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('kontrak_magang', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
