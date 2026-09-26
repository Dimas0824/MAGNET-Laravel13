<?php
use Illuminate\Support\Facades\Auth;
use App\Models\Mahasiswa;
use App\Models\LogMagang;
use App\Models\KontrakMagang;
use Illuminate\Support\Str;
use function Livewire\Volt\{layout, state, mount, computed};

layout('components.layouts.user.main');

state([
    'mahasiswa' => null,
    'kontrak_magang' => null,
    'logs' => [],
    'currentPage' => 1,
    'perPage' => 10,
    'totalLogs' => 0,
]);

mount(function () {
    $this->mahasiswa = Auth::guard('mahasiswa')->user();

    if ($this->mahasiswa) {
        $this->kontrak_magang = KontrakMagang::where('mahasiswa_id', $this->mahasiswa->id)
            ->with(['lowonganMagang.perusahaan', 'lowonganMagang.pekerjaan'])
            ->latest()
            ->first();
    }

    $this->loadLogs();
});

$loadLogs = function () {
    if (!$this->kontrak_magang) {
        $this->logs = [];
        $this->totalLogs = 0;
        return;
    }

    $query = LogMagang::where('kontrak_magang_id', $this->kontrak_magang->id)->orderBy('tanggal', 'desc');

    $this->totalLogs = $query->count();

    $this->logs = $query
        ->skip(($this->currentPage - 1) * $this->perPage)
        ->take($this->perPage)
        ->get()
        ->toArray(); // Convert to array to ensure it's always an array
};

$changePage = function ($page) {
    $this->currentPage = $page;
    $this->loadLogs();
};

$changePerPage = function ($perPage) {
    $this->perPage = $perPage;
    $this->currentPage = 1;
    $this->loadLogs();
};

$downloadReport = function () {
    session()->flash('message', 'Laporan sedang diproses dan akan segera diunduh.');
};

$totalPages = computed(function () {
    return ceil($this->totalLogs / $this->perPage);
});

$startRecord = computed(function () {
    return ($this->currentPage - 1) * $this->perPage + 1;
});

$endRecord = computed(function () {
    return min($this->currentPage * $this->perPage, $this->totalLogs);
});
?>

<x-slot:user>mahasiswa</x-slot:user>

<div class="h-full bg-gradient-to-br from-blue-50 via-white to-indigo-50 p-4 rounded-2xl shadow-lg">
    <div class="max-w-6xl mx-auto">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Detail Log Magang</h1>
                @if ($this->kontrak_magang && $this->kontrak_magang->lowonganMagang)
                    <p class="text-gray-600">
                        {{ $this->kontrak_magang->lowonganMagang->pekerjaan->nama }} -
                        {{ $this->kontrak_magang->lowonganMagang->perusahaan->nama }}
                    </p>
                @endif
            </div>

            <div class="flex gap-3 mt-4 sm:mt-0">
                <button wire:click="downloadReport"
                    class="bg-gradient-to-r from-teal-500 to-cyan-600 hover:from-teal-600 hover:to-cyan-700 text-white font-semibold py-2 px-4 rounded-xl transition-all duration-300 hover:scale-105 shadow-lg hover:shadow-xl flex items-center space-x-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span>Unduh Laporan</span>
                </button>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            @if (is_array($logs) && count($logs) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gradient-to-r from-gray-50 to-gray-100 border-b border-gray-200">
                            <tr>
                                <th
                                    class="px-6 py-4 text-center text-sm font-semibold text-gray-700 uppercase tracking-wider">
                                    No</th>
                                <th
                                    class="px-6 py-4 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">
                                    Tanggal</th>
                                <th
                                    class="px-6 py-4 text-left text-sm font-semibold text-gray-700 uppercase tracking-wider">
                                    Kegiatan</th>
                                <th
                                    class="px-6 py-4 text-center text-sm font-semibold text-gray-700 uppercase tracking-wider">
                                    Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach ($logs as $index => $log)
                                <tr class="hover:bg-gray-50 transition-colors duration-200 cursor-pointer group"
                                    onclick="window.location='{{ url('/log-magang?id=' . (is_array($log) ? $log['id'] : $log->id)) }}'">
                                    <td class="px-6 py-4 text-center text-sm text-gray-900">
                                        {{ $startRecord + $index }}
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <div class="flex items-center space-x-3">
                                            <div
                                                class="w-10 h-10 bg-gradient-to-br from-blue-100 to-indigo-100 rounded-xl flex items-center justify-center">
                                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M8 7V3a2 2 0 012-2h4a2 2 0 012 2v4m-6 0a2 2 0 00-2 2v6a2 2 0 002 2h12a2 2 0 002-2V9a2 2 0 00-2-2h-6z" />
                                                </svg>
                                            </div>
                                            <div>
                                                <p class="font-medium text-gray-900">
                                                    {{ \Carbon\Carbon::parse(is_array($log) ? $log['tanggal'] : $log->tanggal)->format('d M Y') }}
                                                </p>
                                                <p class="text-sm text-gray-500">
                                                    {{ \Carbon\Carbon::parse(is_array($log) ? $log['tanggal'] : $log->tanggal)->format('l') }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <div class="max-w-md">
                                            <p class="text-gray-900 font-medium truncate"
                                                title="{{ is_array($log) ? $log['kegiatan'] : $log->kegiatan }}">
                                                {{ Str::limit(is_array($log) ? $log['kegiatan'] : $log->kegiatan, 80) }}
                                            </p>
                                            <p class="text-gray-500 text-xs">
                                                Jam:
                                                {{ \Carbon\Carbon::parse(is_array($log) ? $log['jam_masuk'] : $log->jam_masuk)->format('H:i') }}
                                                -
                                                {{ \Carbon\Carbon::parse(is_array($log) ? $log['jam_keluar'] : $log->jam_keluar)->format('H:i') }}
                                            </p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <svg class="w-5 h-5 text-gray-400 group-hover:text-blue-500 transition-colors mx-auto"
                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 5l7 7-7 7" />
                                        </svg>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="bg-gray-50 px-6 py-4 flex items-center justify-between border-t border-gray-200">
                    <div class="text-sm text-gray-700">
                        Menampilkan <span class="font-medium">{{ $startRecord }}</span> sampai
                        <span class="font-medium">{{ $endRecord }}</span> dari
                        <span class="font-medium">{{ $totalLogs }}</span> data
                    </div>

                    <div class="flex items-center space-x-2">
                        <button wire:click="changePage({{ $currentPage - 1 }})"
                            {{ $currentPage <= 1 ? 'disabled' : '' }}
                            class="p-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>

                        @for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++)
                            <button wire:click="changePage({{ $i }})"
                                class="px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200 {{ $i === $currentPage ? 'bg-blue-500 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
                                {{ $i }}
                            </button>
                        @endfor

                        <button wire:click="changePage({{ $currentPage + 1 }})"
                            {{ $currentPage >= $totalPages ? 'disabled' : '' }}
                            class="p-2 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>

                    <div class="flex items-center space-x-2 text-sm text-gray-700">
                        <span>Baris per halaman:</span>
                        <select wire:change="changePerPage($event.target.value)"
                            class="border border-gray-300 rounded-lg px-3 py-1 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="10" {{ $perPage == 10 ? 'selected' : '' }}>10</option>
                            <option value="25" {{ $perPage == 25 ? 'selected' : '' }}>25</option>
                            <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50</option>
                            <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100</option>
                        </select>
                    </div>
                </div>
            @else
                <div class="text-center py-12">
                    <div
                        class="w-24 h-24 mx-auto mb-6 bg-gradient-to-br from-gray-100 to-gray-200 rounded-full flex items-center justify-center">
                        <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">Belum Ada Log Magang</h3>
                    <p class="text-gray-600 mb-6">
                        Mulai catat aktivitas magang Anda setiap hari.
                    </p>
                </div>
            @endif
        </div>

        @if (session()->has('message'))
            <div class="mt-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl">
                {{ session('message') }}
            </div>
        @endif
    </div>
</div>
