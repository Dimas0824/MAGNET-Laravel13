<?php

namespace App\Services;

use App\Models\BerkasPengajuanMagang;
use App\Models\FormPengajuanMagang;
use App\Models\Mahasiswa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Handles file storage + persistence for a mahasiswa's pengajuan magang.
 *
 * Owns the PII document lifecycle: replacing any previous submission,
 * storing CV/transcript/portfolio on the private disk, and creating the
 * berkas + form records inside a DB transaction.
 */
class BerkasPengajuanService
{
    /**
     * Private disk used to store PII documents (CV, transcript, portfolio).
     */
    private const DISK = 'private';

    /**
     * Persist a new pengajuan submission: replace any previous berkas, store
     * the uploaded files on the private disk, then create the DB records.
     *
     * @param  array{cv?: \Illuminate\Http\UploadedFile, transkrip_nilai?: \Illuminate\Http\UploadedFile, portfolio?: \Illuminate\Http\UploadedFile}  $files
     */
    public function handle(Mahasiswa $mahasiswa, array $files): BerkasPengajuanMagang
    {
        $this->deleteExistingSubmission($mahasiswa);

        $this->ensureDirectoryExists('pengajuan-magang/cv');
        $this->ensureDirectoryExists('pengajuan-magang/transkrip');
        $this->ensureDirectoryExists('pengajuan-magang/portfolio');

        $cvFileName = $this->generateFileName($mahasiswa, 'cv');
        $transkripFileName = $this->generateFileName($mahasiswa, 'transkrip');

        $cvPath = $files['cv']->storeAs('pengajuan-magang/cv', $cvFileName, self::DISK);
        $transkripPath = $files['transkrip_nilai']->storeAs('pengajuan-magang/transkrip', $transkripFileName, self::DISK);

        $portfolioPath = null;
        if (isset($files['portfolio'])) {
            $portfolioFileName = $this->generateFileName($mahasiswa, 'portfolio');
            $portfolioPath = $files['portfolio']->storeAs('pengajuan-magang/portfolio', $portfolioFileName, self::DISK);
        }

        return DB::transaction(function () use ($mahasiswa, $cvPath, $transkripPath, $portfolioPath) {
            $berkas = BerkasPengajuanMagang::create([
                'mahasiswa_id' => $mahasiswa->id,
                'cv' => $cvPath,
                'transkrip_nilai' => $transkripPath,
                'portfolio' => $portfolioPath,
            ]);

            FormPengajuanMagang::forceCreate([
                'pengajuan_id' => $berkas->id,
                'status' => 'diproses',
                'keterangan' => 'Dokumen telah dikirim, diproses review admin',
            ]);

            $this->updateStatusPengajuan($mahasiswa->id);

            return $berkas;
        });
    }

    /**
     * Delete any previous berkas (and its related form + files) for the mahasiswa.
     */
    private function deleteExistingSubmission(Mahasiswa $mahasiswa): void
    {
        $existing = BerkasPengajuanMagang::where('mahasiswa_id', $mahasiswa->id)->first();

        if (! $existing) {
            return;
        }

        FormPengajuanMagang::where('pengajuan_id', $existing->id)->delete();

        foreach (['cv', 'transkrip_nilai', 'portfolio'] as $file) {
            if ($existing->$file && Storage::disk(self::DISK)->exists($existing->$file)) {
                Storage::disk(self::DISK)->delete($existing->$file);
            }
        }

        $existing->delete();
    }

    /**
     * Update status pengajuan magang ke 'diproses'
     */
    public function updateStatusPengajuan($mahasiswaId)
    {
        try {
            // Cari berkas pengajuan mahasiswa
            $berkas = BerkasPengajuanMagang::where('mahasiswa_id', $mahasiswaId)->latest()->first();

            if ($berkas) {
                // Update status form pengajuan menjadi 'diproses'
                FormPengajuanMagang::where('pengajuan_id', $berkas->id)
                    ->update([
                        'status' => 'diproses',
                        'keterangan' => 'Dokumen telah dikirim, diproses review admin',
                        'updated_at' => now(),
                    ]);

                Log::info('Status pengajuan updated to diproses', [
                    'mahasiswa_id' => $mahasiswaId,
                    'berkas_id' => $berkas->id,
                ]);

                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Error updating status pengajuan', [
                'mahasiswa_id' => $mahasiswaId,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Buat direktori jika belum ada
     */
    private function ensureDirectoryExists($path)
    {
        $fullPath = Storage::disk(self::DISK)->path($path);

        if (! is_dir($fullPath)) {
            mkdir($fullPath, 0755, true);
            Log::info('Directory created: '.$fullPath);
        }

        return $fullPath;
    }

    /**
     * Generate nama file yang aman
     */
    private function generateFileName($mahasiswa, $type, $extension = 'pdf')
    {
        $date = now()->format('Y-m-d');
        $name = preg_replace('/[^a-z0-9_]/', '', str_replace(' ', '_', strtolower($mahasiswa->nama)));
        $token = Str::lower(Str::random(8));

        return "{$type}_{$date}_{$name}_{$token}.{$extension}";
    }
}
