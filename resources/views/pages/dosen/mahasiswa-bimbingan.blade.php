<?php
use Illuminate\Support\Facades\Auth;
use App\Models\Mahasiswa;
use function Livewire\Volt\{layout, state, mount, computed};

state([
    'search' => '',
    'perPage' => 20,
]);

layout('components.layouts.user.main');

$mahasiswa = computed(function () {
    $dosenId = Auth::guard('dosen')->id();
    $query = Mahasiswa::query()
        ->select(['mahasiswa.*', 'perusahaan.nama as perusahaan_nama', 'pekerjaan.nama as posisi_nama', 'kontrak_magang.id as kontrak_id', 'kontrak_magang.waktu_awal', 'kontrak_magang.waktu_akhir'])
        ->join('kontrak_magang', 'mahasiswa.id', '=', 'kontrak_magang.mahasiswa_id')
        ->join('lowongan_magang', 'kontrak_magang.lowongan_magang_id', '=', 'lowongan_magang.id')
        ->join('perusahaan', 'lowongan_magang.perusahaan_id', '=', 'perusahaan.id')
        ->join('pekerjaan', 'lowongan_magang.pekerjaan_id', '=', 'pekerjaan.id')
        ->where('kontrak_magang.dosen_id', $dosenId)
        ->where('mahasiswa.status_magang', '!=', 'selesai magang')
        ->orderBy('mahasiswa.nama', 'asc'); // Default sorting by name

    // Apply search filter
    if ($this->search) {
        $searchTerm = "%{$this->search}%";
        $query->where(function ($q) use ($searchTerm) {
            $q->where('mahasiswa.nama', 'like', $searchTerm)->orWhere('mahasiswa.nim', 'like', $searchTerm)->orWhere('perusahaan.nama', 'like', $searchTerm)->orWhere('pekerjaan.nama', 'like', $searchTerm);
        });
    }

    return $query->paginate($this->perPage);
});

function clearSearch()
{
    state('search', '');
    state('page', 1);
}

function updateSearch()
{
    state('page', 1);
}

?>
<div>
    <x-slot:user>dosen</x-slot:user>

    <div class="bg-magnet-frost-mist min-h-screen flex flex-col gap-5 p-4">
        <!-- Breadcrumbs -->
        <flux:breadcrumbs class="mb-5">
            <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" icon:variant="outline" />
            <flux:breadcrumbs.item href="{{ route('dosen.mahasiswa-bimbingan') }}" class="text-black">
                Mahasiswa Bimbingan
            </flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <!-- Header Section -->
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Mahasiswa Bimbingan Aktif</h2>
            <p class="text-gray-600">Kelola dan pantau perkembangan mahasiswa yang sedang magang</p>
        </div>

        <div class="w-full bg-white rounded-xl shadow-sm border border-gray-200">
            <!-- Search Section -->
            <div class="p-6 border-b border-gray-100">
                <div class="flex flex-col sm:flex-row gap-4 items-center">
                    <!-- Search Input -->
                    <div class="flex-1 w-full">
                        <div class="relative">
                            <flux:input wire:model.live.debounce.300ms="search" wire:change="updateSearch"
                                placeholder="Cari berdasarkan nama, NIM, perusahaan, atau posisi..."
                                icon="magnifying-glass"
                                class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200" />
                        </div>
                    </div>

                    <!-- Clear Search Button -->
                    @if ($search)
                        <flux:button wire:click="clearSearch" variant="outline" icon="x-mark"
                            class="px-4 py-2 border-gray-300 text-gray-700 hover:bg-gray-50 rounded-lg transition-colors duration-200">
                            Reset
                        </flux:button>
                    @endif
                </div>

                <!-- Search Results Info -->
                @if ($search)
                    <div class="mt-4">
                        <div
                            class="flex items-center gap-2 p-3 text-sm bg-blue-50 text-blue-700 rounded-lg border border-blue-200">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>
                                Hasil pencarian untuk: <strong>"{{ $search }}"</strong>
                                • {{ $this->mahasiswa->total() }} data ditemukan
                            </span>
                        </div>
                    </div>
                @endif

                <!-- Summary Info -->
                <div class="flex items-center justify-between mt-4 pt-4 border-t border-gray-100">
                    <div class="flex items-center gap-2 text-sm text-gray-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                            </path>
                        </svg>
                        <span>Total {{ $this->mahasiswa->total() }} mahasiswa aktif</span>
                    </div>

                    @if (!$search && $this->mahasiswa->total() > 0)
                        <div class="text-xs text-gray-500">
                            Diurutkan berdasarkan nama (A-Z)
                        </div>
                    @endif
                </div>
            </div>

            <!-- Loading State -->
            <div wire:loading class="flex items-center justify-center py-12">
                <div class="flex items-center gap-3 text-blue-600">
                    <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                            stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                    <span class="text-sm font-medium">Memuat data...</span>
                </div>
            </div>

            <!-- Mahasiswa Cards -->
            <div wire:loading.remove class="p-6">
                @if ($this->mahasiswa->count() > 0)
                    <div
                        class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6 mb-8">
                        @foreach ($this->mahasiswa as $mhs)
                            <div class="group">
                                <a href="{{ route('dosen.detail-mahasiswa-bimbingan', $mhs->id) }}"
                                    class="block bg-white border border-gray-200 rounded-xl p-4 hover:shadow-lg hover:border-blue-300 transition-all duration-300 group-hover:scale-105">

                                    <!-- Profile Image -->
                                    <div class="flex justify-center mb-4">
                                        <div class="relative">
                                            <img src="{{ $mhs->foto_profil ? asset('storage/' . $mhs->foto_profil) : 'https://ui-avatars.com/api/?name=' . urlencode($mhs->nama) . '&background=3b82f6&color=ffffff&size=128' }}"
                                                class="w-20 h-20 rounded-full object-cover border-3 border-white shadow-md group-hover:shadow-lg transition-shadow duration-300"
                                                alt="Foto {{ $mhs->nama }}" />

                                            <!-- Status Indicator -->
                                            <div
                                                class="absolute -bottom-1 -right-1 w-6 h-6 bg-green-500 border-2 border-white rounded-full flex items-center justify-center">
                                                <div class="w-2 h-2 bg-white rounded-full animate-pulse"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Student Info -->
                                    <div class="text-center space-y-2">
                                        <h3
                                            class="font-semibold text-gray-900 text-sm leading-tight group-hover:text-blue-600 transition-colors duration-200">
                                            {{ $mhs->nama }}
                                        </h3>

                                        <p class="text-xs text-gray-500 font-mono">
                                            {{ $mhs->nim }}
                                        </p>

                                        <!-- Status Badge -->
                                        <div class="flex justify-center">
                                            <span
                                                class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700 border border-green-200">
                                                <span
                                                    class="w-1.5 h-1.5 bg-green-500 rounded-full mr-1.5 animate-pulse"></span>
                                                Sedang Magang
                                            </span>
                                        </div>

                                        <!-- Position and Company Info -->
                                        <div class="space-y-1 pt-2 border-t border-gray-100">
                                            <div class="flex items-center justify-center gap-1 text-xs text-gray-600">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z">
                                                    </path>
                                                </svg>
                                                <span
                                                    class="truncate">{{ $mhs->posisi_nama ?? 'Belum ada posisi' }}</span>
                                            </div>

                                            <div class="flex items-center justify-center gap-1 text-xs text-gray-600">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                                                    </path>
                                                </svg>
                                                <span
                                                    class="truncate">{{ $mhs->perusahaan_nama ?? 'Belum ada perusahaan' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        @endforeach
                    </div>

                    <!-- Pagination -->
                    <div class="flex justify-center border-t border-gray-100 pt-6">
                        {{ $this->mahasiswa->links() }}
                    </div>
                @else
                    <!-- Empty State -->
                    <div class="text-center py-16">
                        <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-6">
                            @if ($search)
                                <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            @else
                                <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z">
                                    </path>
                                </svg>
                            @endif
                        </div>

                        <h3 class="text-lg font-semibold text-gray-900 mb-2">
                            @if ($search)
                                Tidak Ada Hasil Ditemukan
                            @else
                                Belum Ada Mahasiswa Bimbingan
                            @endif
                        </h3>

                        <p class="text-gray-500 mb-6 max-w-md mx-auto">
                            @if ($search)
                                Tidak ada mahasiswa yang cocok dengan kata kunci "{{ $search }}". Coba gunakan
                                kata kunci yang berbeda.
                            @else
                                Belum ada mahasiswa yang sedang magang di bawah bimbingan Anda. Mahasiswa yang sedang
                                magang akan muncul di sini.
                            @endif
                        </p>

                        @if ($search)
                            <flux:button wire:click="clearSearch" variant="primary" icon="arrow-path"
                                class="px-6 py-2 bg-blue-600 text-white hover:bg-blue-700 rounded-lg transition-colors duration-200">
                                Tampilkan Semua Mahasiswa
                            </flux:button>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
