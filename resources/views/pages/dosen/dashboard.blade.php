<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Mahasiswa;
use App\Models\KontrakMagang;
use App\Models\DosenPembimbing;
use function Livewire\Volt\{layout, state, mount, computed, with};

state([
    'perPage' => 10,
    'search' => '',
    'currentPage' => 1,
]);

layout('components.layouts.user.main');

mount(function () {
    // Pastikan user adalah dosen
    if (!Auth::guard('dosen')->check()) {
        abort(403, 'Unauthorized access');
    }
});

$mahasiswaBimbingan = computed(function () {
    $dosenId = Auth::guard('dosen')->id();

    $query = Mahasiswa::query()
        ->whereHas('kontrakMagang', function ($query) use ($dosenId) {
            $query->where('dosen_id', $dosenId);
        })
        // Eager-load the first contract with its relations (no lazy N+1).
        ->with([
            'kontrakMagang' => function ($q) use ($dosenId) {
                $q->where('dosen_id', $dosenId)
                    ->with(['lowonganMagang.perusahaan', 'lowonganMagang.pekerjaan'])
                    ->withCount([
                        'logMagang as has_recent_log' => fn ($lq) => $lq->where('created_at', '>=', now()->subDays(7)),
                        'umpanBalikMagang as has_feedback' => fn ($uq) => $uq->whereNotNull('komentar')->where('komentar', '!=', ''),
                    ]);
            },
        ]);

    // Search
    if (! empty($this->search)) {
        $searchTerm = '%'.$this->search.'%';
        $query->where(function ($q) use ($searchTerm) {
            $q->where('nama', 'like', $searchTerm)
                ->orWhere('nim', 'like', $searchTerm)
                ->orWhere('email', 'like', $searchTerm);
        });
    }

    return $query->orderBy('nama')
        ->paginate($this->perPage, ['*'], 'page', $this->currentPage)
        ->through(function ($mahasiswa) {
            // whereHas guarantees at least one contract for this dosen.
            $kontrak = $mahasiswa->kontrakMagang->first();

            return [
                'id' => $mahasiswa->id,
                'nama' => $mahasiswa->nama,
                'nim' => $mahasiswa->nim,
                'email' => $mahasiswa->email,
                'status_magang' => $mahasiswa->status_magang,
                'kontrak_id' => $kontrak->id,
                'waktu_awal' => $kontrak->waktu_awal,
                'waktu_akhir' => $kontrak->waktu_akhir,
                'perusahaan_nama' => $kontrak->lowonganMagang->perusahaan->nama ?? '-',
                'posisi_nama' => $kontrak->lowonganMagang->pekerjaan->nama ?? '-',
                'status_log' => ($kontrak->has_recent_log ?? 0) > 0 ? 'Sudah dibaca' : 'Belum dibaca',
                'status_feedback' => ($kontrak->has_feedback ?? 0) > 0 ? 'Sudah diberikan' : 'Belum diberikan',
            ];
        });
});

$paginatedMahasiswa = computed(function () {
    return $this->mahasiswaBimbingan;
});

$bimbinganStats = computed(function () {
    $dosenId = Auth::guard('dosen')->id();

    $row = KontrakMagang::where('dosen_id', $dosenId)
        ->join('mahasiswa', 'kontrak_magang.mahasiswa_id', '=', 'mahasiswa.id')
        ->selectRaw('COUNT(*) as total')
        ->selectRaw("SUM(CASE WHEN mahasiswa.status_magang = 'selesai magang' THEN 1 ELSE 0 END) as selesai")
        ->first();

    return [
        'total' => (int) ($row->total ?? 0),
        'selesai' => (int) ($row->selesai ?? 0),
    ];
});

$totalMahasiswa = computed(function () {
    return $this->bimbinganStats['total'];
});

$mahasiswaSelesai = computed(function () {
    return $this->bimbinganStats['selesai'];
});

$feedbackDiberikan = computed(function () {
    $dosenId = Auth::guard('dosen')->id();

    return KontrakMagang::where('dosen_id', $dosenId)
        ->whereExists(function ($query) {
            $query
                ->select(DB::raw(1))
                ->from('umpan_balik_magang')
                ->whereRaw('umpan_balik_magang.kontrak_magang_id = kontrak_magang.id')
                ->where('umpan_balik_magang.created_at', '>=', now()->subDays(30))
                ->whereNotNull('umpan_balik_magang.komentar')
                ->where('umpan_balik_magang.komentar', '!=', '');
        })
        ->count();
});

$statusProfil = computed(function () {
    $dosen = Auth::guard('dosen')->user();
    return $dosen && $dosen->nama && $dosen->nidn ? 'Lengkap' : 'Belum Lengkap';
});

// Actions
$updateSearch = function ($value) {
    $this->search = $value;
    $this->currentPage = 1; // Reset ke halaman pertama saat search
};

$clearSearch = function () {
    $this->search = '';
    $this->currentPage = 1;
};

$updatePerPage = function ($value) {
    $this->perPage = $value;
    $this->currentPage = 1;
};

$goToPage = function ($page) {
    $this->currentPage = $page;
};

$lihatDetail = function ($mahasiswaId) {
    return redirect()->to('/mahasiswa-bimbingan/' . $mahasiswaId);
};

?>

<div>
    <x-slot:user>dosen</x-slot:user>

    <flux:breadcrumbs class="mb-5">
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" icon:variant="outline" />
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" class="text-black">Dashboard</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <!-- Statistics Cards -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Mahasiswa Bimbingan Magang</p>
                        <p class="text-xl font-semibold text-green-600 mt-2">{{ $this->totalMahasiswa }} Mahasiswa</p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <flux:icon name="users" class="text-green-600 w-6 h-6" />
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Mahasiswa Selesai Magang</p>
                        <p class="text-xl font-semibold text-blue-600 mt-2">{{ $this->mahasiswaSelesai }} Mahasiswa</p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <flux:icon name="academic-cap" class="text-blue-600 w-6 h-6" />
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Feedback Diberikan</p>
                        <p class="text-xl font-semibold text-purple-600 mt-2">{{ $this->feedbackDiberikan }} Mahasiswa
                        </p>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <flux:icon name="message-circle" class="text-purple-600 w-6 h-6" />
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Status Profil</p>
                        <p class="text-xl font-semibold text-yellow-600 mt-2">{{ $this->statusProfil }}</p>
                    </div>
                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <flux:icon name="user-circle" class="text-yellow-600 w-6 h-6" />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Header and Search -->
    <div class="mb-6">
        <flux:heading size="lg" class="mb-4">Daftar Mahasiswa Bimbingan</flux:heading>

        <div class="flex flex-col md:flex-row gap-4 items-start md:items-center justify-between">
            <div class="flex-1 max-w-md">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari nama, NIM, atau email..."
                    icon="magnifying-glass" />
            </div>

            @if ($search)
                <flux:button wire:click="clearSearch" variant="outline" size="sm">
                    <flux:icon name="x-mark" class="w-4 h-4 mr-1" />
                    Reset
                </flux:button>
            @endif
        </div>

        @if ($search)
            <flux:badge color="blue" class="mt-2">
                Hasil pencarian untuk "{{ $search }}" ({{ $this->mahasiswaBimbingan->total() }} ditemukan)
            </flux:badge>
        @endif
    </div>

    <!-- Table -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        @if ($this->paginatedMahasiswa->count() > 0)
            <div class="overflow-x-auto">
                <table class="table-fixed w-full min-w-[900px]">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th
                                class="text-center px-6 py-3 w-16 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                No</th>
                            <th
                                class="text-left px-6 py-3 w-64 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Nama Mahasiswa</th>
                            <th
                                class="text-left px-6 py-3 w-32 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                NIM</th>
                            <th
                                class="text-left px-6 py-3 w-40 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status Magang</th>
                            <th
                                class="text-left px-6 py-3 w-36 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Feedback</th>
                            <th
                                class="text-left px-6 py-3 w-32 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($this->paginatedMahasiswa as $index => $mahasiswa)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-center text-sm text-gray-900">
                                    {{ ($this->currentPage - 1) * $this->perPage + $loop->iteration }}
                                </td>
                                <td class="px-6 py-4">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">{{ $mahasiswa['nama'] }}</div>
                                        <div class="text-sm text-gray-500">{{ $mahasiswa['email'] }}</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">{{ $mahasiswa['nim'] }}</td>
                                <td class="px-6 py-4">
                                    @php
                                        $statusConfig = match ($mahasiswa['status_magang']) {
                                            'belum magang' => ['bg-gray-100', 'text-gray-800'],
                                            'sedang magang' => ['bg-blue-100', 'text-blue-800'],
                                            'selesai magang' => ['bg-green-100', 'text-green-800'],
                                            default => ['bg-gray-100', 'text-gray-800'],
                                        };
                                    @endphp
                                    <span
                                        class="px-2 py-1 text-xs rounded-full {{ $statusConfig[0] }} {{ $statusConfig[1] }}">
                                        {{ ucwords(str_replace('_', ' ', $mahasiswa['status_magang'])) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        class="px-2 py-1 text-xs rounded-full {{ $mahasiswa['status_feedback'] === 'Sudah diberikan' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                        {{ $mahasiswa['status_feedback'] }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <flux:button wire:click="lihatDetail({{ $mahasiswa['id'] }})" size="sm">
                                        Lihat Detail
                                    </flux:button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="p-8 text-center">
                <flux:icon name="magnifying-glass" class="w-12 h-12 text-gray-400 mx-auto mb-4" />
                <flux:heading size="lg" class="text-gray-600 mb-2">
                    @if ($search)
                        Tidak ada hasil yang ditemukan
                    @else
                        Belum ada mahasiswa bimbingan
                    @endif
                </flux:heading>
                <p class="text-gray-500 mb-4">
                    @if ($search)
                        Coba gunakan kata kunci yang berbeda atau ubah filter pencarian.
                    @else
                        Mahasiswa bimbingan akan muncul di sini setelah ada yang mendaftar.
                    @endif
                </p>
                @if ($search)
                    <flux:button wire:click="clearSearch" variant="outline">
                        Hapus Filter
                    </flux:button>
                @endif
            </div>
        @endif
    </div>

    <!-- Pagination -->
    @if ($this->paginatedMahasiswa->count() > 0)
        <div class="flex flex-col md:flex-row items-center justify-between mt-6 gap-4">
            <div class="text-sm text-gray-600">
                Menampilkan {{ $this->paginatedMahasiswa->firstItem() }} sampai
                {{ $this->paginatedMahasiswa->lastItem() }}
                dari {{ $this->paginatedMahasiswa->total() }} data
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2">
                    <flux:input.group>
                        <flux:select wire:change="updatePerPage($event.target.value)" class="w-20">
                            <option value="10" {{ $perPage == 10 ? 'selected' : '' }}>10</option>
                            <option value="25" {{ $perPage == 25 ? 'selected' : '' }}>25</option>
                            <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50</option>
                            <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100</option>
                        </flux:select>
                    </flux:input.group>
                    <span class="text-sm text-gray-600">per halaman</span>
                </div>

                <div class="flex items-center gap-1">
                    @if ($this->paginatedMahasiswa->onFirstPage())
                        <flux:button disabled size="sm" variant="outline">
                            <flux:icon name="chevron-left" class="w-4 h-4" />
                        </flux:button>
                    @else
                        <flux:button wire:click="goToPage({{ $this->paginatedMahasiswa->currentPage() - 1 }})"
                            size="sm" variant="outline">
                            <flux:icon name="chevron-left" class="w-4 h-4" />
                        </flux:button>
                    @endif

                    @foreach (range(1, $this->paginatedMahasiswa->lastPage()) as $page)
                        @if ($page == $this->paginatedMahasiswa->currentPage())
                            <flux:button size="sm" disabled>{{ $page }}</flux:button>
                        @else
                            <flux:button wire:click="goToPage({{ $page }})" size="sm"
                                variant="outline">{{ $page }}</flux:button>
                        @endif
                    @endforeach

                    @if ($this->paginatedMahasiswa->hasMorePages())
                        <flux:button wire:click="goToPage({{ $this->paginatedMahasiswa->currentPage() + 1 }})"
                            size="sm" variant="outline">
                            <flux:icon name="chevron-right" class="w-4 h-4" />
                        </flux:button>
                    @else
                        <flux:button disabled size="sm" variant="outline">
                            <flux:icon name="chevron-right" class="w-4 h-4" />
                        </flux:button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
