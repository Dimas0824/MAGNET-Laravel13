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

    public function run(): void
    {
        $this->resetDemoData();

        $this->seedMasterData();
        $this->seedAdmins();
        $dosen = $this->seedDosen();

        [$aktif, $selesai, $baru] = $this->seedMahasiswa();
        $this->seedPreferensi($aktif);
        $this->seedPreferensi($selesai);
        $this->seedPreferensi($baru);

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
        KontrakMagang::whereIn('id', $kontrakIds)->delete();

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
        Mahasiswa::whereIn('id', $mahasiswaIds)->delete();
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
            'nama' => 'Admin MAGNET',
            'nip' => '198501012010011001',
            'password' => Hash::make($this->password),
        ]);
    }

    private function seedDosen(): DosenPembimbing
    {
        return DosenPembimbing::forceCreate([
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
            'nama' => 'PT Teknologi Nusantara',
            'bidang_industri_id' => BidangIndustri::where('nama', 'Teknologi')->value('id'),
            'lokasi' => 'Lowokwaru, Kota Malang, Jawa Timur',
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
            'sender_id' => $mahasiswa->id,
            'sender_type' => Chat::SENDER_MAHASISWA,
            'receiver_id' => $dosen->id,
            'receiver_type' => Chat::SENDER_DOSEN,
            'message' => 'Selamat pagi Bu, saya izin bertanya soal modul minggu ini.',
        ]);
        Chat::forceCreate([
            'kontrak_magang_id' => $kontrak->id,
            'sender_id' => $dosen->id,
            'sender_type' => Chat::SENDER_DOSEN,
            'receiver_id' => $mahasiswa->id,
            'receiver_type' => Chat::SENDER_MAHASISWA,
            'message' => 'Pagi Budi, silakan kirim detail kendalanya lewat log ya.',
        ]);
        Chat::forceCreate([
            'kontrak_magang_id' => $kontrak->id,
            'sender_id' => $mahasiswa->id,
            'sender_type' => Chat::SENDER_MAHASISWA,
            'receiver_id' => $dosen->id,
            'receiver_type' => Chat::SENDER_DOSEN,
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
