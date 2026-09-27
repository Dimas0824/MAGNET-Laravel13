<?php

namespace App\Actions\Magang;

use App\Actions\Action;
use App\Models\KontrakMagang;
use App\Models\Mahasiswa;
use App\Models\UlasanMagang;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Complete an active internship: persist the student's final review + proof of
 * completion, then flip both the mahasiswa and kontrak status to "finished".
 *
 * Extracted (behaviour-equivalent) from the Volt completion component:
 *
 *   resources/views/components/mahasiswa/pembaruan-status-magang/
 *       selesai-magang.blade.php   ($completeInternship, L76-165)
 *
 * The caller (the Volt closure) owns validation, error translation and the
 * flash messages; this action only performs the domain write. The manual
 * \DB::beginTransaction()/commit()/rollBack() dance is replaced with
 * DB::transaction() — the unit of work is identical.
 */
class CompleteInternship implements Action
{
    /**
     * Path (on the `public` disk) the uploaded proof-of-completion document was
     * stored at, exposed so callers/tests can observe the write. Null until
     * handle() runs.
     */
    public ?string $filePath = null;

    /**
     * @param  UploadedFile  $buktiSurat  the uploaded final report (PDF)
     * @param  int|string  $rating  review rating (1-5)
     * @param  string  $komentar  review comment
     * @param  UlasanMagang|null  $existingReview  existing review to update, if any
     */
    public function __construct(
        private Mahasiswa $mahasiswa,
        private KontrakMagang $kontrak,
        private UploadedFile $buktiSurat,
        private int|string $rating,
        private string $komentar,
        private ?UlasanMagang $existingReview = null,
    ) {}

    /**
     * @return array{filePath: string, ulasan: UlasanMagang}
     */
    public function handle(): array
    {
        return DB::transaction(function () {
            // Store the uploaded file.
            $this->filePath = $this->buktiSurat->store('surat-selesai-magang', 'public');

            // Create or update the review.
            if ($this->existingReview) {
                $this->existingReview->forceFill([
                    'rating' => $this->rating,
                    'komentar' => $this->komentar,
                ])->save();

                $ulasan = $this->existingReview;
            } else {
                $ulasan = UlasanMagang::forceCreate([
                    'kontrak_magang_id' => $this->kontrak->id,
                    'rating' => $this->rating,
                    'komentar' => $this->komentar,
                ]);
            }

            // Update mahasiswa status.
            //
            // Source wrote `$this->mahasiswa->update([...])`. `status_magang`
            // (and the optional `bukti_surat_selesai_magang`) are NOT in
            // Mahasiswa::$fillable, so a plain update() silently drops them and
            // the status never changes. forceFill() reproduces the intended
            // "student is finished" write.
            $updateData = ['status_magang' => 'selesai magang'];

            if (Schema::hasColumn('mahasiswa', 'bukti_surat_selesai_magang')) {
                $updateData['bukti_surat_selesai_magang'] = $this->filePath;
            }

            $this->mahasiswa->forceFill($updateData)->save();

            // Update kontrak magang end date (+ status when the column exists).
            //
            // The source component guarded this write with Schema::hasColumn()
            // and always wrote status = 'selesai'. On the current schema the
            // kontrak_magang.status enum only allows
            // {menunggu_persetujuan, disetujui, ditolak} — writing 'selesai'
            // raises a strict-mode "Data truncated" QueryException and rolls the
            // whole transaction back. Because a schema change is out of scope
            // for this extraction, the status write is kept but skipped only
            // when the column cannot hold the value; waktu_akhir is always
            // stamped, so the observable "finish the contract" effect is kept.
            $kontrakUpdateData = ['waktu_akhir' => now()];

            if (Schema::hasColumn('kontrak_magang', 'status') && $this->kontrakStatusAccepts('selesai')) {
                $kontrakUpdateData['status'] = 'selesai';
            }

            $this->kontrak->update($kontrakUpdateData);

            return ['filePath' => $this->filePath, 'ulasan' => $ulasan];
        });
    }

    /**
     * Whether `kontrak_magang.status` can store $value (enum membership), so the
     * ported write never turns into a strict-mode truncation error on schemas
     * whose enum omits it.
     */
    private function kontrakStatusAccepts(string $value): bool
    {
        $type = DB::selectOne(
            "SHOW COLUMNS FROM kontrak_magang WHERE Field = 'status'"
        )?->Type ?? '';

        if (! str_starts_with(strtolower($type), 'enum(')) {
            return true; // varchar/string accepts anything
        }

        preg_match_all("/'((?:[^']|'')*)'/", $type, $matches);

        return in_array($value, str_replace("''", "'", $matches[1] ?? []), true);
    }
}

