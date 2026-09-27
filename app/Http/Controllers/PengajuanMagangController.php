<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePengajuanMagangRequest;
use App\Models\BerkasPengajuanMagang;
use App\Models\KontrakMagang;
use App\Models\Mahasiswa;
use App\Services\BerkasPengajuanService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PengajuanMagangController extends Controller
{
    /**
     * Private disk used to store PII documents (CV, transcript, portfolio).
     */
    private const DISK = 'private';

    /**
     * Resolve the berkas service lazily so the controller stays `new`-able
     * (feature tests instantiate it directly with no constructor args).
     */
    private function berkasService(): BerkasPengajuanService
    {
        return App::make(BerkasPengajuanService::class);
    }

    /**
     * Public method untuk mengubah status ke diproses
     */
    public function setStatusdiproses($mahasiswaId)
    {
        return $this->berkasService()->updateStatusPengajuan($mahasiswaId);
    }

    public function storePengajuan(StorePengajuanMagangRequest $request)
    {
        try {
            // Validation runs automatically via the Form Request type-hint.

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

            $this->berkasService()->handle($mahasiswa, $request->allFiles());

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
