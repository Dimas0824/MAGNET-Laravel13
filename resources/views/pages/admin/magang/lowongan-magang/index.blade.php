<?php

use App\Models\LowonganMagang;

use function Livewire\Volt\layout;
use function Livewire\Volt\state;
use function Livewire\Volt\usesPagination;
use function Livewire\Volt\with;

layout('components.layouts.user.main');

state([
    'totalRowsPerPage' => 10,
]);

usesPagination();

with(function () {
    return [
        'dataLowonganMagang' => LowonganMagang::select('id', 'perusahaan_id', 'lokasi_magang_id', 'pekerjaan_id', 'status')
            ->with([
                'perusahaan' => function ($query) {
                    $query->select('id', 'nama');
                },
                'lokasiMagang' => function ($query) {
                    $query->select('id', 'lokasi');
                },
                'pekerjaan' => function ($query) {
                    $query->select('id', 'nama');
                },
            ])
            ->withCount(['kontrak_magang as jumlah_pendaftar'])
            ->orderBy('created_at', 'desc')
            ->paginate($this->totalRowsPerPage),
    ];
});

$goToSpecificPage = fn (int $page) => $this->setPage($page);
$goToPrevPage = fn () => $this->previousPage();
$goToNextPage = fn () => $this->nextPage();

?>

<div class="flex flex-col gap-5">
    <x-slot:user>admin</x-slot:user>

    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" icon:variant="outline" />
        <flux:breadcrumbs.item class="text-black">Kelola data lowongan magang
        </flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <h1 class="text-base font-bold leading-6 text-black">Kelola Data Lowongan Magang</h1>

    <div class="flex justify-between">
        <div class="flex gap-3">
            <flux:input class="rounded-3xl!" placeholder="Cari Data Mahasiswa" icon="magnifying-glass" />
            <flux:button variant="primary" class="bg-white! text-black! w-17 rounded-full!"
                icon="arrow-down-wide-narrow"></flux:button>
            <flux:button variant="primary" class="bg-white! text-black! w-17 rounded-full!" icon="funnel-plus">
            </flux:button>
        </div>
        <div class="flex gap-3">
            <flux:modal.trigger name="add-new-data">
                <flux:button variant="primary" class="bg-magnet-sky-teal" icon="plus">Tambah Magang</flux:button>
            </flux:modal.trigger>
            <flux:button variant="primary" class="bg-magnet-sky-teal" icon="download">Import</flux:button>
            <flux:button variant="primary" class="bg-magnet-sky-teal" icon="upload">Export</flux:button>
        </div>
    </div>

    <div class="overflow-y-auto flex flex-col items-center mt-4 rounded-lg shadow bg-white">
        <table class="table-auto w-full ">
            <thead class="bg-white text-black">
                <tr class="border-b">
                    <th class="text-center px-6 py-3">No</th>
                    <th class="text-left px-6 py-3">Perusahaan</th>
                    <th class="text-left px-6 py-3">Pekerjaan</th>
                    <th class="text-left px-6 py-3">Lokasi</th>
                    <th class="text-center px-6 py-3">Status</th>
                    <th class="text-center px-6 py-3">Jumlah Pendaftar</th>
                    <th class="text-center px-6 py-3">Detail</th>
                </tr>
            </thead>
            <tbody class="bg-white text-black">
                @foreach ($dataLowonganMagang as $lowongan)
                    <tr onclick="window.location.href='{{ route('admin.detail-lowongan', $lowongan['id']) }}'"  class="border-b hover:bg-gray-50">
                        <td class="px-6 py-3 text-center">
                            {{ $loop->iteration + ($dataLowonganMagang->firstItem() - 1) }}
                        </td>
                        <td class="px-6 py-3">{{ $lowongan['perusahaan']['nama'] }}</td>
                        <td class="px-6 py-3">{{ $lowongan['pekerjaan']['nama'] }}</td>
                        <td class="px-6 py-3">{{ $lowongan['lokasiMagang']['lokasi'] }}</td>
                        <td class="px-6 py-3">
                            @php
                                $badgeColor = match ($lowongan['status']) {
                                    'tutup' => 'red',
                                    'buka' => 'green',
                                }
                            @endphp
                            <flux:badge variant="solid" color="{{ $badgeColor }}">{{ ucfirst($lowongan['status']) }}</flux:badge>
                        </td>
                        <td class="px-6 py-3 text-right">{{ $lowongan['jumlah_pendaftar'] }}</td>
                        <td class="px-6 py-3 text-center">
                            <flux:button icon="chevron-right" href="{{ route('admin.detail-lowongan', $lowongan['id']) }}"
                                variant="ghost" />
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="flex items-center justify-between w-full px-8 py-4">
            <p>Menampilkan {{ $dataLowonganMagang->count() }} dari {{ $dataLowonganMagang->total() }} data</p>

            <div class="flex">
                <flux:button icon="chevron-left" variant="ghost" wire:click="goToPrevPage" />
                @for ($i = $dataLowonganMagang->currentPage(); $i <= $dataLowonganMagang->currentPage() + 5 && $i < $dataLowonganMagang->lastPage(); $i++)
                    <flux:button variant="ghost" wire:click="goToSpecificPage({{ $i }})">
                        {{ $i }}
                    </flux:button>
                @endfor

                @if ($dataLowonganMagang->lastPage() > 6)
                    <flux:button variant="ghost" disabled>...</flux:button>
                    <flux:button variant="ghost" wire:click="goToSpecificPage({{ $dataLowonganMagang->lastPage() }})">
                        {{ $dataLowonganMagang->lastPage() }}
                    </flux:button>
                @endif
                <flux:button icon="chevron-right" variant="ghost" wire:click="goToNextPage" />
            </div>
            <div class="flex gap-3 items-center text-black">
                <p>Baris per halaman</p>
                <flux:select class="w-20!" wire:model.live="totalRowsPerPage">
                    <flux:select.option>10</flux:select.option>
                    <flux:select.option>25</flux:select.option>
                    <flux:select.option>50</flux:select.option>
                    <flux:select.option>100</flux:select.option>
                </flux:select>
            </div>
        </div>
    </div>

    <livewire:components.admin.lowongan-magang.tambah-magang />
    <livewire:components.general.modal.response-form />
</div>
