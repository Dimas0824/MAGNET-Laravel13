<?php
use function Livewire\Volt\{layout, state, mount, computed};
use App\Models\Perusahaan;
use App\Models\UlasanMagang;
use App\Models\LowonganMagang;

layout('components.layouts.user.main');

state([
    'perusahaan',
    'perusahaanId' => null,
    'lowonganId' => null,
    'totalUlasan' => 0,
    'rataRating' => 0,
    'isDataNotFound' => false
]);

mount(function (int $id) {
    $this->perusahaanId = $id;

    try {
        $this->perusahaan = Perusahaan::findOrFail($id);
    } catch (\Exception $e) {
        $this->isDataNotFound = true;

        return;
    }

    // Compute rating statistics once on mount (no side effects in getters).
    $ulasanData = UlasanMagang::whereHas('kontrakMagang.lowonganMagang', function ($query) use ($id) {
        $query->where('perusahaan_id', $id);
    })->get();

    $this->totalUlasan = $ulasanData->count();
    $this->rataRating = $this->totalUlasan > 0 ? round($ulasanData->avg('rating'), 1) : 0;
});

$lowonganLainnya = computed(function () {
    try {
        if (! $this->perusahaan) {
            return collect();
        }

        return $this->perusahaan
            ->lowonganMagang()
            ->with(['pekerjaan', 'lokasiMagang'])
            ->where('status', 'buka')
            ->when($this->lowonganId, fn ($q) => $q->where('id', '!=', $this->lowonganId))
            ->take(3)
            ->get();
    } catch (\Exception $e) {
        return collect();
    }
});
?>

<div class="flex flex-col gap-5">
    <x-slot:user>mahasiswa</x-slot:user>


    @if (!$isDataNotFound)
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Profil Perusahaan</h1>
        </div>

        <div class="space-y-6">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="bg-gradient-to-r from-gray-50 to-gray-100 p-8">
                    <div class="flex items-start gap-6">
                        <div class="relative">
                            <span
                                class="w-20 h-20 object-contain rounded-lg border border-gray-200 bg-white p-2 flex justify-center items-center">
                                {{ $perusahaan->nama[0] }}
                            </span>
                            <div
                                class="absolute -bottom-2 -right-2 w-6 h-6 bg-green-500 rounded-full border-2 border-white">
                            </div>
                        </div>
                        <div class="flex-1">
                            <h2 class="text-2xl font-bold text-gray-900 mb-2">{{ $perusahaan->nama }}</h2>
                            <div class="space-y-2">
                                <div class="flex items-center text-gray-600">
                                    <flux:icon.map-pin class="mr-2 h-4 w-4" />
                                    <span>{{ $perusahaan->lokasi }}</span>
                                </div>
                                <div class="flex items-center text-gray-600">
                                    <flux:icon.building class="mr-2 h-4 w-4" />
                                    <span>{{ $perusahaan->bidangIndustri->nama }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-6 border-b border-gray-100">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="text-center">
                            <div class="w-12 h-12 mx-auto mb-3 bg-blue-100 rounded-xl flex items-center justify-center">
                                <flux:icon.handshake class="h-6 w-6 text-blue-600" />
                            </div>
                            <h4 class="font-semibold text-gray-900">{{ ucfirst(str_replace('_', ' ', $perusahaan->kategori)) }}</h4>
                            <p class="text-sm text-gray-500 mt-1">Status Kemitraan</p>
                        </div>

                        <div class="text-center">
                            <div
                                class="w-12 h-12 mx-auto mb-3 bg-yellow-100 rounded-xl flex items-center justify-center">
                                <flux:icon.star class="h-6 w-6 text-yellow-600" />
                            </div>
                            <h4 class="font-semibold text-gray-900">
                                @if ($totalUlasan > 0)
                                    {{ $rataRating }}/5
                                @else
                                    Belum ada
                                @endif
                            </h4>
                            <p class="text-sm text-gray-500 mt-1">
                                @if ($totalUlasan > 0)
                                    {{ $totalUlasan }} ulasan
                                @else
                                    Penilaian
                                @endif
                            </p>
                        </div>

                        <div class="text-center">
                            <div
                                class="w-12 h-12 mx-auto mb-3 bg-green-100 rounded-xl flex items-center justify-center">
                                <flux:icon.globe class="h-6 w-6 text-green-600" />
                            </div>
                            <a href="http://{{ $perusahaan->website }}" target="_blank"
                                class="font-semibold text-blue-600 hover:text-blue-800 transition-colors">
                                {{ $perusahaan->website }}
                            </a>
                            <p class="text-sm text-gray-500 mt-1">Website Resmi</p>
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">Tentang Kami</h3>
                    <div class="prose prose-gray max-w-none">
                        <p class="text-gray-700 leading-relaxed">
                            {{ $perusahaan->deskripsi }}
                        </p>
                    </div>
                </div>
            </div>

            @if ($this->lowonganLainnya->count() > 0)
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-semibold text-gray-900">Lowongan Magang Lainnya</h3>
                        <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">
                            {{ $this->lowonganLainnya->count() }} tersedia
                        </span>
                    </div>

                    <div class="grid gap-4">
                        @foreach ($this->lowonganLainnya as $lowongan)
                            <div
                                class="border border-gray-200 rounded-xl p-5 hover:border-blue-300 hover:bg-blue-50/50 transition-all duration-200 group">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <h4 class="font-semibold text-gray-900 group-hover:text-blue-900 mb-2">
                                            {{ $lowongan->nama }}
                                        </h4>
                                        <p class="text-gray-600 mb-3">
                                            {{ $lowongan->pekerjaan ? $lowongan->pekerjaan->nama : 'Posisi tidak tersedia' }}
                                        </p>

                                        <div class="flex flex-wrap gap-4 text-sm text-gray-500">
                                            <span class="flex items-center gap-1">
                                                <flux:icon.users class="h-4 w-4" />
                                                {{ $lowongan->kuota ?? 0 }} posisi
                                            </span>
                                            <span class="flex items-center gap-1">
                                                <flux:icon.map-pin class="h-4 w-4" />
                                                {{ $lowongan->lokasiMagang ? $lowongan->lokasiMagang->lokasi : 'Lokasi fleksibel' }}
                                            </span>
                                            <span
                                                class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                                {{ ($lowongan->jenis_magang ?? '') === 'berbayar' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                                {{ ucfirst(str_replace('_', ' ', $lowongan->jenis_magang ?? 'tidak berbayar')) }}
                                            </span>
                                        </div>
                                    </div>
                                    <a href="{{ url('/profil-perusahaan?id=' . $lowongan->id) }}"
                                        class="ml-4 inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium">
                                        Lihat Detail
                                        <flux:icon.arrow-right class="h-4 w-4" />
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @php
                        $totalLowonganAktif = $perusahaan->lowonganMagang()->where('status', 'buka')->count();
                    @endphp
                    @if ($totalLowonganAktif > 3)
                        <div class="text-center mt-6 pt-6 border-t border-gray-200">
                            <button
                                class="inline-flex items-center gap-2 text-blue-600 hover:text-blue-800 font-medium">
                                <span>Lihat Semua Lowongan ({{ $totalLowonganAktif }})</span>
                                <flux:icon.arrow-right class="h-4 w-4" />
                            </button>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    @else
        <div class="bg-white rounded-2xl shadow-sm border border-red-200 p-8">
            <div class="text-center">
                <div class="w-16 h-16 mx-auto mb-4 bg-red-100 rounded-full flex items-center justify-center">
                    <flux:icon.exclamation-triangle class="h-8 w-8 text-red-500" />
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">Data Perusahaan Tidak Ditemukan</h3>
                <p class="text-gray-600 mb-8 leading-relaxed">
                    Maaf, data perusahaan yang Anda cari tidak tersedia atau mungkin telah dihapus.
                    Silakan kembali ke halaman utama untuk melihat data perusahaan lainnya.
                </p>
                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    <flux:button variant="primary" href="{{ route('dashboard') }}" icon="arrow-left">
                        Kembali dashboard
                    </flux:button>
                    <flux:button variant="ghost" onclick="history.back()" icon="arrow-uturn-left">
                        Halaman Sebelumnya
                    </flux:button>
                </div>
            </div>
        </div>
    @endif
</div>
