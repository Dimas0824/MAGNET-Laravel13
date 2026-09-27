<?php

namespace App\Actions\Kontrak;

use App\Actions\Action;
use App\Models\KontrakMagang;
use App\Models\Mahasiswa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Approve a pending kontrak magang and move the mahasiswa into the
 * "sedang magang" state.
 *
 * Extracted from the approveContract() closure on the admin
 * pembaruan-status-magang detail Volt page. The caller (the Volt page) owns
 * validation, flash messages and the Flux modals; this action only performs
 * the domain write and its logging.
 *
 * The two writes (kontrak_magang + mahasiswa) stay inside a single
 * DB::transaction, exactly as before.
 */
class ApproveKontrakMagang implements Action
{
    /**
     * @param  int  $kontrakId  kontrak_magang.id being approved
     * @param  int  $dosenId  selected dosen pembimbing id to attach
     * @param  string  $keterangan  final keterangan text (already resolved by the caller)
     */
    public function handle(int $kontrakId = 0, int $dosenId = 0, string $keterangan = ''): KontrakMagang
    {
        return DB::transaction(function () use ($kontrakId, $dosenId, $keterangan) {
            $kontrak = KontrakMagang::findOrFail($kontrakId);

            $kontrak->update([
                'status' => 'disetujui',
                'dosen_id' => $dosenId,
                'keterangan' => $keterangan,
            ]);

            Log::info('Approve Contract - Hasil update kontrak:', [
                'contract_id' => $kontrak->id,
                'rows_affected' => 1,
            ]);

            $mahasiswa = Mahasiswa::findOrFail($kontrak->mahasiswa_id);

            // Direct assignment (not mass-assignment) so status_magang is
            // written even though it is not in the model's $fillable list —
            // matching the previous query-builder update exactly.
            $mahasiswa->status_magang = 'sedang magang';
            $mahasiswa->save();

            Log::info('Approve Contract - Hasil update mahasiswa:', [
                'mahasiswa_id' => $mahasiswa->id,
                'rows_affected' => 1,
            ]);

            return $kontrak;
        });
    }
}
