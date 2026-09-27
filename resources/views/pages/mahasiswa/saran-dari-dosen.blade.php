<?php
use App\Models\KontrakMagang;
use App\Models\Mahasiswa;
use App\Models\UmpanBalikMagang;

use function Livewire\Volt\computed;
use function Livewire\Volt\layout;
use function Livewire\Volt\mount;
use function Livewire\Volt\state;

layout('components.layouts.user.main');

state([
    'mahasiswa_id' => null,
    'filter' => 'all', // all, this_week, this_month, older
    'sort' => 'newest', // newest, oldest
    'page' => 1,
    'perPage' => 6,
    'search' => '',
]);

mount(function () {
    $this->mahasiswa_id = auth('mahasiswa')->user()->id;
    $this->page = request('page', 1);
});

$komentarDosenQuery = function () {
    return UmpanBalikMagang::with(['kontrakMagang.mahasiswa', 'kontrakMagang.dosenPembimbing', 'kontrakMagang.lowonganMagang.perusahaan'])->whereHas('kontrakMagang', function ($query) {
        $query->where('mahasiswa_id', $this->mahasiswa_id);
    });
};

// Query-level pagination: only the current page's rows are fetched from the
// database instead of loading every row into PHP and slicing the collection.
$paginatedKomentar = computed(function () {
    $query = $this->komentarDosenQuery();

    // Filter by search
    if ($this->search) {
        $query->where(function ($q) {
            $q->where('komentar', 'like', '%'.$this->search.'%')->orWhereHas('kontrakMagang.dosenPembimbing', function ($subQ) {
                $subQ->where('nama', 'like', '%'.$this->search.'%');
            });
        });
    }

    // Filter by date - PERBAIKAN DISINI
    switch ($this->filter) {
        case 'this_week':
            $query->whereBetween('tanggal', [now()->startOfWeek(), now()->endOfWeek()]);
            break;
        case 'this_month':
            $query->whereMonth('tanggal', now()->month)->whereYear('tanggal', now()->year);
            break;
        case 'older':
            $query->where('tanggal', '<', now()->subMonth());
            break;
        case 'all':
        default:
            // Tidak ada filter tambahan untuk 'all' - tampilkan semua data
            break;
    }

    // Sort
    $query->orderBy('tanggal', $this->sort === 'newest' ? 'desc' : 'asc');

    // Paginate at the database level. The page is driven by the component
    // state (goToPage / updateFilter / ...) rather than the request query.
    return $query->paginate($this->perPage, ['*'], 'page', $this->page);
});

// Counts are computed once from the database rather than re-scanning the full
// in-memory collection for every stat in the header.
$totalKomentar = computed(function () {
    return $this->komentarDosenQuery()->count();
});

$filteredKomentarCount = computed(function () {
    $query = $this->komentarDosenQuery();

    [$count, $label] = match ($this->filter) {
        'this_week' => [
            (clone $query)->whereBetween('tanggal', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'Minggu Ini',
        ],
        'this_month' => [
            (clone $query)->whereMonth('tanggal', now()->month)->whereYear('tanggal', now()->year)->count(),
            'Bulan Ini',
        ],
        'older' => [
            (clone $query)->where('tanggal', '<', now()->subMonth())->count(),
            'Lama',
        ],
        default => [
            (clone $query)->where('tanggal', '>=', now()->startOfWeek())->count(),
            'Total',
        ],
    };

    return ['count' => $count, 'label' => $label];
});

$dosenPembimbingCount = computed(function () {
    return UmpanBalikMagang::query()
        ->join('kontrak_magang', 'umpan_balik_magang.kontrak_magang_id', '=', 'kontrak_magang.id')
        ->join('dosen_pembimbing', 'kontrak_magang.dosen_id', '=', 'dosen_pembimbing.id')
        ->where('kontrak_magang.mahasiswa_id', $this->mahasiswa_id)
        ->distinct()
        ->count('dosen_pembimbing.id');
});

$totalPages = computed(function () {
    return $this->paginatedKomentar->lastPage();
});

$hasKontrakMagang = computed(function () {
    return KontrakMagang::where('mahasiswa_id', $this->mahasiswa_id)->exists();
});

$updateFilter = function ($filter) {
    $this->filter = $filter;
    $this->page = 1;
};

$updateSort = function ($sort) {
    $this->sort = $sort;
    $this->page = 1;
};

$updateSearch = function () {
    $this->page = 1;
};

$goToPage = function ($page) {
    $this->page = $page;
};

?>

<div>
    <x-slot:user>mahasiswa</x-slot:user>

    <div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-indigo-50">
        <!-- Header Section -->
        <div class="relative overflow-hidden bg-gradient-to-r from-sky-500/90 to-cyan-500/90 text-white rounded-2xl">
            <div class="absolute inset-0 bg-black/10"></div>
            <div class="relative container mx-auto px-6 py-12">
                <div class="max-w-4xl">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="p-3 bg-white/20 rounded-xl backdrop-blur-sm">
                            <flux:icon.chat-bubble-left-ellipsis class="w-8 h-8" />
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold">Umpan Balik Dosen</h1>
                            <p class="text-blue-100 mt-1">Pantau perkembangan dan saran dari dosen pembimbing</p>
                        </div>
                    </div>

                    @if ($this->hasKontrakMagang)
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-8">
                            <div class="bg-white/10 backdrop-blur-sm rounded-xl p-4 border border-white/20">
                                <div class="flex items-center gap-3">
                                    <flux:icon.chat-bubble-left class="w-6 h-6 text-blue-200" />
                                    <div>
                                        <div class="text-2xl font-bold">{{ $this->totalKomentar }}</div>
                                        <div class="text-sm text-blue-200">Total Komentar</div>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-white/10 backdrop-blur-sm rounded-xl p-4 border border-white/20">
                                <div class="flex items-center gap-3">
                                    <flux:icon.calendar-days class="w-6 h-6 text-blue-200" />
                                    <div>
                                        <div class="text-2xl font-bold">
                                            {{ $this->filteredKomentarCount['count'] }}
                                        </div>
                                        <div class="text-sm text-blue-200">{{ $this->filteredKomentarCount['label'] }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-white/10 backdrop-blur-sm rounded-xl p-4 border border-white/20">
                                <div class="flex items-center gap-3">
                                    <flux:icon.user-circle class="w-6 h-6 text-blue-200" />
                                    <div>
                                        <div class="text-2xl font-bold">
                                            {{ $this->dosenPembimbingCount }}
                                        </div>
                                        <div class="text-sm text-blue-200">Dosen Pembimbing</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="container mx-auto px-6 py-8">
            @if (!$this->hasKontrakMagang)
                <div class="max-w-2xl mx-auto">
                    <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
                        <div class="bg-gradient-to-r from-amber-400 to-orange-500 p-6">
                            <div class="flex items-center gap-4 text-white">
                                <div class="p-3 bg-white/20 rounded-xl">
                                    <flux:icon.exclamation-triangle class="w-8 h-8" />
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold">Belum Ada Kontrak Magang</h3>
                                    <p class="text-amber-100">Daftar magang untuk mulai menerima umpan balik</p>
                                </div>
                            </div>
                        </div>
                        <div class="p-8 text-center">
                            <p class="text-gray-600 mb-6">Silakan daftar magang terlebih dahulu untuk mulai menerima
                                umpan balik dari dosen pembimbing.</p>
                            <flux:button variant="primary">
                                <flux:icon.plus class="w-5 h-5 mr-2" />
                                Daftar Magang
                            </flux:button>
                        </div>
                    </div>
                </div>
            @elseif(true)
                <!-- Filters and Search -->
                <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 mb-8">
                    <div class="flex flex-col lg:flex-row gap-4 items-start lg:items-center justify-between">
                        <!-- Search Bar -->
                        <div class="flex-1 max-w-md">
                            <div class="relative">
                                <flux:icon.magnifying-glass
                                    class="w-5 h-5 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
                                <input type="text" wire:model.live.debounce.300ms="search"
                                    placeholder="Cari komentar atau nama dosen..."
                                    class="w-full pl-10 pr-4 py-3 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all" />
                            </div>
                        </div>

                        <!-- Filters -->
                        <div class="flex flex-wrap gap-3">
                            <!-- Date Filter -->
                            <div class="flex bg-gray-50 rounded-xl p-1">
                                <button wire:click="updateFilter('all')"
                                    class="px-4 py-2 text-sm font-medium rounded-lg transition-all {{ $this->filter === 'all' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-600 hover:text-gray-900' }}">
                                    Semua
                                </button>
                                <button wire:click="updateFilter('this_week')"
                                    class="px-4 py-2 text-sm font-medium rounded-lg transition-all {{ $this->filter === 'this_week' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-600 hover:text-gray-900' }}">
                                    Minggu Ini
                                </button>
                                <button wire:click="updateFilter('this_month')"
                                    class="px-4 py-2 text-sm font-medium rounded-lg transition-all {{ $this->filter === 'this_month' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-600 hover:text-gray-900' }}">
                                    Bulan Ini
                                </button>
                                <button wire:click="updateFilter('older')"
                                    class="px-4 py-2 text-sm font-medium rounded-lg transition-all {{ $this->filter === 'older' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-600 hover:text-gray-900' }}">
                                    Lama
                                </button>
                            </div>

                            <!-- Sort -->
                            <div class="flex bg-gray-50 rounded-xl p-1">
                                <button wire:click="updateSort('newest')"
                                    class="px-4 py-2 text-sm font-medium rounded-lg transition-all {{ $this->sort === 'newest' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-600 hover:text-gray-900' }}">
                                    <flux:icon.arrow-down class="w-4 h-4 inline mr-1" />
                                    Terbaru
                                </button>
                                <button wire:click="updateSort('oldest')"
                                    class="px-4 py-2 text-sm font-medium rounded-lg transition-all {{ $this->sort === 'oldest' ? 'bg-white text-blue-600 shadow-sm' : 'text-gray-600 hover:text-gray-900' }}">
                                    <flux:icon.arrow-up class="w-4 h-4 inline mr-1" />
                                    Terlama
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Grouped Comments -->
                @if ($this->paginatedKomentar->count() > 0)
                    @php
                        $currentGroup = null;
                        $paginatedItems = $this->paginatedKomentar;
                    @endphp

                    <div class="space-y-6">
                        @foreach ($paginatedItems as $komentar)
                            @php
                                $date = \Carbon\Carbon::parse($komentar->tanggal);
                                $now = now();

                                if ($date->isToday()) {
                                    $group = 'Hari Ini';
                                } elseif ($date->isYesterday()) {
                                    $group = 'Kemarin';
                                } elseif ($date->isCurrentWeek()) {
                                    $group = 'Minggu Ini';
                                } elseif ($date->isCurrentMonth()) {
                                    $group = 'Bulan Ini';
                                } elseif ($date->isLastMonth()) {
                                    $group = 'Bulan Lalu';
                                } else {
                                    $group = $date->translatedFormat('F Y');
                                }
                            @endphp

                            @if ($currentGroup !== $group)
                                @php $currentGroup = $group; @endphp
                                <div class="flex items-center gap-4 my-8">
                                    <div
                                        class="h-px bg-gradient-to-r from-transparent via-gray-300 to-transparent flex-1">
                                    </div>
                                    <div class="bg-white px-4 py-2 rounded-full border border-gray-200 shadow-sm">
                                        <span class="text-sm font-semibold text-gray-600">{{ $group }}</span>
                                    </div>
                                    <div
                                        class="h-px bg-gradient-to-r from-transparent via-gray-300 to-transparent flex-1">
                                    </div>
                                </div>
                            @endif

                            <div
                                class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden hover:shadow-xl transition-all duration-300 group">
                                <!-- Header -->
                                <div
                                    class="bg-gradient-to-r from-blue-50 to-indigo-50 px-6 py-4 border-b border-gray-100">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg">
                                                <flux:icon.user-circle class="w-6 h-6 text-white" />
                                            </div>
                                            <div>
                                                <h3 class="font-semibold text-gray-900">
                                                    {{ $komentar->kontrakMagang->dosenPembimbing->nama }}</h3>
                                                <p class="text-sm text-gray-600 flex items-center gap-1">
                                                    <flux:icon.building-office-2 class="w-4 h-4" />
                                                    {{ $komentar->kontrakMagang->lowonganMagang->perusahaan->nama }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="flex items-center gap-2 text-sm text-gray-500">
                                                <flux:icon.calendar-days class="w-4 h-4" />
                                                {{ \Carbon\Carbon::parse($komentar->tanggal)->translatedFormat('d M Y') }}
                                            </div>
                                            <div class="text-xs text-gray-400 mt-1">
                                                {{ \Carbon\Carbon::parse($komentar->tanggal)->diffForHumans() }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Content -->
                                <div class="p-6">
                                    <div class="flex items-start gap-4">
                                        <div
                                            class="w-10 h-10 bg-gradient-to-br from-emerald-400 to-teal-500 rounded-lg flex items-center justify-center flex-shrink-0">
                                            <flux:icon.chat-bubble-left class="w-5 h-5 text-white" />
                                        </div>
                                        <div class="flex-1">
                                            <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                                                <p class="text-gray-700 leading-relaxed">{{ $komentar->komentar }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Enhanced Pagination -->
                    @if ($this->totalPages > 1)
                        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 mt-8">
                            <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                                <div class="text-sm text-gray-600">
                                    Menampilkan {{ $this->paginatedKomentar->firstItem() }} -
                                    {{ $this->paginatedKomentar->lastItem() }} dari
                                    {{ $this->paginatedKomentar->total() }} komentar
                                </div>

                                <div class="flex items-center gap-2">
                                    @if ($this->page > 1)
                                        <button wire:click="goToPage({{ $this->page - 1 }})"
                                            class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                            <flux:icon.chevron-left class="w-4 h-4" />
                                            Previous
                                        </button>
                                    @endif

                                    <div class="flex items-center gap-1">
                                        @for ($i = max(1, $this->page - 2); $i <= min($this->totalPages, $this->page + 2); $i++)
                                            <button wire:click="goToPage({{ $i }})"
                                                class="w-10 h-10 text-sm font-medium rounded-lg transition-colors {{ $i === $this->page ? 'bg-blue-600 text-white shadow-lg' : 'text-gray-600 hover:bg-gray-50' }}">
                                                {{ $i }}
                                            </button>
                                        @endfor
                                    </div>

                                    @if ($this->page < $this->totalPages)
                                        <button wire:click="goToPage({{ $this->page + 1 }})"
                                            class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                            Next
                                            <flux:icon.chevron-right class="w-4 h-4" />
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif
                @else
                    <div class="text-center py-16">
                        @if ($this->search)
                            <flux:icon.magnifying-glass class="w-16 h-16 text-gray-300 mx-auto mb-4" />
                            <h3 class="text-lg font-semibold text-gray-700 mb-2">Tidak ditemukan hasil pencarian
                            </h3>
                            <p class="text-gray-500 mb-4">Tidak ada komentar yang sesuai dengan kata kunci
                                "{{ $this->search }}"</p>
                        @else
                            <flux:icon.chat-bubble-left-right class="w-16 h-16 text-gray-300 mx-auto mb-4" />
                            <h3 class="text-lg font-semibold text-gray-700 mb-2">
                                @if ($this->filter === 'this_week')
                                    Belum ada komentar minggu ini
                                @elseif($this->filter === 'this_month')
                                    Belum ada komentar bulan ini
                                @elseif($this->filter === 'older')
                                    Belum ada komentar lama
                                @else
                                    Belum ada komentar
                                @endif
                            </h3>
                            <p class="text-gray-500">Coba ubah filter atau tunggu umpan balik dari dosen pembimbing
                            </p>
                        @endif
                    </div>
                @endif
            @else
                <div class="max-w-2xl mx-auto">
                    <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
                        <div class="bg-gradient-to-r from-gray-400 to-gray-600 p-6">
                            <div class="flex items-center gap-4 text-white">
                                <div class="p-3 bg-white/20 rounded-xl">
                                    <flux:icon.chat-bubble-left-right class="w-8 h-8" />
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold">Belum Ada Komentar</h3>
                                    <p class="text-gray-100">Tunggu umpan balik dari dosen pembimbing</p>
                                </div>
                            </div>
                        </div>
                        <div class="p-8 text-center">
                            <p class="text-gray-600 mb-6">Belum ada komentar atau umpan balik dari dosen pembimbing
                                Anda. Pastikan Anda aktif dalam kegiatan magang.</p>
                            <div class="flex justify-center gap-4">
                                <flux:button variant="outline">
                                    <flux:icon.arrow-path class="w-5 h-5 mr-2" />
                                    Refresh
                                </flux:button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Tips Section -->
            @if ($this->totalKomentar > 0)
                <div
                    class="bg-gradient-to-r from-emerald-50 to-teal-50 rounded-2xl border border-emerald-200 p-6 mt-8">
                    <div class="flex items-start gap-4">
                        <div
                            class="w-12 h-12 bg-gradient-to-br from-emerald-400 to-teal-500 rounded-xl flex items-center justify-center flex-shrink-0">
                            <flux:icon.light-bulb class="w-6 h-6 text-white" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-emerald-900 mb-2">Tips Memaksimalkan Umpan Balik</h3>
                            <p class="text-emerald-700 text-sm leading-relaxed">
                                Komentar dan umpan balik dari dosen sangat berharga untuk pengembangan Anda.
                                Gunakan masukan tersebut untuk memperbaiki diri, belajar lebih baik, dan tunjukkan
                                progress yang konsisten dalam kegiatan magang Anda.
                            </p>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
