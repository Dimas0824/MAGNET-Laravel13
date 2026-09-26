<?php

use function Livewire\Volt\{state};
use App\Models\{FormPengajuanMagang, KontrakMagang, BidangIndustri};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

$statusCounts = FormPengajuanMagang::query()
    ->select('status', DB::raw('COUNT(*) as total'))
    ->groupBy('status')
    ->pluck('total', 'status');

state([
    'totalPengajuanMasuk' => (int) ($statusCounts['diproses'] ?? 0),
    'totalPengajuanDiterima' => (int) ($statusCounts['diterima'] ?? 0),
    'totalPengajuanDitolak' => (int) ($statusCounts['ditolak'] ?? 0),

    'totalKontrakMagangTahunIni' => KontrakMagang::whereYear('created_at', Carbon::now()->year)->count(),

    'bidangIndustriTerpopuler' => BidangIndustri::withCount([
        'perusahaan as total_mahasiswa' => function ($query) {
            $query->join('lowongan_magang', 'perusahaan.id', '=', 'lowongan_magang.perusahaan_id')->join('kontrak_magang', 'lowongan_magang.id', '=', 'kontrak_magang.lowongan_magang_id');
        },
    ])
        ->orderByDesc('total_mahasiswa')
        ->take(5)
        ->get()
        ->toArray(),
]);

?>

<div class="flex flex-col gap-5">
    <x-slot:topScript>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    </x-slot:topScript>

    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" icon:variant="outline" />
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" class="text-black">Dashboard</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="flex flex-col gap-12">
        {{-- atas --}}
        <div class="flex justify-between w-full gap-9">
            <div class="w-full rounded-md border border-magnet-def-grey-400 bg-white flex flex-col items-center">
                <span class="flex gap-3 p-3">
                    <flux:icon.arrow-down-to-line />
                    Pengajuan Magang Masuk
                </span>
                <p class="font-bold text-xl">{{ $totalPengajuanMasuk }}</p>
                <flux:button variant="primary"
                    class="w-full border-t-gray-400! bg-white! rounded-b-md! text-black! font-black! rounded-t-none!">
                    Lihat Semua
                </flux:button>
            </div>
            <div class="w-full rounded-md border border-magnet-def-grey-400 bg-white flex flex-col items-center">
                <span class="flex gap-3 p-3">
                    <flux:icon.check />
                    Pengajuan Magang Diterima
                </span>
                <p class="font-bold text-xl">{{ $totalPengajuanDiterima }}</p>
                <flux:button variant="primary"
                    class="w-full border-t-gray-400! bg-white! rounded-b-md! text-black! font-black! rounded-t-none!">
                    Lihat Semua
                </flux:button>
            </div>
            <div class="w-full rounded-md border border-magnet-def-grey-400 bg-white flex flex-col items-center">
                <span class="flex gap-3 p-3">
                    <flux:icon.x />
                    Pengajuan Magang Ditolak
                </span>
                <p class="font-bold text-xl">{{ $totalPengajuanDitolak }}</p>
                <flux:button variant="primary"
                    class="w-full border-t-gray-400! bg-white! rounded-b-md! text-black! font-black! rounded-t-none!">
                    Lihat Semua
                </flux:button>
            </div>
        </div>
        {{-- bawah --}}

        <div class="grid lg:grid-cols-2 md:grid-cols-1 w-full gap-11 text-black">
            <div class="w-full bg-white rounded-md border border-magnet-def-grey-400 p-6">
                <div class="flex flex-col h-full">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 text-center">Tren Mahasiswa Magang</h3>
                    <div class="flex-1 relative" style="min-height: 300px;">
                        <canvas id="magangChart" class="w-full h-full"></canvas>
                    </div>
                </div>
            </div>
            <div class="w-full bg-white rounded-md border border-magnet-def-grey-400 p-4">
                <div class="px-6 pb-4 border-b border-gray-200">
                    <h3 class="pt-2 text-lg font-semibold text-gray-900 text-center">Bidang industri terpopuler</h3>
                </div>
                <div class="p-4 space-y-1">
                    @foreach ($bidangIndustriTerpopuler as $bidangIndustri)
                        <div class="flex items-center justify-between py-3 border-b border-gray-100">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                    <span class="text-sm font-semibold text-blue-600">{{ $loop->iteration }}</span>
                                </div>
                                <p class="font-medium text-gray-900">{{ $bidangIndustri['nama'] }}</p>
                            </div>
                            <span class="text-sm font-semibold text-gray-900">{{ $bidangIndustri['total_mahasiswa'] }}
                                mahasiswa magang</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

    </div>

    {{-- Script untuk Chart --}}
    <script>
        const ctx = document.getElementById('magangChart').getContext('2d');
        const magangChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['2024', '2025'],
                datasets: [{
                    label: 'Jumlah Mahasiswa',
                    data: [0, {{ $totalKontrakMagangTahunIni }}],
                    borderColor: 'brown',
                    backgroundColor: 'transparent',
                    tension: 0,
                    borderWidth: 2,
                    pointRadius: 4,
                    pointBackgroundColor: 'brown'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>

</div>
