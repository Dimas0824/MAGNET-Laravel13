<?php
use Illuminate\Support\Facades\Auth;
use App\Models\Mahasiswa;
use App\Models\KontrakMagang;
use App\Models\Perusahaan;
use function Livewire\Volt\{layout, state, mount};

layout('components.layouts.user.main');

state([
    'status' => '',
    'mahasiswa' => null,
    'perusahaan' => null,
    'magang' => null,
    'kontrak_magang' => null,
]);

mount(function () {
    $this->mahasiswa = Auth::guard('mahasiswa')->user();

    if ($this->mahasiswa) {
        // Get the latest kontrak magang for the mahasiswa
        $this->kontrak_magang = KontrakMagang::where('mahasiswa_id', $this->mahasiswa->id)
            ->with(['lowonganMagang.perusahaan', 'lowonganMagang.pekerjaan', 'lowonganMagang.lokasiMagang'])
            ->latest()
            ->first();

        // Set status based on current mahasiswa status
        $status = $this->mahasiswa->status_magang;

        switch ($status) {
            case 'sedang magang':
                $this->status = 'Sedang Magang';
                if ($this->kontrak_magang) {
                    $this->perusahaan = $this->kontrak_magang->lowonganMagang->perusahaan;
                    $this->magang = $this->kontrak_magang->lowonganMagang;
                }
                break;

            case 'selesai magang':
                $this->status = 'Selesai Magang';
                if ($this->kontrak_magang) {
                    $this->perusahaan = $this->kontrak_magang->lowonganMagang->perusahaan;
                    $this->magang = $this->kontrak_magang->lowonganMagang;
                }
                break;

            case 'belum magang':
            default:
                $this->status = 'Belum Magang';
                $this->perusahaan = null;
                $this->magang = null;
        }
    }
});
?>

<x-slot:user>mahasiswa</x-slot:user>
<!-- Header Section -->
<div>
    <h1 class="text-3xl font-bold text-gray-800 mb-2">Log Magang</h1>
    <p class="text-gray-600">Selamat datang kembali, {{ $this->mahasiswa->nama ?? 'Mahasiswa' }}!</p>
</div>
<div class="h-full bg-gradient-to-br from-blue-50 via-white to-indigo-50 p-4 rounded-2xl shadow-lg">
    <div class="max-w-4xl mx-auto">
        @if ($this->perusahaan && $this->magang)
            <!-- Active Internship Card -->
            <div class="group cursor-pointer transition-all duration-300 hover:scale-[1.02] mb-6"
                onclick="window.location='{{ route('mahasiswa.detail-log') }}'">
                <div
                    class="bg-white rounded-2xl shadow-lg hover:shadow-xl border-l-4
                           {{ $this->status === 'Sedang Magang' ? 'border-green-500' : 'border-blue-500' }}
                           p-6 relative overflow-hidden">

                    <!-- Background Pattern -->
                    <div
                        class="absolute top-0 right-0 w-32 h-32 bg-gradient-to-br from-blue-100 to-transparent rounded-full transform translate-x-16 -translate-y-16 opacity-50">
                    </div>

                    <!-- Status Badge -->
                    <div class="flex justify-between items-start mb-4">
                        <div class="flex items-center space-x-3">
                            <div
                                class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-md">
                                <img src="{{ asset('logo-perusahaan.png') }}" alt="Logo {{ $this->perusahaan->nama }}"
                                    class="w-8 h-8 object-contain filter brightness-0 invert">
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-800 text-lg">{{ $this->perusahaan->nama }}</h3>
                                <p class="text-sm text-gray-500">Perusahaan Mitra</p>
                            </div>
                        </div>
                        <span
                            class="px-3 py-1 rounded-full text-sm font-medium
                                   {{ $this->status === 'Sedang Magang' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">
                            {{ $this->status }}
                        </span>
                    </div>

                    <!-- Internship Details -->
                    <div class="space-y-3">
                        <div>
                            <h4 class="font-bold text-xl text-gray-800">{{ $this->magang->pekerjaan->nama ?? 'Magang' }}</h4>
                            <div class="flex items-center text-gray-600 mt-1">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <span>{{ $this->magang->lokasiMagang->lokasi ?? '-' }}</span>
                            </div>
                        </div>

                        @if ($this->kontrak_magang)
                            <!-- Timeline -->
                            <div class="bg-gray-50 rounded-xl p-4 mt-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500 uppercase tracking-wide">Mulai</p>
                                            <p class="font-semibold text-gray-800">
                                                {{ \Carbon\Carbon::parse($this->kontrak_magang->waktu_awal)->format('d M Y') }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500 uppercase tracking-wide">Selesai</p>
                                            <p class="font-semibold text-gray-800">
                                                {{ \Carbon\Carbon::parse($this->kontrak_magang->waktu_akhir)->format('d M Y') }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Click Indicator -->
                    <div class="flex items-center justify-between mt-6 pt-4 border-t border-gray-100">
                        <span class="text-sm text-gray-500">Klik untuk melihat detail log</span>
                        <svg class="w-5 h-5 text-gray-400 group-hover:text-blue-500 transition-colors" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </div>
            </div>
        @else
            <!-- Empty State -->
            <div class="text-center py-12">
                <div class="bg-white rounded-2xl shadow-lg p-8 max-w-md mx-auto">
                    <!-- Empty State Illustration -->
                    <div
                        class="w-24 h-24 mx-auto mb-6 bg-gradient-to-br from-blue-100 to-indigo-100 rounded-full flex items-center justify-center">
                        @if ($this->status === 'Belum Magang')
                            <svg class="w-12 h-12 text-blue-500" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2-2v2m8 0V6a2 2 0 012 2v6a2 2 0 01-2 2H8a2 2 0 01-2-2V8a2 2 0 012-2V6" />
                            </svg>
                        @else
                            <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        @endif
                    </div>

                    <!-- Status Message -->
                    <div class="mb-6">
                        <h3 class="text-xl font-bold text-gray-800 mb-2">{{ $this->status }}</h3>
                        @if ($this->status === 'Belum Magang')
                            <p class="text-gray-600 mb-6">
                                Mulai perjalanan magang Anda! Temukan perusahaan terbaik yang sesuai dengan minat dan
                                keahlian Anda.
                            </p>
                            <button
                                class="bg-gradient-to-r from-blue-500 to-indigo-600 hover:from-blue-600 hover:to-indigo-700 text-white font-semibold py-3 px-8 rounded-xl transition-all duration-300 hover:scale-105 shadow-lg hover:shadow-xl transform"
                                onclick="window.location='{{ route('mahasiswa.hasil-pencarian') }}'">
                                <div class="flex items-center space-x-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                    <span>Cari Magang</span>
                                </div>
                            </button>
                        @else
                            <p class="text-gray-600">Data magang tidak dapat ditemukan. Silakan hubungi admin jika ada
                                masalah.</p>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
