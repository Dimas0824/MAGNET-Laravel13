<?php

use App\Models\LokasiMagang;
use App\Models\LowonganMagang;
use App\Models\Perusahaan;
use Livewire\WithPagination;

use function Livewire\Volt\computed;
use function Livewire\Volt\layout;
use function Livewire\Volt\mount;
use function Livewire\Volt\state;
use function Livewire\Volt\uses;
use function Livewire\Volt\with;

layout('components.layouts.user.main');
uses([WithPagination::class]);

state([
    'selectedJob' => null,
    'searchQuery' => '',
    'sortBy' => 'created_at',
    'sortDirection' => 'desc',
    'filterByType' => 'all',
    'filterByLocation' => 'all',
    'filterByStatus' => 'buka',
    'isLoadingDetail' => false,
]);

mount(function () {
    // Get query parameter from URL
    $this->searchQuery = request()->get('query', '');
});

$jobs = computed(function () {
    $query = LowonganMagang::with(['perusahaan', 'pekerjaan', 'lokasiMagang'])->where('status', $this->filterByStatus);

    // Apply search filter (lowongan has no "nama" column; search by related
    // company name and location).
    if (! empty($this->searchQuery)) {
        $query->where(function ($q) {
            $q->orWhereHas('perusahaan', function ($company) {
                $company->where('nama', 'LIKE', "%{$this->searchQuery}%");
            })
                ->orWhereHas('lokasiMagang', function ($location) {
                    $location->where('lokasi', 'LIKE', "%{$this->searchQuery}%");
                });
        });
    }

    // Apply type filter
    if ($this->filterByType !== 'all') {
        $query->where('jenis_magang', $this->filterByType);
    }

    // Apply location filter
    if ($this->filterByLocation !== 'all') {
        $query->whereHas('lokasiMagang', function ($location) {
            $location->where('kategori_lokasi', $this->filterByLocation);
        });
    }

    // Apply sorting (whitelist columns/direction to prevent orderBy injection).
    $sortable = ['created_at', 'kuota', 'jenis_magang', 'open_remote', 'status'];
    $sortBy = in_array($this->sortBy, $sortable, true) ? $this->sortBy : 'created_at';
    $sortDirection = $this->sortDirection === 'asc' ? 'asc' : 'desc';
    $query->orderBy($sortBy, $sortDirection);

    return $query->paginate(10);
});

$selectJob = function ($jobId) {
    $this->isLoadingDetail = true;

    try {
        $this->selectedJob = LowonganMagang::with(['perusahaan.bidangIndustri', 'pekerjaan', 'lokasiMagang', 'kontrak_magang.ulasanMagang.mahasiswa'])->findOrFail($jobId);
    } catch (\Exception $e) {
        session()->flash('error', 'Lowongan tidak ditemukan.');
        $this->selectedJob = null;
    } finally {
        $this->isLoadingDetail = false;
    }
};

$clearSelection = function () {
    $this->selectedJob = null;
};

$updateSearch = function ($value) {
    $this->searchQuery = $value;
    $this->resetPage();
};

// Perbaikan untuk filter functions
$updateFilterType = function ($value) {
    $this->filterByType = $value;
    $this->resetPage();
    // Force refresh computed property
    $this->jobs;
};

$updateFilterLocation = function ($value) {
    $this->filterByLocation = $value;
    $this->resetPage();
    // Force refresh computed property
    $this->jobs;
};

$clearSearch = function () {
    $this->searchQuery = '';
    $this->resetPage();
};

// Add saveJob function that was missing
$saveJob = function () {
    // Implement save job functionality here
    session()->flash('success', 'Lowongan berhasil disimpan!');
};

// Add this method to handle pagination page name
$paginationView = function () {
    return 'pagination::bootstrap-4';
};

// Method helper untuk mendapatkan data tambahan jika diperlukan
$getJobStats = computed(function () {
    return [
        'total_jobs' => LowonganMagang::where('status', 'buka')->count(),
        'total_companies' => Perusahaan::whereHas('lowonganMagang', function ($query) {
            $query->where('status', 'buka');
        })->count(),
    ];
});

// Method untuk mendapatkan filter options
$getFilterOptions = computed(function () {
    return [
        'types' => LowonganMagang::select('jenis_magang')->distinct()->whereNotNull('jenis_magang')->pluck('jenis_magang')->toArray(),
        'locations' => LokasiMagang::select('kategori_lokasi')->distinct()->whereNotNull('kategori_lokasi')->pluck('kategori_lokasi')->toArray(),
    ];
});

?>

<div class="min-h-screen bg-gradient-to-br from-slate-50 to-blue-50/30">
    <x-slot:user>mahasiswa</x-slot:user>

    <!-- Header Section -->
    <div class="bg-white/80 backdrop-blur-sm border-b border-gray-200/50 sticky top-0 z-10">
        <div class="container mx-auto px-6 py-4">
            <div class="flex flex-col lg:flex-row gap-4 items-start lg:items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">
                        @if ($searchQuery)
                            Hasil Pencarian: "{{ $searchQuery }}"
                        @else
                            Lowongan Magang
                        @endif
                    </h1>
                    <p class="text-sm text-gray-600 mt-1">Temukan peluang magang terbaik untuk pengembangan kariermu</p>
                </div>

                <!-- Search and Filters -->
                <div class="flex flex-wrap gap-3 w-full lg:w-auto">
                    <div class="flex-1 lg:flex-none lg:w-80">
                        <flux:input wire:model.live.debounce.300ms="searchQuery"
                            class="rounded-xl border-gray-200 shadow-sm"
                            placeholder="Cari perusahaan, posisi, atau lokasi..." icon="magnifying-glass" />
                    </div>

                    <flux:dropdown class="w-auto">
                        <flux:button variant="outline" class="rounded-xl border-gray-200 shadow-sm px-4" icon="funnel">
                            Filter
                            @if ($filterByType !== 'all' || $filterByLocation !== 'all')
                                <flux:badge variant="primary" size="sm" class="ml-1">
                                    {{ ($filterByType !== 'all' ? 1 : 0) + ($filterByLocation !== 'all' ? 1 : 0) }}
                                </flux:badge>
                            @endif
                        </flux:button>

                        <flux:menu class="w-56">
                            <flux:menu.group>
                                <flux:menu.heading>Jenis Magang</flux:menu.heading>
                                <flux:menu.item wire:click="updateFilterType('all')"
                                    class="{{ $filterByType === 'all' ? 'bg-blue-50' : '' }}">
                                    <div class="flex items-center gap-2">
                                        <input type="radio" name="filter_type" value="all"
                                            {{ $filterByType === 'all' ? 'checked' : '' }} class="text-blue-600">
                                        <span>Semua</span>
                                    </div>
                                </flux:menu.item>
                                <flux:menu.item wire:click="updateFilterType('berbayar')"
                                    class="{{ $filterByType === 'berbayar' ? 'bg-blue-50' : '' }}">
                                    <div class="flex items-center gap-2">
                                        <input type="radio" name="filter_type" value="berbayar"
                                            {{ $filterByType === 'berbayar' ? 'checked' : '' }} class="text-blue-600">
                                        <span>Berbayar</span>
                                    </div>
                                </flux:menu.item>
                                <flux:menu.item wire:click="updateFilterType('tidak berbayar')"
                                    class="{{ $filterByType === 'tidak berbayar' ? 'bg-blue-50' : '' }}">
                                    <div class="flex items-center gap-2">
                                        <input type="radio" name="filter_type" value="tidak berbayar"
                                            {{ $filterByType === 'tidak berbayar' ? 'checked' : '' }}
                                            class="text-blue-600">
                                        <span>Tidak Berbayar</span>
                                    </div>
                                </flux:menu.item>
                            </flux:menu.group>

                            <flux:menu.separator />

                            <flux:menu.group>
                                <flux:menu.heading>Lokasi</flux:menu.heading>
                                <flux:menu.item wire:click="updateFilterLocation('all')"
                                    class="{{ $filterByLocation === 'all' ? 'bg-blue-50' : '' }}">
                                    <div class="flex items-center gap-2">
                                        <input type="radio" name="filter_location" value="all"
                                            {{ $filterByLocation === 'all' ? 'checked' : '' }} class="text-blue-600">
                                        <span>Semua Lokasi</span>
                                    </div>
                                </flux:menu.item>
                                @foreach ($this->getFilterOptions['locations'] as $location)
                                    <flux:menu.item wire:click="updateFilterLocation('{{ $location }}')"
                                        class="{{ $filterByLocation === $location ? 'bg-blue-50' : '' }}">
                                        <div class="flex items-center gap-2">
                                            <input type="radio" name="filter_location" value="{{ $location }}"
                                                {{ $filterByLocation === $location ? 'checked' : '' }}
                                                class="text-blue-600">
                                            <span>{{ ucfirst($location) }}</span>
                                        </div>
                                    </flux:menu.item>
                                @endforeach
                            </flux:menu.group>

                            @if ($filterByType !== 'all' || $filterByLocation !== 'all')
                                <flux:menu.separator />
                                <flux:menu.item
                                    wire:click="$set('filterByType', 'all'); $set('filterByLocation', 'all'); resetPage();"
                                    class="text-red-600">
                                    <div class="flex items-center gap-2">
                                        <flux:icon.x-circle class="size-4" />
                                        <span>Reset Filter</span>
                                    </div>
                                </flux:menu.item>
                            @endif
                        </flux:menu>
                    </flux:dropdown>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-6 py-6">
        <div class="flex flex-col lg:flex-row gap-6 min-h-[calc(100vh-200px)]">

            <!-- Job Listings Panel -->
            <div class="w-full lg:w-1/2">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200/50 overflow-hidden">
                    <div class="p-4 border-b border-gray-100">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium text-gray-700">
                                {{ $this->jobs->total() }} lowongan ditemukan
                            </p>
                            @if ($searchQuery)
                                <flux:button variant="ghost" size="sm" wire:click="clearSearch" icon="x-circle"
                                    class="text-gray-500">
                                    Hapus pencarian
                                </flux:button>
                            @endif
                        </div>
                    </div>

                    <div class="divide-y divide-gray-100 max-h-[600px] overflow-y-auto">
                        @forelse($this->jobs as $job)
                            <div onclick="window.location='{{ route('mahasiswa.detail-lowongan-magang', $job->id) }}'"
                                role="button"
                                class="p-4 hover:bg-gradient-to-r hover:from-blue-50 hover:to-indigo-50 hover:border-blue-300 cursor-pointer transition-all duration-200 group border-r-2 border-r-transparent {{ $selectedJob?->id === $job->id ? 'bg-blue-50/50 border-r-blue-500' : '' }}">
                                <div class="flex items-start gap-4">
                                    <div class="flex-shrink-0">
                                        @if ($job->perusahaan->logo)
                                            <img src="{{ asset('storage/' . $job->perusahaan->logo) }}"
                                                alt="{{ $job->perusahaan->nama }}"
                                                class="w-12 h-12 rounded-xl object-cover border border-gray-200">
                                        @else
                                            <div
                                                class="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center">
                                                <span class="text-white font-bold text-lg">
                                                    {{ substr($job->perusahaan->nama, 0, 1) }}
                                                </span>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="flex-1">
                                                <h3
                                                    class="font-semibold text-gray-900 group-hover:text-blue-600 transition-colors line-clamp-1">
                                                    {{ $job->pekerjaan->nama ?? $job->nama }}
                                                </h3>
                                                <p class="text-sm text-gray-600 mt-1">{{ $job->perusahaan->nama }}</p>

                                                <div class="flex items-center gap-4 mt-2 text-xs text-gray-500">
                                                    <div class="flex items-center gap-1">
                                                        <flux:icon.map-pin class="size-3" />
                                                        {{ $job->lokasiMagang->lokasi ?? 'Remote' }}
                                                    </div>

                                                    <div class="flex items-center gap-1">
                                                        <flux:icon.users class="size-3" />
                                                        {{ $job->kuota }} posisi
                                                    </div>

                                                    @if ($job->jenis_magang === 'berbayar')
                                                        <flux:badge variant="success" size="sm">Berbayar
                                                        </flux:badge>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="text-xs text-gray-400">
                                                {{ $job->created_at->diffForHumans() }}
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Detail Button -->
                                    <div class="flex items-center gap-2 ml-2" onclick="event.stopPropagation();">
                                        <flux:button wire:click="selectJob({{ $job->id }})" variant="ghost"
                                            size="sm" class="text-blue-600 hover:bg-blue-100">
                                            <flux:icon.eye class="size-4" />
                                        </flux:button>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="p-8 text-center">
                                <flux:icon.search class="size-12 text-gray-300 mx-auto mb-4" />
                                <p class="text-gray-500 mb-2">
                                    @if ($searchQuery)
                                        Tidak ada lowongan ditemukan untuk "{{ $searchQuery }}"
                                    @else
                                        Tidak ada lowongan ditemukan
                                    @endif
                                </p>
                                <p class="text-sm text-gray-400">Coba ubah kriteria pencarian Anda</p>

                                @if ($searchQuery)
                                    <flux:button wire:click="clearSearch" variant="outline" size="sm"
                                        class="mt-3">
                                        Hapus Pencarian
                                    </flux:button>
                                @endif
                            </div>
                        @endforelse
                    </div>

                    @if ($this->jobs->hasPages())
                        <div class="p-4 border-t border-gray-100">
                            {{ $this->jobs->links() }}
                        </div>
                    @endif
                </div>
            </div>

            <!-- Job Detail Panel -->
            <div class="w-full lg:w-1/2">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200/50 min-h-full">
                    @if (!$selectedJob)
                        <div class="flex items-center justify-center h-96 text-center p-8">
                            <div>
                                <flux:icon.cursor-arrow-rays class="size-16 text-gray-300 mx-auto mb-4" />
                                <h3 class="text-lg font-medium text-gray-900 mb-2">Pilih Lowongan Magang</h3>
                                <p class="text-gray-500">Klik pada salah satu lowongan di sebelah kiri untuk melihat
                                    detail lengkapnya</p>
                            </div>
                        </div>
                    @else
                        <div class="h-full overflow-y-auto">
                            @if ($isLoadingDetail)
                                <div class="flex items-center justify-center h-96">
                                    <svg class="animate-spin h-8 w-8 text-gray-500" xmlns="http://www.w3.org/2000/svg"
                                        fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10"
                                            stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                        </path>
                                    </svg>
                                </div>
                            @else
                                <!-- Job Header -->
                                <div class="p-6 border-b border-gray-100">
                                    <div class="flex items-start justify-between mb-6">
                                        <div class="flex items-start gap-4 flex-1">
                                            @if ($selectedJob->perusahaan->logo)
                                                <img src="{{ asset('storage/' . $selectedJob->perusahaan->logo) }}"
                                                    alt="{{ $selectedJob->perusahaan->nama }}"
                                                    class="w-16 h-16 rounded-xl object-cover border border-gray-200">
                                            @else
                                                <div
                                                    class="w-16 h-16 rounded-xl bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center">
                                                    <span class="text-white font-bold text-xl">
                                                        {{ substr($selectedJob->perusahaan->nama, 0, 1) }}
                                                    </span>
                                                </div>
                                            @endif

                                            <div class="flex-1">
                                                <h1 class="text-xl font-bold text-gray-900 mb-1">
                                                    {{ $selectedJob->nama }}</h1>
                                                <p class="text-gray-600 mb-3">{{ $selectedJob->perusahaan->nama }}</p>

                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                                                    <div class="flex items-center gap-2 text-gray-600">
                                                        <flux:icon.map-pin class="size-4 text-gray-400" />
                                                        {{ $selectedJob->lokasiMagang->lokasi ?? 'Remote' }}
                                                    </div>

                                                    <div class="flex items-center gap-2 text-gray-600">
                                                        <flux:icon.banknote class="size-4 text-gray-400" />
                                                        {{ $selectedJob->jenis_magang === 'berbayar' ? 'Berbayar' : 'Tidak Berbayar' }}
                                                    </div>

                                                    <div class="flex items-center gap-2 text-gray-600">
                                                        <flux:icon.users class="size-4 text-gray-400" />
                                                        {{ $selectedJob->kuota }} posisi tersedia
                                                    </div>

                                                    <div class="flex items-center gap-2 text-gray-600">
                                                        <flux:icon.wifi class="size-4 text-gray-400" />
                                                        {{ $selectedJob->open_remote === 'ya' ? 'Remote Available' : 'On-site Only' }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex flex-col gap-2">
                                            <flux:button wire:click="saveJob" variant="primary"
                                                class="bg-blue-600 hover:bg-blue-700" icon="bookmark">
                                                Simpan
                                            </flux:button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Job Description -->
                                <div class="p-6 border-b border-gray-100">
                                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Deskripsi Magang</h2>
                                    <div class="prose prose-sm text-gray-700 max-w-none">
                                        {!! nl2br(e($selectedJob->deskripsi)) !!}
                                    </div>
                                </div>

                                <!-- Requirements -->
                                <div class="p-6 border-b border-gray-100">
                                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Persyaratan</h2>
                                    <div class="prose prose-sm text-gray-700 max-w-none">
                                        {!! nl2br(e($selectedJob->persyaratan)) !!}
                                    </div>
                                </div>

                                <!-- Reviews Section -->
                                @if ($selectedJob->kontrakMagang->isNotEmpty())
                                    <div class="p-6">
                                        <h2 class="text-lg font-semibold text-gray-900 mb-4">Ulasan Magang</h2>
                                        <div class="space-y-4">
                                            @foreach ($selectedJob->kontrakMagang->take(3) as $kontrak)
                                                @if ($kontrak->ulasanMagang->isNotEmpty())
                                                    @foreach ($kontrak->ulasanMagang->take(1) as $ulasan)
                                                        <div class="bg-gray-50 rounded-xl p-4">
                                                            <div class="flex items-start gap-3 mb-3">
                                                                @if ($ulasan->kontrak->mahasiswa->foto)
                                                                    <img src="{{ asset('storage/' . $ulasan->kontrak->mahasiswa->foto) }}"
                                                                        alt="{{ $ulasan->kontrak->mahasiswa->nama }}"
                                                                        class="w-10 h-10 rounded-full object-cover">
                                                                @else
                                                                    <div
                                                                        class="w-10 h-10 rounded-full bg-gray-300 flex items-center justify-center">
                                                                        <span
                                                                            class="text-gray-600 font-medium text-sm">
                                                                            {{ substr($ulasan->kontrak->mahasiswa->nama, 0, 1) }}
                                                                        </span>
                                                                    </div>
                                                                @endif

                                                                <div class="flex-1">
                                                                    <div class="flex items-center gap-2 mb-1">
                                                                        <p class="font-medium text-gray-900">
                                                                            {{ $ulasan->kontrak->mahasiswa->nama }}</p>
                                                                        <div class="flex items-center">
                                                                            @for ($i = 1; $i <= 5; $i++)
                                                                                <flux:icon.star
                                                                                    class="size-4 {{ $i <= $ulasan->rating ? 'text-yellow-400' : 'text-gray-300' }}" />
                                                                            @endfor
                                                                        </div>
                                                                    </div>
                                                                    <p class="text-xs text-gray-500 mb-2">
                                                                        {{ $ulasan->created_at->format('d M Y') }}</p>
                                                                    <p class="text-sm text-gray-700 leading-relaxed">
                                                                        {{ $ulasan->komentar }}</p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
