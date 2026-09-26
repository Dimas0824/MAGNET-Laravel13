<?php

namespace App\Http\Controllers;

use App\Models\BerkasPengajuanMagang;
use App\Models\FormPengajuanMagang;
use App\Models\KontrakMagang;
use App\Models\Mahasiswa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PengajuanMagangController extends Controller
{
    /**
     * Private disk used to store PII documents (CV, transcript, portfolio).
     */
    private const DISK = 'private';

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

    /**
     * Update status pengajuan magang ke 'diproses'
     */
    private function updateStatusPengajuan($mahasiswaId)
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
     * Public method untuk mengubah status ke diproses
     */
    public function setStatusdiproses($mahasiswaId)
    {
        return $this->updateStatusPengajuan($mahasiswaId);
    }

    public function storePengajuan(Request $request)
    {
        try {
            // Validasi input
            $validated = $request->validate([
                'cv' => 'required|file|mimes:pdf|max:2048',
                'transkrip_nilai' => 'required|file|mimes:pdf|max:2048',
                'portfolio' => 'nullable|file|mimes:pdf|max:2048',
            ], [
                'cv.required' => 'CV wajib diupload.',
                'cv.file' => 'CV harus berupa file.',
                'cv.mimes' => 'CV harus berupa file PDF.',
                'cv.max' => 'Ukuran file CV maksimal 2 MB.',
                'transkrip_nilai.required' => 'Transkrip nilai wajib diupload.',
                'transkrip_nilai.file' => 'Transkrip nilai harus berupa file.',
                'transkrip_nilai.mimes' => 'Transkrip nilai harus berupa file PDF.',
                'transkrip_nilai.max' => 'Ukuran file transkrip maksimal 2 MB.',
                'portfolio.file' => 'Portofolio harus berupa file.',
                'portfolio.mimes' => 'Portofolio harus berupa file PDF.',
                'portfolio.max' => 'Ukuran file portofolio maksimal 2 MB.',
            ]);

            // Ambil data mahasiswa
            $mahasiswaId = auth('mahasiswa')->id();
            if (! $mahasiswaId) {
                Log::error('Authentication failed - no mahasiswa ID found');

                return back()->with('error', 'Sesi login berakhir. Silakan login ulang.');
            }
            $mahasiswa = Mahasiswa::find($mahasiswaId);

            if (! $mahasiswa) {
                Log::error('Mahasiswa not found', ['mahasiswa_id' => $mahasiswaId]);

                return back()->with('error', 'Data mahasiswa tidak ditemukan. Silakan login ulang.');
            }

            // Log successful mahasiswa retrieval
            Log::info('Mahasiswa found for pengajuan', [
                'mahasiswa_id' => $mahasiswa->id,
                'nama' => $mahasiswa->nama,
                'nim' => $mahasiswa->nim,
            ]);

            // Cek dan hapus berkas lama jika ada
            $existing = BerkasPengajuanMagang::where('mahasiswa_id', $mahasiswa->id)->first();
            if ($existing) {
                // Hapus form pengajuan yang terkait
                FormPengajuanMagang::where('pengajuan_id', $existing->id)->delete();

                // Hapus file-file lama
                foreach (['cv', 'transkrip_nilai', 'portfolio'] as $file) {
                    if ($existing->$file && Storage::disk(self::DISK)->exists($existing->$file)) {
                        Storage::disk(self::DISK)->delete($existing->$file);
                    }
                }
                $existing->delete();
            }

            // Pastikan direktori penyimpanan ada
            $this->ensureDirectoryExists('pengajuan-magang/cv');
            $this->ensureDirectoryExists('pengajuan-magang/transkrip');
            $this->ensureDirectoryExists('pengajuan-magang/portfolio');

            // Simpan file dengan nama yang terstruktur
            $cvFileName = $this->generateFileName($mahasiswa, 'cv');
            $transkripFileName = $this->generateFileName($mahasiswa, 'transkrip');

            $cvPath = $request->file('cv')->storeAs('pengajuan-magang/cv', $cvFileName, self::DISK);
            $transkripPath = $request->file('transkrip_nilai')->storeAs('pengajuan-magang/transkrip', $transkripFileName, self::DISK);

            $portfolioPath = null;
            if ($request->hasFile('portfolio')) {
                $portfolioFileName = $this->generateFileName($mahasiswa, 'portfolio');
                $portfolioPath = $request->file('portfolio')->storeAs('pengajuan-magang/portfolio', $portfolioFileName, self::DISK);
            }

            // Simpan data ke database dalam transaksi
            DB::transaction(function () use ($mahasiswa, $cvPath, $transkripPath, $portfolioPath) {
                // Buat berkas pengajuan
                $berkas = BerkasPengajuanMagang::create([
                    'mahasiswa_id' => $mahasiswa->id,
                    'cv' => $cvPath,
                    'transkrip_nilai' => $transkripPath,
                    'portfolio' => $portfolioPath,
                ]);

                // Buat form pengajuan dengan status 'diproses'
                FormPengajuanMagang::forceCreate([
                    'pengajuan_id' => $berkas->id,
                    'status' => 'diproses',
                    'keterangan' => 'Dokumen telah dikirim, diproses review admin',
                ]);

                // Update status pengajuan ke diproses
                $this->updateStatusPengajuan($mahasiswa->id);
            });

            return redirect()->route('mahasiswa.pengajuan-magang')
                ->with('success', 'Pengajuan magang berhasil dikirim! Status pengajuan telah diubah menjadi diproses review.');

        } catch (ValidationException $e) {
            return back()
                ->withErrors($e->validator)
                ->withInput()
                ->with('error', 'Terdapat kesalahan dalam pengisian form. Silakan periksa kembali.');
        } catch (\Exception $e) {
            Log::error('Error pengajuan magang', [
                'mahasiswa_id' => auth('mahasiswa')->id(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['cv', 'transkrip_nilai', 'portfolio']),
            ]);

            return back()->with('error', 'Terjadi kesalahan sistem. Silakan coba lagi atau hubungi admin.');
        }
    }

    /**
     * Stream a PII document (cv|transkrip_nilai|portfolio) from the private disk.
     *
     * Authorized for: the owning mahasiswa, any admin, or a dosen who supervises
     * a contract for that mahasiswa.
     */
    public function downloadBerkas(BerkasPengajuanMagang $berkas, string $type)
    {
        abort_unless(in_array($type, ['cv', 'transkrip_nilai', 'portfolio'], true), 404);

        $path = $berkas->{$type};
        abort_if(empty($path), 404);
        abort_unless(Storage::disk(self::DISK)->exists($path), 404);

        $this->authorizeBerkasAccess($berkas);

        // PII access audit: record WHO read WHICH student's document, and when.
        $this->logBerkasAccess($berkas, $type);

        return Storage::disk(self::DISK)->download($path);
    }

    /**
     * Write an `accessed` audit row for a PII download. Uses the Auditable
     * helper so actor/ip/user_agent capture matches the change-history rows.
     */
    private function logBerkasAccess(BerkasPengajuanMagang $berkas, string $type): void
    {
        \App\Models\AuditLog::create([
            'auditable_type' => BerkasPengajuanMagang::class,
            'auditable_id' => $berkas->id,
            'event' => \App\Models\AuditLog::EVENT_ACCESSED,
            'old_values' => null,
            'new_values' => ['document' => $type],
            'actor_user_id' => $this->currentActorUserId(),
            'actor_role' => $this->currentActorRole(),
            'ip' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }

    private function currentActorUserId(): ?int
    {
        foreach (['mahasiswa', 'dosen', 'admin'] as $guard) {
            $user = auth($guard)->user();
            if ($user !== null) {
                return $user->user_id;
            }
        }

        return null;
    }

    private function currentActorRole(): ?string
    {
        foreach (['mahasiswa', 'dosen', 'admin'] as $guard) {
            if (auth($guard)->check()) {
                return $guard;
            }
        }

        return null;
    }

    /**
     * Ensure the current user may access the given berkas.
     */
    private function authorizeBerkasAccess(BerkasPengajuanMagang $berkas): void
    {
        // Owning mahasiswa.
        if (auth('mahasiswa')->check() && auth('mahasiswa')->id() === $berkas->mahasiswa_id) {
            return;
        }

        // Admin.
        if (auth('admin')->check()) {
            return;
        }

        // Supervising dosen.
        if (auth('dosen')->check()) {
            $dosenId = auth('dosen')->id();
            $supervises = KontrakMagang::where('mahasiswa_id', $berkas->mahasiswa_id)
                ->where('dosen_id', $dosenId)
                ->exists();

            abort_unless($supervises, 403);

            return;
        }

        abort(403);
    }
}
