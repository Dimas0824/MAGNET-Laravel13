<?php

use Illuminate\Support\Facades\Auth;
use App\Models\Mahasiswa;
use App\Models\KontrakMagang;
use App\Models\LogMagang;
use App\Models\UmpanBalikMagang;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use function Livewire\Volt\{layout, state, mount, computed, form};

state(['mahasiswaId', 'perPage' => 10, 'currentPage' => 1, 'showFeedbackModal' => false, 'showSuccessModal' => false]);

state([
    'komentar' => '',
    'tanggal' => '',
]);

layout('components.layouts.user.main');

mount(function ($id) {
    // Pastikan user adalah dosen
    if (!Auth::guard('dosen')->check()) {
        abort(403, 'Unauthorized access');
    }

    $this->mahasiswaId = $id;
    // Set tanggal hari ini sebagai default
    $this->tanggal = Carbon::today()->format('Y-m-d');
});

// Get mahasiswa detail with kontrak magang
$mahasiswaDetail = computed(function () {
    $dosenId = Auth::guard('dosen')->id();

    $data = DB::table('mahasiswa as m')->join('kontrak_magang as km', 'm.id', '=', 'km.mahasiswa_id')->join('lowongan_magang as lm', 'km.lowongan_magang_id', '=', 'lm.id')->join('perusahaan as p', 'lm.perusahaan_id', '=', 'p.id')->join('pekerjaan as pk', 'lm.pekerjaan_id', '=', 'pk.id')->where('m.id', $this->mahasiswaId)->where('km.dosen_id', $dosenId)->select('m.id', 'm.nama', 'm.nim', 'm.program_studi', 'm.foto', 'p.nama as perusahaan', 'pk.nama as posisi', 'km.waktu_awal', 'km.waktu_akhir', 'km.id as kontrak_id')->first();

    if (!$data) {
        abort(404, 'Kontrak magang tidak ditemukan');
    }

    // Calculate duration and remaining days
    $startDate = Carbon::parse($data->waktu_awal);
    $endDate = Carbon::parse($data->waktu_akhir);
    $today = Carbon::today();

    $totalDuration = (int) $startDate->diffInDays($endDate);
    $remainingDays = (int) $today->diffInDays($endDate, false); // false allows negative values

    return [
        'id' => $data->id,
        'nama' => $data->nama,
        'nim' => $data->nim,
        'program_studi' => $data->program_studi ?? '-',
        'perusahaan' => $data->perusahaan ?? '-',
        'posisi' => $data->posisi ?? '-',
        'foto' => $data->foto ? asset('storage/' . $data->foto) : asset('default-profile.png'),
        'waktu_awal' => $startDate->format('j F Y'),
        'waktu_akhir' => $endDate->format('j F Y'),
        'durasi' => "{$totalDuration} hari",
        'sisa_waktu' => $remainingDays > 0 ? "{$remainingDays} hari" : 'Sudah selesai',
        'kontrak_id' => $data->kontrak_id,
    ];
});

// Get log mahasiswa with pagination
$logMahasiswa = computed(function () {
    $mahasiswa = $this->mahasiswaDetail;

    return LogMagang::where('kontrak_magang_id', $mahasiswa['kontrak_id'])
        ->orderBy('created_at', 'desc')
        ->paginate($this->perPage, ['*'], 'page', $this->currentPage);
});

// Get total log count
$totalLogCount = computed(function () {
    $mahasiswa = $this->mahasiswaDetail;

    return LogMagang::where('kontrak_magang_id', $mahasiswa['kontrak_id'])->count();
});

// Get recommendations/feedback for the student
$recommendations = computed(function () {
    $mahasiswa = $this->mahasiswaDetail;

    return UmpanBalikMagang::where('kontrak_magang_id', $mahasiswa['kontrak_id'])->orderBy('created_at', 'desc')->get();
});

// Modal control functions
$openFeedbackModal = function () {
    $this->showFeedbackModal = true;
};

$closeFeedbackModal = function () {
    $this->showFeedbackModal = false;
    // Reset form when closing
    $this->komentar = '';
    $this->tanggal = Carbon::today()->format('Y-m-d');
};

$closeSuccessModal = function () {
    $this->showSuccessModal = false;
};

// Function to save feedback
$saveFeedback = function () {
    $this->validate(
        [
            'komentar' => 'required|string|max:50',
            'tanggal' => 'required|date',
        ],
        [
            'komentar.required' => 'Komentar wajib diisi',
            'komentar.max' => 'Komentar maksimal 50 karakter',
            'tanggal.required' => 'Tanggal wajib diisi',
            'tanggal.date' => 'Format tanggal tidak valid',
        ],
    );

    try {
        $mahasiswa = $this->mahasiswaDetail;

        UmpanBalikMagang::create([
            'kontrak_magang_id' => $mahasiswa['kontrak_id'],
            'komentar' => $this->komentar,
            'tanggal' => $this->tanggal,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Reset form
        $this->komentar = '';
        $this->tanggal = Carbon::today()->format('Y-m-d');

        // Close feedback modal and show success modal
        $this->showFeedbackModal = false;
        $this->showSuccessModal = true;

        // Auto close success modal after 3 seconds
        $this->dispatch('auto-close-success');
    } catch (\Exception $e) {
        session()->flash('error', 'Terjadi kesalahan saat menyimpan umpan balik: ' . $e->getMessage());
    }
};

// Pagination methods
$nextPage = function () {
    $totalPages = ceil($this->totalLogCount / $this->perPage);
    if ($this->currentPage < $totalPages) {
        $this->currentPage++;
    }
};

$prevPage = function () {
    if ($this->currentPage > 1) {
        $this->currentPage--;
    }
};

$goToPage = function ($page) {
    $totalPages = ceil($this->totalLogCount / $this->perPage);
    if ($page >= 1 && $page <= $totalPages) {
        $this->currentPage = $page;
    }
};

$changePerPage = function ($newPerPage) {
    $this->perPage = (int) $newPerPage;
    $this->currentPage = 1; // Reset to first page
};

// Get pagination info
$paginationInfo = computed(function () {
    $total = $this->totalLogCount;
    $perPage = $this->perPage;
    $currentPage = $this->currentPage;

    $totalPages = ceil($total / $perPage);
    $startItem = ($currentPage - 1) * $perPage + 1;
    $endItem = min($currentPage * $perPage, $total);

    return [
        'total' => $total,
        'start' => $total > 0 ? $startItem : 0, // PERBAIKAN: Hindari start = 1 ketika total = 0
        'end' => $endItem,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'per_page' => $perPage,
    ];
});

?>

<div>
    <x-slot:user>dosen</x-slot:user>

    <flux:breadcrumbs class="mb-5">
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" icon:variant="outline" />
        <flux:breadcrumbs.item href="{{ route('dosen.mahasiswa-bimbingan') }}" class="text-black">
            Mahasiswa Bimbingan
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item class="text-black">Detail Mahasiswa Bimbingan</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <h1 class="text-base font-bold leading-6 text-black">Informasi Mahasiswa Magang</h1>

    <!-- Keep error messages for display -->
    @if (session()->has('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            {{ session('error') }}
        </div>
    @endif

    <div class="min-h-screen p-6">
        <div class="px-4 font-sans text-black">
            <div class="flex gap-8 items-start">
                <div class="bg-white p-6 rounded-xl shadow-md w-full">
                    <flux:field class="flex! my-2">
                        <flux:label class="w-1/12">Nama</flux:label>
                        <flux:input value="{{ $this->mahasiswaDetail['nama'] }}" readonly />
                    </flux:field>
                    <flux:field class="flex! my-2">
                        <flux:label class="w-1/12">NIM</flux:label>
                        <flux:input value="{{ $this->mahasiswaDetail['nim'] }}" readonly />
                    </flux:field>
                    <flux:field class="flex! my-2">
                        <flux:label class="w-1/12">Program Studi</flux:label>
                        <flux:input value="{{ $this->mahasiswaDetail['program_studi'] }}" readonly />
                    </flux:field>
                    <flux:field class="flex! my-2">
                        <flux:label class="w-1/12">Perusahaan yang dituju</flux:label>
                        <flux:input value="{{ $this->mahasiswaDetail['perusahaan'] }}" readonly />
                    </flux:field>
                    <flux:field class="flex! my-2">
                        <flux:label class="w-1/12">Posisi</flux:label>
                        <flux:input value="{{ $this->mahasiswaDetail['posisi'] }}" readonly />
                    </flux:field>
                    <flux:field class="flex! my-2">
                        <flux:label class="w-1/12">Mulai Magang</flux:label>
                        <flux:input value="{{ $this->mahasiswaDetail['waktu_awal'] }}" readonly />
                    </flux:field>
                    <flux:field class="flex! my-2">
                        <flux:label class="w-1/12">Akhir Magang</flux:label>
                        <flux:input value="{{ $this->mahasiswaDetail['waktu_akhir'] }}" readonly />
                    </flux:field>
                    <flux:field class="flex! my-2">
                        <flux:label class="w-1/12">Durasi</flux:label>
                        <flux:input value="{{ $this->mahasiswaDetail['durasi'] }}" readonly />
                    </flux:field>
                    <flux:field class="flex! my-2">
                        <flux:label class="w-1/12">Sisa Waktu</flux:label>
                        <flux:input value="{{ $this->mahasiswaDetail['sisa_waktu'] }}" readonly />
                    </flux:field>
                </div>
            </div>
        </div>

        <!-- Modal Trigger Button -->
        <flux:button wire:click="openFeedbackModal" class="bg-magnet-sky-teal! text-white! my-5">
            Berikan Masukan kepada Mahasiswa
        </flux:button>

        <!-- Feedback Modal -->
        <flux:modal wire:model="showFeedbackModal" class="md:w-96">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Berikan Umpan Balik</flux:heading>
                    <flux:text class="mt-2">Berikan masukan kepada {{ $this->mahasiswaDetail['nama'] }} mengenai
                        progress magang.</flux:text>
                </div>

                <form wire:submit="saveFeedback">
                    <div class="space-y-4">
                        <flux:field>
                            <flux:label>Komentar</flux:label>
                            <flux:textarea wire:model="komentar" placeholder="Masukkan komentar (maksimal 50 karakter)"
                                maxlength="50" rows="3" />
                            <flux:error name="komentar" />
                            <div class="text-sm text-gray-500 mt-1">
                                {{ strlen($this->komentar ?? '') }}/50 karakter
                            </div>
                        </flux:field>

                        <flux:field>
                            <flux:label>Tanggal</flux:label>
                            <flux:input type="date" wire:model="tanggal" />
                            <flux:error name="tanggal" />
                        </flux:field>
                    </div>

                    <div class="flex justify-end space-x-3 mt-6">
                        <flux:button wire:click="closeFeedbackModal" variant="ghost">Batal</flux:button>
                        <flux:button type="submit" variant="primary">Simpan Umpan Balik</flux:button>
                    </div>
                </form>
            </div>
        </flux:modal>

        <!-- Success Modal -->
        <flux:modal wire:model="showSuccessModal" class="md:w-96">
            <div class="space-y-6 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100">
                    <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <div>
                    <flux:heading size="lg" class="text-green-600">Berhasil!</flux:heading>
                    <flux:text class="mt-2">Umpan balik berhasil diberikan kepada
                        {{ $this->mahasiswaDetail['nama'] }}</flux:text>
                </div>
                <div class="flex justify-center">
                    <flux:button wire:click="closeSuccessModal" variant="primary"
                        class="bg-green-600 hover:bg-green-700">
                        Tutup
                    </flux:button>
                </div>
            </div>
        </flux:modal>

        <!-- Recommendations Section -->
        <div class="mt-8">
            <h2 class="text-base font-bold leading-6 text-black mb-4">Saran dan Masukan untuk Mahasiswa</h2>

            @if ($this->recommendations->count() > 0)
                <div class="bg-white rounded-xl shadow-md overflow-hidden">
                    <div class="divide-y divide-gray-200">
                        @foreach ($this->recommendations as $recommendation)
                            <div class="p-4 hover:bg-gray-50">
                                <div class="flex items-start space-x-3">
                                    <div class="flex-shrink-0">
                                        <div class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center">
                                            <svg class="h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                                            </svg>
                                        </div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900">
                                            {{ $recommendation->komentar }}
                                        </p>
                                        <p class="text-xs text-gray-500 mt-1">
                                            Diberikan pada
                                            {{ Carbon::parse($recommendation->tanggal)->format('j F Y') }}
                                            • {{ $recommendation->created_at->format('H:i') }} WIB
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="bg-white rounded-xl shadow-md p-8 text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-gray-100">
                        <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                        </svg>
                    </div>
                    <flux:text class="mt-2 text-gray-500">Belum ada saran atau masukan yang diberikan</flux:text>
                </div>
            @endif
        </div>

        <div>
            <h1 class="text-base font-bold leading-6 text-black my-4">Log Mahasiswa</h1>
        </div>

        <div class="overflow-y-auto flex flex-col items-center mt-4 rounded-lg shadow bg-white">
            <table class="table-auto w-full">
                <thead class="bg-white text-black">
                    <tr class="border-b">
                        <th class="text-center px-6 py-3">No</th>
                        <th class="text-left px-6 py-3">Aktivitas</th>
                        <th class="text-left px-6 py-3">Tanggal</th>
                        <th class="text-left px-6 py-3">Waktu</th>
                        <th class="text-center px-6 py-3">Keterangan</th>
                    </tr>
                </thead>
                <tbody class="bg-white text-black">
                    @forelse ($this->logMahasiswa as $index => $log)
                        <tr class="border-b hover:bg-gray-50">
                            <td class="px-6 py-3 text-center">
                                {{ ($this->paginationInfo['current_page'] - 1) * $this->paginationInfo['per_page'] + $index + 1 }}
                            </td>
                            <td class="px-6 py-3">{{ $log->aktivitas ?? '-' }}</td>
                            <td class="px-6 py-3">{{ $log->created_at->format('j M Y') }}</td>
                            <td class="px-6 py-3">{{ $log->created_at->format('H.i') }} WIB</td>
                            <td class="px-6 py-3">{{ $log->keterangan ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-3 text-center text-gray-500">
                                Belum ada log aktivitas
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex items-center justify-between w-full p-3">
            <div class="flex items-center justify-between w-full p-3">
                <div class="text-black mt-6">
                    <p>Menampilkan {{ $this->paginationInfo['start'] }} - {{ $this->paginationInfo['end'] }} dari
                        {{ $this->paginationInfo['total'] }} data</p>
                </div>

                <div class="flex mt-6" id="pagination-controls">
                    <button
                        class="px-3 py-2 text-sm bg-gray-200 text-gray-700 rounded hover:bg-gray-300 disabled:opacity-50 disabled:cursor-not-allowed"
                        onclick="goToPrevPage()" {{ $this->paginationInfo['current_page'] <= 1 ? 'disabled' : '' }}>
                        ←
                    </button>

                    @for ($i = 1; $i <= min(5, $this->paginationInfo['total_pages']); $i++)
                        <button
                            class="px-3 py-2 text-sm rounded {{ $this->paginationInfo['current_page'] == $i ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}"
                            onclick="goToPage({{ $i }})">
                            {{ $i }}
                        </button>
                    @endfor

                    <button
                        class="px-3 py-2 text-sm bg-gray-200 text-gray-700 rounded hover:bg-gray-300 disabled:opacity-50 disabled:cursor-not-allowed"
                        onclick="goToNextPage()"
                        {{ $this->paginationInfo['current_page'] >= $this->paginationInfo['total_pages'] ? 'disabled' : '' }}>
                        →
                    </button>
                </div>

                <div class="flex gap-3 items-center text-black mt-6">
                    <p>Baris per halaman</p>
                    <select onchange="changePerPage(this.value)" class="w-20 px-2 py-1 border rounded">
                        <option value="10" {{ $this->perPage == 10 ? 'selected' : '' }}>10</option>
                        <option value="25" {{ $this->perPage == 25 ? 'selected' : '' }}>25</option>
                        <option value="50" {{ $this->perPage == 50 ? 'selected' : '' }}>50</option>
                        <option value="100" {{ $this->perPage == 100 ? 'selected' : '' }}>100</option>
                    </select>
                </div>
            </div>

            <script>
                function goToPage(page) {
                    @this.call('goToPage', page);
                }

                function goToPrevPage() {
                    @this.call('prevPage');
                }

                function goToNextPage() {
                    @this.call('nextPage');
                }

                function changePerPage(perPage) {
                    @this.call('changePerPage', perPage);
                }

                // Auto close success modal after 3 seconds
                document.addEventListener('livewire:init', () => {
                    Livewire.on('auto-close-success', () => {
                        setTimeout(() => {
                            @this.call('closeSuccessModal');
                        }, 3000);
                    });
                });
            </script>
        </div>
    </div>
</div>
