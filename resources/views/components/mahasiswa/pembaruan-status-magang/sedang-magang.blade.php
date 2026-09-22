<?php

use App\Models\BidangIndustri;
use App\Models\KontrakMagang;
use App\Models\LokasiMagang;
use App\Models\LowonganMagang;
use App\Models\Pekerjaan;
use App\Models\Perusahaan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;

use function Livewire\Volt\computed;
use function Livewire\Volt\mount;
use function Livewire\Volt\rules;
use function Livewire\Volt\state;
use function Livewire\Volt\updated;
use function Livewire\Volt\uses;

uses(WithFileUploads::class);

state([
    'company_type' => '',
    'selected_company_id' => '',
    'selected_lowongan_id' => '',
    'company_name' => '',
    'company_address' => '',
    'bidang_industri' => '',
    'lokasi_magang' => '',
    'surat_izin_magang' => null,
    'partner_companies' => [],
    'available_lowongan' => collect(),
    'mahasiswa' => null,
    'existing_contract' => null,
    'can_register' => false,
]);

rules([
    'company_name' => 'required_if:company_type,non_partner|string|max:255',
    'company_address' => 'required_if:company_type,non_partner|string',
    'bidang_industri' => 'required_if:company_type,non_partner|string',
    'selected_company_id' => 'required_if:company_type,partner|exists:perusahaan,id',
    'selected_lowongan_id' => 'required_if:company_type,partner|exists:lowongan_magang,id',
    'lokasi_magang' => 'required|string|max:255',
    'surat_izin_magang' => 'required|file|mimes:pdf|max:2048',
]);

mount(function () {
    try {
        $this->mahasiswa = Auth::guard('mahasiswa')->user();

        if (! $this->mahasiswa) {
            session()->flash('error', 'Anda harus login sebagai mahasiswa.');

            return;
        }

        // Check for existing contract
        $this->existing_contract = KontrakMagang::with(['lowonganMagang.perusahaan', 'dosenPembimbing'])
            ->where('mahasiswa_id', $this->mahasiswa->id)
            ->latest()
            ->first();

        // Determine if student can register for new internship
        // Allow registration if no contract exists, or previous contract was rejected/completed
        $this->can_register = ! $this->existing_contract || in_array($this->existing_contract->status, ['ditolak', 'selesai']) || $this->mahasiswa->status_magang === 'belum magang' || $this->mahasiswa->status_magang === 'selesai magang';

        if ($this->can_register) {
            $this->partner_companies = Perusahaan::where('kategori', 'mitra')
                ->whereHas('lowonganMagang', function ($query) {
                    $query->where('status', 'buka');
                })
                ->get();
        }
    } catch (\Exception $e) {
        Log::error('Error in mount function', [
            'mahasiswa_id' => $this->mahasiswa->id ?? null,
            'error' => $e->getMessage(),
        ]);
        session()->flash('error', 'Terjadi kesalahan saat memuat data.');
    }
});

updated([
    'selected_company_id' => function ($value) {
        $this->loadLowongan();
    },
]);

$loadLowongan = function () {
    try {
        if (! $this->selected_company_id) {
            $this->available_lowongan = collect();
            $this->selected_lowongan_id = '';

            return;
        }

        $this->available_lowongan = LowonganMagang::where('perusahaan_id', $this->selected_company_id)
            ->where('status', 'buka')
            ->with(['pekerjaan', 'lokasiMagang'])
            ->get();

        $this->selected_lowongan_id = '';
    } catch (\Exception $e) {
        Log::error('Error loading lowongan', [
            'company_id' => $this->selected_company_id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        $this->available_lowongan = collect();
        $this->selected_lowongan_id = '';
    }
};

$save = function () {
    try {
        if (! $this->mahasiswa) {
            session()->flash('error', 'Data mahasiswa tidak ditemukan.');

            return;
        }

        if (! $this->can_register) {
            session()->flash('error', 'Anda sudah memiliki pendaftaran magang yang sedang diproses atau aktif.');

            return;
        }

        $this->validate();

        // Double check for existing pending/active contract
        $existingContract = KontrakMagang::where('mahasiswa_id', $this->mahasiswa->id)
            ->whereIn('status', ['menunggu_persetujuan', 'disetujui'])
            ->exists();

        if ($existingContract) {
            session()->flash('error', 'Anda sudah memiliki pendaftaran magang yang sedang diproses atau aktif.');

            return;
        }

        $lowongan_magang_id = null;

        if ($this->company_type === 'partner') {
            $selectedLowongan = LowonganMagang::where('id', $this->selected_lowongan_id)->where('perusahaan_id', $this->selected_company_id)->where('status', 'buka')->first();

            if (! $selectedLowongan) {
                session()->flash('error', 'Lowongan magang tidak ditemukan atau tidak valid.');

                return;
            }

            $lowongan_magang_id = $selectedLowongan->id;
        } else {
            // Handle file upload for non-partner companies
            $suratPath = null;
            if ($this->surat_izin_magang) {
                $suratPath = $this->surat_izin_magang->store('surat-izin-magang', 'public');
            }

            // Create or get bidang industri
            $bidangIndustri = BidangIndustri::firstOrCreate(['nama' => $this->bidang_industri]);

            // Create new company
            $newCompany = Perusahaan::forceCreate([
                'nama' => $this->company_name,
                'bidang_industri_id' => $bidangIndustri->id,
                'lokasi' => $this->company_address,
                'kategori' => 'non_mitra',
                'rating' => 0,
            ]);

            // Create pekerjaan and lokasi_magang
            $pekerjaan = Pekerjaan::firstOrCreate(['nama' => 'Magang Umum']);
            $lokasi_magang = LokasiMagang::firstOrCreate([
                'kategori_lokasi' => 'Onsite',
                'lokasi' => $this->lokasi_magang,
            ]);

            // Create lowongan magang
            $magang = LowonganMagang::forceCreate([
                'kuota' => 1,
                'pekerjaan_id' => $pekerjaan->id,
                'deskripsi' => "Program magang di {$this->company_name}",
                'persyaratan' => 'Sesuai dengan persyaratan perusahaan',
                'jenis_magang' => 'tidak berbayar',
                'open_remote' => 'tidak',
                'perusahaan_id' => $newCompany->id,
                'lokasi_magang_id' => $lokasi_magang->id,
                'status' => 'buka',
                'surat_izin_path' => $suratPath, // Store file path if needed
            ]);

            $lowongan_magang_id = $magang->id;
        }

        // Create contract with pending status (without dosen assignment)
        $kontrak = KontrakMagang::forceCreate([
            'mahasiswa_id' => $this->mahasiswa->id,
            'dosen_id' => null, // Will be assigned by admin
            'lowongan_magang_id' => $lowongan_magang_id,
            'waktu_awal' => now(),
            'waktu_akhir' => now()->addMonths(3),
            'status' => 'menunggu_persetujuan', // Pending admin approval
            'tanggal_daftar' => now(),
        ]);

        // Do NOT change mahasiswa status automatically
        // Status will be changed by admin after approval

        $internshipInfo = $this->getInternshipInfo();

        session()->flash('success', "Pendaftaran magang berhasil dikirim! Menunggu persetujuan admin untuk: {$internshipInfo}");
        $this->resetForm();

        // Refresh the component state
        $this->can_register = false;
        $this->existing_contract = $kontrak->load(['lowonganMagang.perusahaan']);
    } catch (\Illuminate\Validation\ValidationException $e) {
        throw $e;
    } catch (\Exception $e) {
        Log::error('Error saving internship data', [
            'mahasiswa_id' => $this->mahasiswa->id ?? null,
            'company_type' => $this->company_type,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        session()->flash('error', 'Terjadi kesalahan saat menyimpan data: '.$e->getMessage());
    }
};

$getInternshipInfo = function () {
    try {
        if (! $this->mahasiswa) {
            return 'Tidak diketahui';
        }

        $kontrak = KontrakMagang::with(['lowonganMagang.perusahaan'])
            ->where('mahasiswa_id', $this->mahasiswa->id)
            ->latest()
            ->first();

        if ($kontrak && $kontrak->lowonganMagang && $kontrak->lowonganMagang->perusahaan) {
            $perusahaan = $kontrak->lowonganMagang->perusahaan;

            return "{$perusahaan->nama} - {$perusahaan->lokasi}";
        }

        return 'Lokasi magang belum ditentukan';
    } catch (\Exception $e) {
        Log::error('Error getting internship info', ['error' => $e->getMessage()]);

        return 'Error mengambil informasi magang';
    }
};

$resetForm = function () {
    $this->reset(['company_type', 'selected_company_id', 'selected_lowongan_id', 'company_name', 'company_address', 'bidang_industri', 'lokasi_magang', 'surat_izin_magang']);
    $this->available_lowongan = collect();
};

$getStatusBadgeClass = function ($status) {
    switch ($status) {
        case 'menunggu_persetujuan':
            return 'bg-yellow-100 text-yellow-800 border-yellow-200';
        case 'disetujui':
        case 'sedang magang':
            return 'bg-green-100 text-green-800 border-green-200';
        case 'belum magang':
            return 'bg-gray-100 text-gray-800 border-gray-200';
        case 'selesai magang':
        case 'selesai':
            return 'bg-blue-100 text-blue-800 border-blue-200';
        case 'ditolak':
            return 'bg-red-100 text-red-800 border-red-200';
        default:
            return 'bg-gray-100 text-gray-800 border-gray-200';
    }
};

?>
<div class="max-w-4xl mx-auto">
    <!-- Header Section -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
        <div class="p-6 border-b border-gray-100">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900">Sistem Informasi Magang</h2>
                    <p class="text-gray-600 mt-1">Kelola informasi magang Anda dengan mudah</p>
                </div>
                <div class="flex items-center space-x-2">
                    <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                        </path>
                    </svg>
                </div>
            </div>
        </div>

        @if (!$mahasiswa)
            <div class="p-6">
                <div class="flex items-center p-4 text-red-800 bg-red-50 border border-red-200 rounded-lg">
                    <svg class="flex-shrink-0 w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                            clip-rule="evenodd"></path>
                    </svg>
                    <div>
                        <h3 class="font-semibold">Akses Ditolak</h3>
                        <p class="mt-1">Anda harus login sebagai mahasiswa untuk mengakses fitur ini.</p>
                    </div>
                </div>
            </div>
        @else
            <!-- Flash Messages -->
            @if (session()->has('success'))
                <div class="mx-6 mt-6">
                    <div class="flex items-center p-4 text-green-800 bg-green-50 border border-green-200 rounded-lg">
                        <svg class="flex-shrink-0 w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd"></path>
                        </svg>
                        <div>
                            <h3 class="font-semibold">Berhasil!</h3>
                            <p class="mt-1">{{ session('success') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            @if (session()->has('error'))
                <div class="mx-6 mt-6">
                    <div class="flex items-center p-4 text-red-800 bg-red-50 border border-red-200 rounded-lg">
                        <svg class="flex-shrink-0 w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                clip-rule="evenodd"></path>
                        </svg>
                        <div>
                            <h3 class="font-semibold">Terjadi Kesalahan</h3>
                            <p class="mt-1">{{ session('error') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            @if (session()->has('info'))
                <div class="mx-6 mt-6">
                    <div class="flex items-center p-4 text-blue-800 bg-blue-50 border border-blue-200 rounded-lg">
                        <svg class="flex-shrink-0 w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                clip-rule="evenodd"></path>
                        </svg>
                        <div>
                            <h3 class="font-semibold">Informasi</h3>
                            <p class="mt-1">{{ session('info') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Student Information Card -->
            <div class="p-6">
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-6">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-900 mb-3">Informasi Mahasiswa</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <p class="text-sm text-gray-600">Nama Lengkap</p>
                                    <p class="font-semibold text-gray-900">{{ $mahasiswa->nama }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">NIM</p>
                                    <p class="font-semibold text-gray-900">{{ $mahasiswa->nim }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600">Status Magang</p>
                                    <span
                                        class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium border {{ $this->getStatusBadgeClass($mahasiswa->status_magang) }}">
                                        {{ ucwords(str_replace('_', ' ', $mahasiswa->status_magang)) }}
                                    </span>
                                </div>
                                @if ($mahasiswa->status_magang === 'sedang magang')
                                    <div>
                                        <p class="text-sm text-gray-600">Lokasi Magang</p>
                                        <p class="font-semibold text-gray-900">{{ $this->getInternshipInfo() }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="ml-4">
                            <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center">
                                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <!-- Current Internship Status (if student is currently interning) -->
    @if ($mahasiswa && $existing_contract && $mahasiswa->status_magang === 'sedang magang')
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <svg class="w-5 h-5 text-green-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                            clip-rule="evenodd"></path>
                    </svg>
                    Kontrak Magang Aktif
                </h3>

                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @if ($existing_contract->lowonganMagang && $existing_contract->lowonganMagang->perusahaan)
                            <div>
                                <p class="text-sm text-gray-600">Perusahaan</p>
                                <p class="font-semibold text-gray-900">
                                    {{ $existing_contract->lowonganMagang->perusahaan->nama }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Lokasi</p>
                                <p class="font-semibold text-gray-900">
                                    {{ $existing_contract->lowonganMagang->perusahaan->lokasi }}</p>
                            </div>
                        @endif
                        @if ($existing_contract->dosenPembimbing)
                            <div>
                                <p class="text-sm text-gray-600">Dosen Pembimbing</p>
                                <p class="font-semibold text-gray-900">{{ $existing_contract->dosenPembimbing->nama }}
                                </p>
                            </div>
                        @endif
                        <div>
                            <p class="text-sm text-gray-600">Periode Magang</p>
                            <p class="font-semibold text-gray-900">
                                {{ $existing_contract->waktu_awal->format('d M Y') }} -
                                {{ $existing_contract->waktu_akhir->format('d M Y') }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 p-3 bg-green-100 rounded-lg">
                        <p class="text-sm text-green-800">
                            <svg class="inline w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                    clip-rule="evenodd"></path>
                            </svg>
                            Anda sedang menjalani program magang. Untuk mendaftar magang baru, silakan selesaikan
                            program magang saat ini terlebih dahulu.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Registration Form (only show if student can register) -->
    @if ($mahasiswa && $can_register)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="p-6 border-b border-gray-100">
                <h3 class="text-lg font-semibold text-gray-900">Pendaftaran Magang Baru</h3>
                <p class="text-gray-600 mt-1">Lengkapi formulir berikut untuk mendaftarkan program magang</p>
            </div>

            <div class="p-6 space-y-6">
                <!-- Company Type Selection -->
                <div>
                    <x-flux::field>
                        <x-flux::label class="text-sm font-medium text-gray-700">Jenis Perusahaan</x-flux::label>
                        <x-flux::select wire:model.live="company_type" placeholder="Pilih jenis perusahaan"
                            class="mt-1">
                            <option value="">Pilih jenis perusahaan</option>
                            <option value="partner">Perusahaan Mitra</option>
                            <option value="non_partner">Perusahaan Non-Mitra</option>
                        </x-flux::select>
                        <x-flux::error for="company_type" />
                    </x-flux::field>
                </div>

                <!-- Partner Company Selection -->
                @if ($company_type == 'partner')
                    @if (count($partner_companies) > 0)
                        <div class="space-y-4">
                            <div>
                                <x-flux::field>
                                    <x-flux::label class="text-sm font-medium text-gray-700">Pilih Perusahaan
                                        Mitra</x-flux::label>
                                    <x-flux::select wire:model.live="selected_company_id"
                                        placeholder="Pilih perusahaan" class="mt-1">
                                        <option value="">Pilih perusahaan</option>
                                        @foreach ($partner_companies as $company)
                                            <option value="{{ $company->id }}">
                                                {{ $company->nama }}
                                                @if ($company->bidangIndustri)
                                                    ({{ $company->bidangIndustri->nama }})
                                                @endif
                                            </option>
                                        @endforeach
                                    </x-flux::select>
                                    <x-flux::error for="selected_company_id" />
                                </x-flux::field>
                            </div>

                            <!-- Job Selection for Partner Company -->
                            @if ($selected_company_id && $available_lowongan->count() > 0)
                                <div>
                                    <x-flux::field>
                                        <x-flux::label class="text-sm font-medium text-gray-700">Pilih Lowongan
                                            Magang</x-flux::label>
                                        <x-flux::select wire:model="selected_lowongan_id" placeholder="Pilih lowongan"
                                            class="mt-1">
                                            <option value="">Pilih lowongan</option>
                                            @foreach ($available_lowongan as $lowongan)
                                                <option value="{{ $lowongan->id }}">
                                                    @if ($lowongan->pekerjaan)
                                                        {{ $lowongan->pekerjaan->nama }}
                                                    @else
                                                        Lowongan Magang
                                                    @endif
                                                    @if ($lowongan->lokasiMagang)
                                                        - {{ $lowongan->lokasiMagang->lokasi }}
                                                    @endif
                                                </option>
                                            @endforeach
                                        </x-flux::select>
                                        <x-flux::error for="selected_lowongan_id" />
                                    </x-flux::field>
                                </div>

                                <!-- Show selected job details -->
                                @if ($selected_lowongan_id)
                                    @php
                                        $selectedJob = $available_lowongan->firstWhere('id', $selected_lowongan_id);
                                    @endphp
                                    @if ($selectedJob)
                                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                            <h4 class="font-semibold text-blue-900 mb-3">Detail Lowongan</h4>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                                <div>
                                                    <p class="text-blue-700 font-medium">Jenis Pekerjaan</p>
                                                    <p class="text-blue-900">
                                                        {{ $selectedJob->pekerjaan ? $selectedJob->pekerjaan->nama : 'Tidak tersedia' }}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p class="text-blue-700 font-medium">Kuota</p>
                                                    <p class="text-blue-900">{{ $selectedJob->kuota }} orang</p>
                                                </div>
                                                <div>
                                                    <p class="text-blue-700 font-medium">Jenis Magang</p>
                                                    <p class="text-blue-900">
                                                        {{ ucfirst(str_replace('_', ' ', $selectedJob->jenis_magang)) }}
                                                    </p>
                                                </div>
                                                <div>
                                                    <p class="text-blue-700 font-medium">Remote</p>
                                                    <p class="text-blue-900">
                                                        {{ $selectedJob->open_remote == 'ya' ? 'Ya' : 'Tidak' }}</p>
                                                </div>
                                                @if ($selectedJob->lokasiMagang)
                                                    <div class="md:col-span-2">
                                                        <p class="text-blue-700 font-medium">Lokasi</p>
                                                        <p class="text-blue-900">
                                                            {{ $selectedJob->lokasiMagang->kategori_lokasi }} -
                                                            {{ $selectedJob->lokasiMagang->lokasi }}</p>
                                                    </div>
                                                @endif
                                                @if ($selectedJob->deskripsi)
                                                    <div class="md:col-span-2">
                                                        <p class="text-blue-700 font-medium">Deskripsi</p>
                                                        <p class="text-blue-900">
                                                            {{ Str::limit($selectedJob->deskripsi, 200) }}</p>
                                                    </div>
                                                @endif
                                                @if ($selectedJob->persyaratan)
                                                    <div class="md:col-span-2">
                                                        <p class="text-blue-700 font-medium">Persyaratan</p>
                                                        <p class="text-blue-900">
                                                            {{ Str::limit($selectedJob->persyaratan, 200) }}</p>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @else
                                        <div
                                            class="flex items-center p-4 text-red-800 bg-red-50 border border-red-200 rounded-lg">
                                            <svg class="flex-shrink-0 w-5 h-5 mr-3" fill="currentColor"
                                                viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                                    clip-rule="evenodd"></path>
                                            </svg>
                                            <div>
                                                <h4 class="font-semibold">Lowongan Tidak Ditemukan</h4>
                                                <p class="mt-1">Selected job dengan ID {{ $selected_lowongan_id }}
                                                    tidak ditemukan dalam available_lowongan collection.</p>
                                            </div>
                                        </div>
                                    @endif
                                @endif
                            @elseif ($selected_company_id)
                                <div
                                    class="flex items-center p-4 text-yellow-800 bg-yellow-50 border border-yellow-200 rounded-lg">
                                    <svg class="flex-shrink-0 w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                            clip-rule="evenodd"></path>
                                    </svg>
                                    <div>
                                        <h4 class="font-semibold">Tidak Ada Lowongan Aktif</h4>
                                        <p class="mt-1">Tidak ada lowongan aktif (status buka) untuk perusahaan ini.
                                            Silakan pilih perusahaan lain atau hubungi administrator.</p>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @else
                        <div
                            class="flex items-center p-4 text-yellow-800 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <svg class="flex-shrink-0 w-5 h-5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd"
                                    d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                    clip-rule="evenodd"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold">Tidak Ada Perusahaan Mitra</h4>
                                <p class="mt-1">Tidak ada perusahaan mitra dengan lowongan aktif saat ini. Silakan
                                    pilih "Perusahaan Non-Mitra" untuk mendaftar ke perusahaan lain.</p>
                            </div>
                        </div>
                    @endif
                @endif

                <!-- Non-Partner Company Form -->
                @if ($company_type == 'non_partner')
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-flux::field>
                                    <x-flux::label class="text-sm font-medium text-gray-700">Nama
                                        Perusahaan</x-flux::label>
                                    <x-flux::input wire:model="company_name" placeholder="Masukkan nama perusahaan"
                                        class="mt-1" />
                                    <x-flux::error for="company_name" />
                                </x-flux::field>
                            </div>

                            <div>
                                <x-flux::field>
                                    <x-flux::label class="text-sm font-medium text-gray-700">Bidang
                                        Industri</x-flux::label>
                                    <x-flux::select wire:model="bidang_industri" placeholder="Pilih bidang industri"
                                        class="mt-1">
                                        <option value="">Pilih bidang industri</option>
                                        @foreach (BidangIndustri::orderBy('nama')->get() as $bidang)
                                            <option value="{{ $bidang->nama }}">{{ $bidang->nama }}</option>
                                        @endforeach
                                    </x-flux::select>
                                    <x-flux::error for="bidang_industri" />
                                </x-flux::field>
                            </div>
                        </div>

                        <div>
                            <x-flux::field>
                                <x-flux::label class="text-sm font-medium text-gray-700">Alamat
                                    Perusahaan</x-flux::label>
                                <x-flux::textarea wire:model="company_address"
                                    placeholder="Masukkan alamat lengkap perusahaan" rows="3" class="mt-1" />
                                <x-flux::error for="company_address" />
                            </x-flux::field>
                        </div>
                    </div>
                @endif

                <!-- Common fields for both company types -->
                @if ($company_type)
                    <div class="space-y-4 pt-4 border-t border-gray-200">
                        <div>
                            <x-flux::field>
                                <x-flux::label class="text-sm font-medium text-gray-700">Lokasi Magang</x-flux::label>
                                <x-flux::input wire:model="lokasi_magang"
                                    placeholder="Lokasi tempat magang (contoh: Jakarta Pusat, Malang)"
                                    class="mt-1" />
                                <x-flux::description>Masukkan lokasi spesifik tempat Anda akan menjalani
                                    magang</x-flux::description>
                                <x-flux::error for="lokasi_magang" />
                            </x-flux::field>
                        </div>

                        <div>
                            <x-flux::field>
                                <x-flux::label class="text-sm font-medium text-gray-700">Surat Izin
                                    Magang</x-flux::label>
                                <x-flux::input type="file" wire:model="surat_izin_magang" accept=".pdf"
                                    class="mt-1" />
                                <x-flux::description>Upload file PDF maksimal 2MB. Pastikan surat sudah ditandatangani
                                    oleh pihak yang berwenang.</x-flux::description>

                                @if ($surat_izin_magang && !$errors->has('surat_izin_magang'))
                                    <div class="mt-2 flex items-center text-sm text-green-600">
                                        <svg class="flex-shrink-0 w-4 h-4 mr-2" fill="currentColor"
                                            viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                clip-rule="evenodd"></path>
                                        </svg>
                                        File terpilih: {{ $surat_izin_magang->getClientOriginalName() }}
                                    </div>
                                @endif

                                <div wire:loading wire:target="surat_izin_magang"
                                    class="mt-2 flex items-center text-sm text-blue-600">
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-blue-600"
                                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10"
                                            stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                        </path>
                                    </svg>
                                    Mengunggah file...
                                </div>
                                <x-flux::error for="surat_izin_magang" />
                            </x-flux::field>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row justify-end gap-3 pt-6 border-t border-gray-200">
                        <x-flux::button variant="ghost" wire:click="resetForm" class="order-2 sm:order-1">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0V9a8 8 0 1115.356 2M15 15v-5h-.582m0 0a8.003 8.003 0 01-15.356-2m15.356 2V15a8 8 0 11-15.356-2">
                                </path>
                            </svg>
                            Reset Form
                        </x-flux::button>
                        <x-flux::button wire:click="save" wire:loading.attr="disabled" class="order-1 sm:order-2">
                            <span wire:loading.remove wire:target="save" class="flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Simpan Data Magang
                            </span>
                            <span wire:loading wire:target="save" class="flex items-center">
                                <svg class="animate-spin -ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg"
                                    fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10"
                                        stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                </svg>
                                Menyimpan...
                            </span>
                        </x-flux::button>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- Help Section -->
    @if ($mahasiswa && $can_register)
        <div class="mt-6 bg-gray-50 rounded-lg border border-gray-200">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                    <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                        </path>
                    </svg>
                    Bantuan Pengisian Form
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm text-gray-600">
                    <div>
                        <h4 class="font-semibold text-gray-800 mb-2">Perusahaan Mitra</h4>
                        <ul class="space-y-1 list-disc list-inside">
                            <li>Perusahaan yang sudah bermitra dengan kampus</li>
                            <li>Memiliki lowongan magang yang tersedia</li>
                            <li>Proses pendaftaran lebih mudah dan cepat</li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-800 mb-2">Perusahaan Non-Mitra</h4>
                        <ul class="space-y-1 list-disc list-inside">
                            <li>Perusahaan pilihan sendiri yang belum bermitra</li>
                            <li>Perlu mengisi data perusahaan secara lengkap</li>
                        </ul>
                    </div>
                </div>
                <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                    <p class="text-sm text-blue-800">
                        <svg class="inline w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                clip-rule="evenodd"></path>
                        </svg>
                        <strong>Tips:</strong> Pastikan semua data yang diisi sudah benar dan lengkap. Setelah data
                        disimpan, Anda akan secara otomatis ditugaskan ke dosen pembimbing.
                    </p>
                </div>
            </div>
        </div>
    @endif
</div>
