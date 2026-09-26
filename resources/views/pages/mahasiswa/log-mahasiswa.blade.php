<?php
use Illuminate\Support\Facades\Auth;
use App\Models\Mahasiswa;
use App\Models\KontrakMagang;
use App\Models\LowonganMagang;
use App\Models\Perusahaan;
use App\Models\LogMagang;
use function Livewire\Volt\{layout, state, mount, computed};

state([
    'status' => '',
    'mahasiswa' => null,
    'perusahaan' => null,
    'magang' => null,
    'kontrak_magang' => null,
    'logs' => [],
    'currentPage' => 1,
    'perPage' => 10,
    'totalLogs' => 0,
    'debug_info' => [], // untuk debugging
]);

layout('components.layouts.user.main');

mount(function () {
    $this->mahasiswa = Auth::guard('mahasiswa')->user();

    // Debug: Log mahasiswa data
    $this->debug_info['mahasiswa_id'] = $this->mahasiswa->id ?? 'null';
    $this->debug_info['mahasiswa_status'] = $this->mahasiswa->status_magang ?? 'null';

    if ($this->mahasiswa) {
        // Query kontrak magang dengan relasi
        $this->kontrak_magang = KontrakMagang::where('mahasiswa_id', $this->mahasiswa->id)
            ->with(['lowonganMagang.perusahaan', 'lowonganMagang.pekerjaan'])
            ->latest()
            ->first();

        // Debug: Log kontrak magang
        $this->debug_info['kontrak_magang_exists'] = $this->kontrak_magang ? 'yes' : 'no';

        if ($this->kontrak_magang) {
            $this->debug_info['kontrak_magang_id'] = $this->kontrak_magang->id;
            $this->debug_info['magang_id'] = $this->kontrak_magang->lowongan_magang_id ?? 'null';

            // Set magang dan perusahaan
            $this->magang = $this->kontrak_magang->lowonganMagang;
            $this->perusahaan = $this->kontrak_magang->lowonganMagang->perusahaan ?? null;

            // Debug: Log magang dan perusahaan
            $this->debug_info['magang_nama'] = $this->magang->pekerjaan->nama ?? 'null';
            $this->debug_info['perusahaan_nama'] = $this->perusahaan->nama ?? 'null';

            // Load logs data
            $this->loadLogs();
        }

        // Set initial status based on current mahasiswa status
        $status = $this->mahasiswa->status_magang;

        switch ($status) {
            case 'tidak_magang':
            case 'tidak magang':
            case 'belum magang':
                $this->status = 'Belum Magang';
                break;
            case 'sedang_magang':
            case 'sedang magang':
                $this->status = 'Sedang Magang';
                break;
            case 'selesai':
            case 'selesai_magang':
            case 'selesai magang':
                $this->status = 'Selesai Magang';
                break;
            default:
                $this->status = 'Status Tidak Dikenali';
                $this->debug_info['unknown_status'] = $status;
        }

        // Debug: Final status
        $this->debug_info['final_status'] = $this->status;
    }
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

<div class="flex flex-col gap-5">
    <x-slot:user>mahasiswa</x-slot:user>

    @if ($status === 'Belum Magang')
        <x-mahasiswa.log-magang.belum-magang />
    @elseif ($status === 'Sedang Magang')
        <x-mahasiswa.log-magang.sedang-magang :perusahaan="$this->perusahaan" :magang="$this->magang" :kontrak-magang="$this->kontrak_magang" :logs="$this->logs"
            :current-page="$this->currentPage" :per-page="$this->perPage" :total-logs="$this->totalLogs" :total-pages="$this->totalPages" :start-record="$this->startRecord"
            :end-record="$this->endRecord" />
    @elseif ($status === 'Selesai Magang')
        <x-mahasiswa.log-magang.selesai-magang :perusahaan="$this->perusahaan" :magang="$this->magang" :kontrak-magang="$this->kontrak_magang"
            :logs="$this->logs" :current-page="$this->currentPage" :per-page="$this->perPage" :total-logs="$this->totalLogs" :total-pages="$this->totalPages"
            :start-record="$this->startRecord" :end-record="$this->endRecord" />
    @else
        <div class="bg-white shadow-md p-5 rounded-lg mx-auto max-w-2xl">
            <p class="text-gray-700">Status magang Anda tidak dikenali.</p>
            <p class="text-gray-500">Status saat ini: {{ $status }}</p>
            <p class="text-gray-500">Silakan hubungi admin untuk informasi lebih lanjut.</p>
        </div>
    @endif

    @if (session()->has('message'))
        <div class="mt-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl">
            {{ session('message') }}
        </div>
    @endif
</div>
