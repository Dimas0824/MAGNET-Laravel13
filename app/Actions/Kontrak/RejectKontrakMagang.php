<?php

namespace App\Actions\Kontrak;

use App\Actions\Action;
use App\Models\KontrakMagang;
use App\Models\Mahasiswa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reject a pending kontrak magang and return the mahasiswa to the
 * "belum magang" state.
 *
 * Extracted from the rejectContract() closure on the admin
 * pembaruan-status-magang detail Volt page. The caller (the Volt page) owns
 * validation, flash messages and the Flux modals; this action only performs
 * the domain write and its logging.
 *
 * The two writes (kontrak_magang + mahasiswa) stay inside a single
 * DB::transaction, exactly as before.
 */
class RejectKontrakMagang implements Action
{
    /**
     * @param  int  $kontrakId  kontrak_magang.id being rejected
     * @param  string  $keterangan  final rejection keterangan text (already resolved by the caller)
     */
    public function handle(int $kontrakId = 0, string $keterangan = ''): KontrakMagang
    {
        return DB::transaction(function () use ($kontrakId, $keterangan) {
            $kontrak = KontrakMagang::findOrFail($kontrakId);

            $kontrak->update([
                'status' => 'ditolak',
                'keterangan' => $keterangan,
            ]);

            Log::info('Reject Contract - Hasil update kontrak:', [
                'contract_id' => $kontrak->id,
                'rows_affected' => 1,
            ]);

            $mahasiswa = Mahasiswa::findOrFail($kontrak->mahasiswa_id);

            // Direct assignment (not mass-assignment) so status_magang is
            // written even though it is not in the model's $fillable list —
            // matching the previous query-builder update exactly.
            $mahasiswa->status_magang = 'belum magang';
            $mahasiswa->save();

            Log::info('Reject Contract - Hasil update mahasiswa:', [
                'mahasiswa_id' => $mahasiswa->id,
                'rows_affected' => 1,
            ]);

            return $kontrak;
        });
    }
}
