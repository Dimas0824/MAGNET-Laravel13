<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Widen kontrak_magang.status to add the terminal 'selesai' state.
     *
     * Uses ->change() (not a raw MODIFY) so doctrine/dbal-free Laravel schema
     * diffing emits the correct ALTER while preserving the column's position
     * (after('waktu_akhir')) and default.
     */
    public function up(): void
    {
        Schema::table('kontrak_magang', function (Blueprint $table) {
            $table->enum('status', ['menunggu_persetujuan', 'disetujui', 'ditolak', 'selesai'])
                ->default('menunggu_persetujuan')->after('waktu_akhir')->change();
        });
    }

    /**
     * Shrink the enum back to its original domain. Any 'selesai' rows must be
     * remapped first, otherwise the ALTER would truncate them.
     */
    public function down(): void
    {
        DB::table('kontrak_magang')->where('status', 'selesai')->update(['status' => 'disetujui']);

        Schema::table('kontrak_magang', function (Blueprint $table) {
            $table->enum('status', ['menunggu_persetujuan', 'disetujui', 'ditolak'])
                ->default('menunggu_persetujuan')->after('waktu_akhir')->change();
        });
    }
};
