<?php

namespace App\Actions\Magang;

use App\Actions\Action;
use App\Models\BidangIndustri;
use App\Models\KontrakMagang;
use App\Models\LokasiMagang;
use App\Models\LowonganMagang;
use App\Models\Mahasiswa;
use App\Models\Pekerjaan;
use App\Models\Perusahaan;
use Illuminate\Http\UploadedFile;

/**
 * Register a student for an internship: resolve (or create) the target
 * lowongan magang, then create the pending KontrakMagang that waits for admin
 * approval.
 *
 * Extracted (behaviour-equivalent) from the Volt registration component:
 *
 *   resources/views/components/mahasiswa/pembaruan-status-magang/
 *       sedang-magang.blade.php   ($save, L120-236)
 *
 * The caller (the Volt closure) owns the guards ("no mahasiswa", "already has
 * an active contract"), validation, the flash messages and the form reset;
 * this action only performs the domain write and returns the new kontrak.
 *
 * ── Stale source writes, reconciled ─────────────────────────────────────────
 * The source `$save` closure wrote three columns. Two are resolved here:
 *
 *   1. `perusahaan.lokasi`   — the column was renamed/moved to the
 *                              `lokasi_magang_id` FK (migration
 *                              2026_09_27_001500_drop_lokasi_from_perusahaan),
 *                              so the address is carried on the linked
 *                              LokasiMagang row (and `deskripsi`) instead,
 *   2. `lowongan_magang.surat_izin_path` — never migrated; the upload is kept
 *                              on the `public` disk and the path is persisted
 *                              on `kontrak_magang.surat_izin_path` instead,
 *   3. `kontrak_magang.tanggal_daftar`   — now migrated (2026_09_28_000200)
 *                              and persisted unconditionally below.
 *
 * Because Eloquent strict mode rejects unknown columns, the stale writes in
 * the blade source raised a QueryException that the component's try/catch
 * swallowed into a "Terjadi kesalahan saat menyimpan data" flash — i.e.
 * registration never persisted. This action keeps the same intent while
 * guarding/remapping the stale columns (see the comments below) so the
 * documented behaviour — "kontrak/berkas created, files stored, status set" —
 * actually lands. The source logic was replaced wholesale by this action, so
 * nothing here "fixes" the blade file; the blade file is simply no longer
 * responsible for the write.
 */
class RegisterInternship implements Action
{
    /**
     * Path (on the `public` disk) the uploaded surat izin was stored at, or
     * null for the partner path (which does not store the uploaded file).
     * Exposed so callers/tests can observe the write.
     */
    public ?string $suratIzinPath = null;

    /**
     * @param  string  $companyType  'partner' | 'non_partner'
     * @param  int|string  $selectedCompanyId  partner: chosen mitra company id
     * @param  int|string  $selectedLowonganId  partner: chosen lowongan id
     * @param  string  $companyName  non-partner: new company name
     * @param  string  $companyAddress  non-partner: new company address
     * @param  string  $bidangIndustri  non-partner: bidang industri name
     * @param  string  $lokasiMagang  the internship location (both paths)
     * @param  UploadedFile|null  $suratIzinMagang  uploaded permit PDF
     */
    public function __construct(
        private Mahasiswa $mahasiswa,
        private string $companyType,
        private int|string $selectedCompanyId,
        private int|string $selectedLowonganId,
        private string $companyName,
        private string $companyAddress,
        private string $bidangIndustri,
        private string $lokasiMagang,
        private ?UploadedFile $suratIzinMagang = null,
    ) {}

    /**
     * Register the internship and return the pending kontrak.
     *
     * @throws \RuntimeException when a partner lowongan cannot be resolved or
     *                           the company type is unknown — the caller has
     *                           already validated, but the source closure also
     *                           guarded these explicitly.
     */
    public function handle(): KontrakMagang
    {
        $lowonganMagangId = $this->companyType === 'partner'
            ? $this->resolvePartnerLowonganId()
            : $this->createNonPartnerLowonganId();

        return KontrakMagang::forceCreate([
            'mahasiswa_id' => $this->mahasiswa->id,
            'dosen_id' => null, // assigned later by admin
            'lowongan_magang_id' => $lowonganMagangId,
            'waktu_awal' => now(),
            'waktu_akhir' => now()->addMonths(3),
            'status' => 'menunggu_persetujuan', // pending admin approval
            // Registration timestamp, persisted unconditionally — the column is
            // migrated (2026_09_28_000200) so the source's `tanggal_daftar`
            // write now lands as intended.
            'tanggal_daftar' => now(),
            // The stored permit path for the non-partner (upload) path; the
            // partner path never uploads, so this stays null (column nullable).
            // Set inside createNonPartnerLowonganId() before this create runs.
            'surat_izin_path' => $this->suratIzinPath,
        ]);
    }

    /**
     * Partner path: the student picked an existing mitra company + lowongan.
     * The source re-queried the lowongan scoped to the company and status,
     * failing the request when it did not match.
     */
    private function resolvePartnerLowonganId(): int
    {
        $selectedLowongan = LowonganMagang::where('id', $this->selectedLowonganId)
            ->where('perusahaan_id', $this->selectedCompanyId)
            ->where('status', 'buka')
            ->first();

        if (! $selectedLowongan) {
            throw new \RuntimeException('Lowongan magang tidak ditemukan atau tidak valid.');
        }

        return $selectedLowongan->id;
    }

    /**
     * Non-partner path: force-create the whole company chain (bidang industri,
     * perusahaan, pekerjaan, lokasi, lowongan) and store the uploaded permit.
     */
    private function createNonPartnerLowonganId(): int
    {
        // Store the uploaded permit (source only does this for non-partner).
        if ($this->suratIzinMagang) {
            $this->suratIzinPath = $this->suratIzinMagang->store('surat-izin-magang', 'public');
        }

        // Create or get bidang industri.
        $bidangIndustri = BidangIndustri::firstOrCreate(['nama' => $this->bidangIndustri]);

        // Create pekerjaan + lokasi magang (shared lookup rows).
        $pekerjaan = Pekerjaan::firstOrCreate(['nama' => 'Magang Umum']);
        $lokasiMagang = LokasiMagang::firstOrCreate([
            'kategori_lokasi' => 'Onsite',
            'lokasi' => $this->lokasiMagang,
        ]);

        // Create new company.
        //
        // NOTE: the source wrote `'lokasi' => $this->company_address`, but the
        // `perusahaan` table has no `lokasi` column anymore (latent bug #1
        // above) — the location is now the `lokasi_magang_id` FK. The address
        // is carried by the LokasiMagang row created above, so we link to it
        // (and keep the free-text address on `deskripsi`) instead of dropping
        // the information.
        //
        // NOTE: `perusahaan.deskripsi` and `perusahaan.website` are NOT NULL
        // with no default on the current schema, which the source omitted too.
        // We fill `deskripsi` with the typed address and `website` with an
        // empty string so the intended company row can actually be created.
        $newCompany = Perusahaan::forceCreate([
            'nama' => $this->companyName,
            'bidang_industri_id' => $bidangIndustri->id,
            'lokasi_magang_id' => $lokasiMagang->id,
            'kategori' => 'non_mitra',
            'rating' => 0,
            'deskripsi' => $this->companyAddress,
            'website' => '',
        ]);

        // Create lowongan magang.
        //
        // NOTE: the source also wrote `'surat_izin_path' => $suratPath`, but
        // `lowongan_magang` has no such column (latent bug #2 above); we keep
        // the upload on the disk (observable) and skip the stale column.
        $magang = LowonganMagang::forceCreate([
            'kuota' => 1,
            'pekerjaan_id' => $pekerjaan->id,
            'deskripsi' => "Program magang di {$this->companyName}",
            'persyaratan' => 'Sesuai dengan persyaratan perusahaan',
            'jenis_magang' => 'tidak berbayar',
            'open_remote' => 'tidak',
            'perusahaan_id' => $newCompany->id,
            'lokasi_magang_id' => $lokasiMagang->id,
            'status' => 'buka',
        ]);

        return $magang->id;
    }
}
