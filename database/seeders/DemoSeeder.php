<?php

namespace Database\Seeders;

use App\Helpers\DecisionMaking\ROC;
use App\Models\Admin;
use App\Models\BerkasPengajuanMagang;
use App\Models\BidangIndustri;
use App\Models\Chat;
use App\Models\DosenPembimbing;
use App\Models\FormPengajuanMagang;
use App\Models\KontrakMagang;
use App\Models\KriteriaBidangIndustri;
use App\Models\KriteriaJenisMagang;
use App\Models\KriteriaLokasiMagang;
use App\Models\KriteriaOpenRemote;
use App\Models\KriteriaPekerjaan;
use App\Models\LogMagang;
use App\Models\LokasiMagang;
use App\Models\LowonganMagang;
use App\Models\Mahasiswa;
use App\Models\Pekerjaan;
use App\Models\Perusahaan;
use App\Models\UlasanMagang;
use App\Models\UmpanBalikMagang;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    private string $password = 'password';

    /**
     * The default tenant id, stamped on every root row the demo creates.
     * forceCreate() bypasses the BelongsToTenant creating hook, so the seed
     * must set tenant_id explicitly now that the column is NOT NULL.
     */
    private function tenantId(): int
    {
        \App\Models\Concerns\BelongsToTenant::forgetResolvedTenant();

        return \App\Models\Concerns\BelongsToTenant::defaultTenantId()
            ?? \App\Models\Tenant::query()->where('slug', \Database\Seeders\TenantSeeder::DEFAULT_SLUG)->value('id');
    }

    public function run(): void
    {
        $this->resetDemoData();

        // The default tenant must exist before any root row is written: post
        // P1-T7 `tenant_id` is NOT NULL, and forceCreate() bypasses the trait's
        // creating hook, so every root insert below stamps it explicitly.
        $this->call(TenantSeeder::class);

        $this->seedMasterData();
        $this->seedAdmins();
        $dosen = $this->seedDosen();

        [$aktif, $selesai, $baru] = $this->seedMahasiswa();
        $this->seedPreferensi($aktif);
        $this->seedPreferensi($selesai);
        $this->seedPreferensi($baru);

        // Link every identity into the users registry (the backfill migration
        // ran against an empty DB). Chats + audit reference users.id, so this
        // must happen before any chat is created.
        $this->linkRegistry();

        // Refresh the in-memory identity models so their freshly-stamped
        // user_id is visible when chats are created below.
        $dosen->refresh();
        $aktif->refresh();
        $selesai->refresh();
        $baru->refresh();

        $perusahaan = $this->seedPerusahaan();
        $lowongan = $this->seedLowongan($perusahaan);

        $this->seedPengajuan($baru);
        $this->seedKontrakAktif($aktif, $dosen, $lowongan[0]);
        $this->seedKontrakSelesai($selesai, $dosen, $lowongan[1]);

        $this->command?->newLine();
        $this->command?->info('✅ Demo seed complete. Login credentials (password: "password"):');
        $this->command?->table(
            ['Role', 'Login ID (NIM/NIDN/NIP)', 'Name'],
            [
                ['Mahasiswa (aktif magang)', $aktif->nim, $aktif->nama],
                ['Mahasiswa (selesai magang)', $selesai->nim, $selesai->nama],
                ['Mahasiswa (belum magang)', $baru->nim, $baru->nama],
                ['Dosen', $dosen->nidn, $dosen->nama],
                ['Admin', Admin::first()->nip, 'Admin MAGNET'],
            ]
        );
    }

    /**
     * Link the demo identities into the users registry and stamp their user_id
     * (the backfill migrations ran against the empty DB before seeding).
     */
    private function linkRegistry(): void
    {
        (new \Database\Seeders\TenantBackfillSeeder)->run();
        (require database_path('migrations/2026_09_27_000600_backfill_users_registry.php'))->up();
        (require database_path('migrations/2026_09_27_000800_backfill_user_id_on_auth_tables.php'))->up();
    }

    private function resetDemoData(): void
    {
        // Idempotent re-run: remove the demo-owned rows (identified by their
        // known credentials/handles) so the seeder produces the same linked
        // dataset every time instead of tripping unique constraints.
        $mahasiswaIds = Mahasiswa::whereIn('nim', ['24410706001', '24410706002', '24410706003'])->pluck('id');
        $dosenIds = DosenPembimbing::where('nidn', '0012345678')->pluck('id');
        $kontrakIds = KontrakMagang::whereIn('mahasiswa_id', $mahasiswaIds)->pluck('id');

        Chat::whereIn('kontrak_magang_id', $kontrakIds)->delete();
        LogMagang::whereIn('kontrak_magang_id', $kontrakIds)->delete();
        UlasanMagang::whereIn('kontrak_magang_id', $kontrakIds)->delete();
        UmpanBalikMagang::whereIn('kontrak_magang_id', $kontrakIds)->delete();
        // forceDelete: kontrak rows are soft-deletable (P6-T4), but a demo
        // reset must remove them physically or the RESTRICT FK to
        // lowongan_magang would keep them alive.
        KontrakMagang::withTrashed()->whereIn('id', $kontrakIds)->forceDelete();

        $berkasIds = BerkasPengajuanMagang::whereIn('mahasiswa_id', $mahasiswaIds)->pluck('id');
        FormPengajuanMagang::whereIn('pengajuan_id', $berkasIds)->delete();
        BerkasPengajuanMagang::whereIn('id', $berkasIds)->delete();

        foreach ([
            KriteriaPekerjaan::class,
            KriteriaBidangIndustri::class,
            KriteriaLokasiMagang::class,
            KriteriaJenisMagang::class,
            KriteriaOpenRemote::class,
        ] as $kriteria) {
            $kriteria::whereIn('mahasiswa_id', $mahasiswaIds)->delete();
        }

        $perusahaanIds = Perusahaan::where('nama', 'PT Teknologi Nusantara')->pluck('id');
        LowonganMagang::withoutEvents(fn () => LowonganMagang::whereIn('perusahaan_id', $perusahaanIds)->delete());
        Perusahaan::whereIn('id', $perusahaanIds)->delete();

        Admin::where('nip', '198501012010011001')->delete();
        DosenPembimbing::whereIn('id', $dosenIds)->delete();
        // forceDelete: mahasiswa is soft-deletable (P6-T4); a reset removes it.
        Mahasiswa::withTrashed()->whereIn('id', $mahasiswaIds)->forceDelete();
    }

    private function seedMasterData(): void
    {
        if (BidangIndustri::query()->doesntExist()) {
            $this->call(BidangIndustriSeeder::class);
        }
        if (Pekerjaan::query()->doesntExist()) {
            $this->call(PekerjaanSeeder::class);
        }
        if (LokasiMagang::query()->doesntExist()) {
            $this->call(LokasiMagangSeeder::class);
        }
    }

    private function seedAdmins(): void
    {
        Admin::forceCreate([
            'tenant_id' => $this->tenantId(),
            'nama' => 'Admin MAGNET',
            'nip' => '198501012010011001',
            'password' => Hash::make($this->password),
        ]);
    }

    private function seedDosen(): DosenPembimbing
    {
        return DosenPembimbing::forceCreate([
            'tenant_id' => $this->tenantId(),
            'nama' => 'Dr. Sri Wahyuni, M.Kom.',
            'nidn' => '0012345678',
            'password' => Hash::make($this->password),
            'jenis_kelamin' => 'P',
        ]);
    }

    /** @return array{0: Mahasiswa, 1: Mahasiswa, 2: Mahasiswa} */
    private function seedMahasiswa(): array
    {
        $aktif = Mahasiswa::forceCreate([
            'tenant_id' => $this->tenantId(),
            'nama' => 'Budi Santoso',
            'nim' => '24410706001',
            'email' => 'budi@magnet.test',
            'password' => Hash::make($this->password),
            'angkatan' => 22,
            'jenis_kelamin' => 'L',
            'tanggal_lahir' => '2003-05-01',
            'jurusan' => 'Teknologi Informasi',
            'program_studi' => 'D4 Teknik Informatika',
            'alamat' => 'Jl. Soekarno Hatta No. 9, Malang',
            'status_magang' => 'sedang magang',
        ]);

        $selesai = Mahasiswa::forceCreate([
            'tenant_id' => $this->tenantId(),
            'nama' => 'Siti Aminah',
            'nim' => '24410706002',
            'email' => 'siti@magnet.test',
            'password' => Hash::make($this->password),
            'angkatan' => 21,
            'jenis_kelamin' => 'P',
            'tanggal_lahir' => '2002-08-17',
            'jurusan' => 'Teknologi Informasi',
            'program_studi' => 'D4 Sistem Informasi Bisnis',
            'alamat' => 'Jl. Ijen No. 12, Malang',
            'status_magang' => 'selesai magang',
        ]);

        $baru = Mahasiswa::forceCreate([
            'tenant_id' => $this->tenantId(),
            'nama' => 'Andi Pratama',
            'nim' => '24410706003',
            'email' => 'andi@magnet.test',
            'password' => Hash::make($this->password),
            'angkatan' => 23,
            'jenis_kelamin' => 'L',
            'tanggal_lahir' => '2004-01-20',
            'jurusan' => 'Teknologi Informasi',
            'program_studi' => 'D2 Pengembangan Piranti Lunak Situs',
            'alamat' => 'Jl. Veteran No. 3, Malang',
            'status_magang' => 'belum magang',
        ]);

        return [$aktif, $selesai, $baru];
    }

    private function seedPreferensi(Mahasiswa $mahasiswa): void
    {
        $total = config('recommendation-system.roc.total_criteria');

        $rows = [
            [KriteriaPekerjaan::class, 'pekerjaan_id', Pekerjaan::where('nama', 'Software Engineer')->value('id'), 1],
            [KriteriaBidangIndustri::class, 'bidang_industri_id', BidangIndustri::where('nama', 'Teknologi')->value('id'), 2],
            [KriteriaLokasiMagang::class, 'lokasi_magang_id', LokasiMagang::where('kategori_lokasi', 'Area Malang Raya')->value('id'), 3],
            [KriteriaJenisMagang::class, 'jenis_magang', 'berbayar', 4],
            [KriteriaOpenRemote::class, 'open_remote', 'ya', 5],
        ];

        foreach ($rows as [$model, $field, $value, $rank]) {
            $model::forceCreate([
                'mahasiswa_id' => $mahasiswa->id,
                $field => $value,
                'rank' => $rank,
                'bobot' => ROC::getWeight($rank, $total),
            ]);
        }
    }

    private function seedPerusahaan(): Perusahaan
    {
        return Perusahaan::forceCreate([
            'tenant_id' => $this->tenantId(),
            'nama' => 'PT Teknologi Nusantara',
            'bidang_industri_id' => BidangIndustri::where('nama', 'Teknologi')->value('id'),
            'lokasi_magang_id' => LokasiMagang::where('kategori_lokasi', 'Area Malang Raya')->value('id'),
            'kategori' => 'mitra',
            'rating' => 4.5,
            'logo' => null,
            'website' => 'https://teknologi-nusantara.test',
            'deskripsi' => 'Perusahaan teknologi yang bergerak di pengembangan perangkat lunak dan layanan digital.',
        ]);
    }

    /** @return array<int, LowonganMagang> */
    private function seedLowongan(Perusahaan $perusahaan): array
    {
        $lokasiId = LokasiMagang::where('kategori_lokasi', 'Area Malang Raya')->value('id');

        $defs = [
            ['Software Engineer', 'berbayar', 'ya'],
            ['Data Engineer', 'berbayar', 'tidak'],
            ['UI/UX Designer', 'tidak berbayar', 'ya'],
        ];

        $lowongan = [];
        foreach ($defs as [$pekerjaan, $jenis, $remote]) {
            $lowongan[] = LowonganMagang::withoutEvents(fn () => LowonganMagang::forceCreate([
                'tenant_id' => $this->tenantId(),
                'kuota' => 3,
                'pekerjaan_id' => Pekerjaan::where('nama', $pekerjaan)->value('id'),
                'deskripsi' => "Program magang posisi {$pekerjaan} di PT Teknologi Nusantara.",
                'persyaratan' => 'Mahasiswa aktif, memahami dasar-dasar bidang terkait, mampu bekerja dalam tim.',
                'jenis_magang' => $jenis,
                'open_remote' => $remote,
                'status' => 'buka',
                'lokasi_magang_id' => $lokasiId,
                'perusahaan_id' => $perusahaan->id,
            ]));
        }

        return $lowongan;
    }

    private function seedPengajuan(Mahasiswa $mahasiswa): void
    {
        $berkas = BerkasPengajuanMagang::forceCreate([
            'tenant_id' => $this->tenantId(),
            'mahasiswa_id' => $mahasiswa->id,
            'cv' => 'pengajuan-magang/cv/cv_demo_andi.pdf',
            'transkrip_nilai' => 'pengajuan-magang/transkrip/transkrip_demo_andi.pdf',
            'portfolio' => null,
        ]);

        FormPengajuanMagang::forceCreate([
            'pengajuan_id' => $berkas->id,
            'status' => 'diproses',
            'keterangan' => 'Dokumen telah dikirim, diproses review admin',
        ]);
    }

    private function seedKontrakAktif(Mahasiswa $mahasiswa, DosenPembimbing $dosen, LowonganMagang $lowongan): void
    {
        $awal = Carbon::now()->subWeeks(3);

        $kontrak = KontrakMagang::forceCreate([
            'tenant_id' => $this->tenantId(),
            'mahasiswa_id' => $mahasiswa->id,
            'dosen_id' => $dosen->id,
            'lowongan_magang_id' => $lowongan->id,
            'waktu_awal' => $awal,
            'waktu_akhir' => $awal->copy()->addMonths(3),
            'status' => 'disetujui',
            'keterangan' => 'Kontrak magang disetujui pembimbing.',
        ]);

        foreach ([1, 2, 3, 5] as $i => $dayOffset) {
            LogMagang::forceCreate([
                'kontrak_magang_id' => $kontrak->id,
                'kegiatan' => 'Mengerjakan modul '.($i + 1).' dan integrasi fitur backend.',
                'tanggal' => $awal->copy()->addDays($dayOffset)->toDateString(),
                'jam_masuk' => '08:00:00',
                'jam_keluar' => '17:00:00',
            ]);
        }

        Chat::forceCreate([
            'kontrak_magang_id' => $kontrak->id,
            'sender_user_id' => $mahasiswa->user_id,
            'receiver_user_id' => $dosen->user_id,
            'message' => 'Selamat pagi Bu, saya izin bertanya soal modul minggu ini.',
        ]);
        Chat::forceCreate([
            'kontrak_magang_id' => $kontrak->id,
            'sender_user_id' => $dosen->user_id,
            'receiver_user_id' => $mahasiswa->user_id,
            'message' => 'Pagi Budi, silakan kirim detail kendalanya lewat log ya.',
        ]);
        Chat::forceCreate([
            'kontrak_magang_id' => $kontrak->id,
            'sender_user_id' => $mahasiswa->user_id,
            'receiver_user_id' => $dosen->user_id,
            'message' => 'Baik Bu, sudah saya catat di log harian. Terima kasih.',
        ]);

        UmpanBalikMagang::forceCreate([
            'kontrak_magang_id' => $kontrak->id,
            'komentar' => 'Progres bagus, tingkatkan dokumentasi.',
            'tanggal' => Carbon::now()->subDays(2)->toDateString(),
        ]);
    }

    private function seedKontrakSelesai(Mahasiswa $mahasiswa, DosenPembimbing $dosen, LowonganMagang $lowongan): void
    {
        $awal = Carbon::now()->subMonths(5);

        $kontrak = KontrakMagang::forceCreate([
            'tenant_id' => $this->tenantId(),
            'mahasiswa_id' => $mahasiswa->id,
            'dosen_id' => $dosen->id,
            'lowongan_magang_id' => $lowongan->id,
            'waktu_awal' => $awal,
            'waktu_akhir' => $awal->copy()->addMonths(3),
            'status' => 'disetujui',
            'keterangan' => 'Magang telah selesai.',
        ]);

        LogMagang::forceCreate([
            'kontrak_magang_id' => $kontrak->id,
            'kegiatan' => 'Finalisasi laporan dan handover pekerjaan ke tim.',
            'tanggal' => $awal->copy()->addMonths(3)->subDays(1)->toDateString(),
            'jam_masuk' => '08:00:00',
            'jam_keluar' => '16:00:00',
        ]);

        UlasanMagang::forceCreate([
            'kontrak_magang_id' => $kontrak->id,
            'rating' => 5,
            'komentar' => 'Pengalaman magang yang sangat berharga dan mentor yang suportif.',
        ]);

        UmpanBalikMagang::forceCreate([
            'kontrak_magang_id' => $kontrak->id,
            'komentar' => 'Laporan akhir rapi dan tepat waktu.',
            'tanggal' => $awal->copy()->addMonths(3)->toDateString(),
        ]);
    }
}
