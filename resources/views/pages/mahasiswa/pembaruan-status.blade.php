<?php
use Illuminate\Support\Facades\Auth;
use function Livewire\Volt\{layout, state, mount};
use App\Models\{FormPengajuanMagang, KontrakMagang};

state([
    'status' => '',
    'mahasiswa' => null,
    'isStatusLocked' => false,
    'availableStatuses' => [],
    'statusMessage' => '',
    'hasApprovedApplication' => false, // Track approved applications
    'hasPendingContract' => false, // New state to track pending contract
    'pendingContractInfo' => null, // Store pending contract information
]);

layout('components.layouts.user.main');

mount(function () {
    $this->mahasiswa = Auth::guard('mahasiswa')->user();

    if ($this->mahasiswa) {
        $currentStatus = $this->mahasiswa->status_magang;

        // Check if mahasiswa has approved application for "Sedang Magang" transition
        $this->checkApprovedApplication();

        // Check if mahasiswa has pending contract
        $this->checkPendingContract();

        $this->setCurrentStatusAndOptions($currentStatus);
    }
});

// Check for approved applications
$checkApprovedApplication = function () {
    if (!$this->mahasiswa) {
        return;
    }

    // Check if there's an approved form pengajuan for this student
    $approvedApplication = FormPengajuanMagang::whereHas('berkasPengajuanMagang', function ($query) {
        $query->where('mahasiswa_id', $this->mahasiswa->id);
    })
        ->where('status', 'diterima')
        ->exists();

    $this->hasApprovedApplication = $approvedApplication;
};

// New method to check for pending contract
$checkPendingContract = function () {
    if (!$this->mahasiswa) {
        return;
    }

    // Check if there's a pending contract for this student
    $pendingContract = KontrakMagang::where('mahasiswa_id', $this->mahasiswa->id)
        ->where('status', 'menunggu_persetujuan')
        ->with(['lowonganMagang.perusahaan', 'dosenPembimbing']) // Load related data through lowonganMagang
        ->first();

    if ($pendingContract) {
        $this->hasPendingContract = true;
        $this->pendingContractInfo = [
            'id' => $pendingContract->id,
            'perusahaan_nama' => $pendingContract->lowonganMagang->perusahaan->nama ?? 'Tidak tersedia',
            'pembimbing_nama' => $pendingContract->dosenPembimbing->nama ?? 'Tidak tersedia',
            'tanggal_mulai' => $pendingContract->waktu_awal,
            'tanggal_selesai' => $pendingContract->waktu_akhir,
            'created_at' => $pendingContract->created_at,
        ];
    } else {
        $this->hasPendingContract = false;
        $this->pendingContractInfo = null;
    }
};

$setCurrentStatusAndOptions = function ($currentStatus) {
    switch ($currentStatus) {
        case 'belum_magang':
        case 'belum magang':
            $this->status = 'Belum Magang';
            $this->isStatusLocked = false;

            // Check if there's a pending contract
            if ($this->hasPendingContract) {
                // If there's a pending contract, don't allow status change
                $this->availableStatuses = [
                    'Belum Magang' => 'Belum Magang',
                ];
                $this->statusMessage = 'Anda memiliki kontrak magang yang sedang menunggu persetujuan. Status tidak dapat diubah hingga kontrak disetujui atau ditolak.';
            } elseif ($this->hasApprovedApplication) {
                // Only allow transition to "Sedang Magang" if application is approved and no pending contract
                $this->availableStatuses = [
                    'Belum Magang' => 'Belum Magang',
                    'Sedang Magang' => 'Sedang Magang',
                ];
                $this->statusMessage = 'Pengajuan magang Anda telah disetujui. Anda dapat memperbarui status ke "Sedang Magang".';
            } else {
                $this->availableStatuses = [
                    'Belum Magang' => 'Belum Magang',
                ];
                $this->statusMessage = 'Anda perlu mengajukan dan mendapat persetujuan admin terlebih dahulu sebelum dapat mengubah status ke "Sedang Magang".';
            }
            break;

        case 'sedang_magang':
        case 'sedang magang':
            $this->status = 'Sedang Magang';
            $this->isStatusLocked = false;
            $this->availableStatuses = [
                'Sedang Magang' => 'Sedang Magang',
                'Selesai Magang' => 'Selesai Magang',
            ];
            $this->statusMessage = 'Anda hanya dapat memperbarui status ke "Selesai Magang" jika kontrak magang telah berakhir.';
            break;

        case 'selesai':
        case 'selesai_magang':
        case 'selesai magang':
            $this->status = 'Selesai Magang';
            $this->isStatusLocked = true;
            $this->availableStatuses = [
                'Selesai Magang' => 'Selesai Magang',
            ];
            $this->statusMessage = 'Status magang telah selesai dan tidak dapat diubah lagi.';
            break;

        default:
            $this->status = '';
            $this->isStatusLocked = false;
            $this->availableStatuses = [
                'Belum Magang' => 'Belum Magang',
            ];
            $this->statusMessage = 'Silakan ajukan permohonan magang terlebih dahulu.';
    }
};

$updateStatus = function () {
    if (!$this->status) {
        session()->flash('error', 'Silakan pilih status magang terlebih dahulu.');
        return;
    }

    // Validate status transition
    $currentDbStatus = $this->mahasiswa->status_magang;
    $newStatus = $this->convertDisplayStatusToDb($this->status);

    if (!$this->isValidStatusTransition($currentDbStatus, $newStatus)) {
        session()->flash('error', 'Perubahan status tidak diizinkan. Silakan ikuti alur status yang benar.');
        return;
    }

    // Additional validation for "Sedang Magang" transition
    if ($newStatus === 'sedang_magang') {
        if (!$this->hasApprovedApplication) {
            session()->flash('error', 'Anda harus mendapat persetujuan admin terlebih dahulu sebelum dapat mengubah status ke "Sedang Magang".');
            return;
        }

        if ($this->hasPendingContract) {
            session()->flash('error', 'Tidak dapat mengubah status ke "Sedang Magang" karena masih ada kontrak yang menunggu persetujuan.');
            return;
        }
    }

    // Update the status
    try {
        $this->mahasiswa->update(['status_magang' => $newStatus]);
        $this->mahasiswa->refresh();

        // Refresh the options after status change
        $this->setCurrentStatusAndOptions($newStatus);

        session()->flash('success', 'Status magang berhasil diperbarui ke: ' . $this->status);
    } catch (\Exception $e) {
        session()->flash('error', 'Terjadi kesalahan saat memperbarui status. Silakan coba lagi.');
        \Log::error('Error updating mahasiswa status: ' . $e->getMessage());
    }
};

$convertDisplayStatusToDb = function ($displayStatus) {
    switch ($displayStatus) {
        case 'Belum Magang':
            return 'belum_magang';
        case 'Sedang Magang':
            return 'sedang_magang';
        case 'Selesai Magang':
            return 'selesai_magang';
        default:
            return '';
    }
};

$isValidStatusTransition = function ($currentStatus, $newStatus) {
    $validTransitions = [
        'belum_magang' => ['belum_magang', 'sedang_magang'],
        'belum magang' => ['belum_magang', 'sedang_magang'],
        'sedang_magang' => ['sedang_magang', 'selesai_magang'],
        'sedang magang' => ['sedang_magang', 'selesai_magang'],
        'selesai_magang' => ['selesai_magang'],
        'selesai magang' => ['selesai_magang'],
        'selesai' => ['selesai_magang'],
    ];

    return isset($validTransitions[$currentStatus]) && in_array($newStatus, $validTransitions[$currentStatus]);
};

// Listen for events from child components
$refreshParent = function () {
    if ($this->mahasiswa) {
        $this->mahasiswa->refresh();
        $this->checkApprovedApplication(); // Refresh approval status
        $this->checkPendingContract(); // Refresh contract status
        $currentStatus = $this->mahasiswa->status_magang;
        $this->setCurrentStatusAndOptions($currentStatus);
    }
};

// Method to check if user can access certain features
$canAccessCompanyFeatures = function () {
    return $this->mahasiswa && in_array($this->mahasiswa->status_magang, ['belum_magang', 'belum magang']) && !$this->hasPendingContract;
};
?>

<div>
    <x-slot:user>mahasiswa</x-slot:user>

    <div class="bg-magnet-frost-mist min-h-screen flex flex-col gap-5">
        <div class="bg-white p-6 rounded-lg shadow-sm">
            <h2 class="text-xl font-bold text-gray-800 mb-2">Pembaruan Status Magang</h2>
            <p class="text-gray-600 text-sm leading-relaxed">
                Kelola status magang Anda dengan mudah. Pastikan untuk memperbarui status sesuai dengan
                perkembangan magang Anda saat ini.
            </p>
        </div>

        <div class="w-full bg-white p-6 rounded-lg shadow-md">
            @if (session()->has('success'))
                <div class="p-4 mb-6 text-sm text-green-800 rounded-lg bg-green-50 border border-green-200">
                    <div class="flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd"></path>
                        </svg>
                        {{ session('success') }}
                    </div>
                </div>
            @endif

            @if (session()->has('error'))
                <div class="p-4 mb-6 text-sm text-red-800 rounded-lg bg-red-50 border border-red-200">
                    <div class="flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                                clip-rule="evenodd"></path>
                        </svg>
                        {{ session('error') }}
                    </div>
                </div>
            @endif

            @if ($mahasiswa)
                <div class="p-4 mb-6 text-sm text-blue-800 rounded-lg bg-blue-50 border border-blue-200">
                    <div class="flex items-start">
                        <svg class="w-4 h-4 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                clip-rule="evenodd"></path>
                        </svg>
                        <div>
                            <div class="font-medium mb-1">Informasi Mahasiswa</div>
                            <div><strong>Nama:</strong> {{ $mahasiswa->nama }}</div>
                            <div><strong>NIM:</strong> {{ $mahasiswa->nim }}</div>
                            <div><strong>Status Saat Ini:</strong>
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if (in_array($mahasiswa->status_magang, ['belum_magang', 'belum magang'])) bg-yellow-100 text-yellow-800
                                    @elseif(in_array($mahasiswa->status_magang, ['sedang_magang', 'sedang magang']))
                                        bg-blue-100 text-blue-800
                                    @else
                                        bg-green-100 text-green-800 @endif
                                ">
                                    {{ ucwords(str_replace('_', ' ', $mahasiswa->status_magang)) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Pending Contract Information Card -->
            @if ($hasPendingContract && $pendingContractInfo)
                <div class="p-4 mb-6 text-sm text-orange-800 rounded-lg bg-orange-50 border border-orange-200">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 mr-2 mt-0.5 text-orange-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                clip-rule="evenodd"></path>
                        </svg>
                        <div class="flex-1">
                            <div class="font-medium mb-2 text-orange-900">
                                <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"
                                        clip-rule="evenodd"></path>
                                </svg>
                                Kontrak Magang Menunggu Persetujuan
                            </div>
                            <div class="space-y-2">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <div class="font-medium text-orange-900">Perusahaan:</div>
                                        <div class="text-orange-800">{{ $pendingContractInfo['perusahaan_nama'] }}</div>
                                    </div>
                                    <div>
                                        <div class="font-medium text-orange-900">Pembimbing Lapangan:</div>
                                        <div class="text-orange-800">{{ $pendingContractInfo['pembimbing_nama'] }}</div>
                                    </div>
                                    <div>
                                        <div class="font-medium text-orange-900">Periode Magang:</div>
                                        <div class="text-orange-800">
                                            {{ \Carbon\Carbon::parse($pendingContractInfo['tanggal_mulai'])->format('d M Y') }}
                                            -
                                            {{ \Carbon\Carbon::parse($pendingContractInfo['tanggal_selesai'])->format('d M Y') }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="font-medium text-orange-900">Tanggal Pengajuan:</div>
                                        <div class="text-orange-800">
                                            {{ \Carbon\Carbon::parse($pendingContractInfo['created_at'])->format('d M Y H:i') }}
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3 p-3 bg-orange-100 rounded-md">
                                    <div class="text-orange-900 font-medium text-sm">
                                        <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                                clip-rule="evenodd"></path>
                                        </svg>
                                        Pemberitahuan:
                                    </div>
                                    <div class="text-orange-800 text-sm mt-1">
                                        Kontrak magang Anda sedang dalam proses review oleh admin. Status magang tidak
                                        dapat diubah hingga kontrak disetujui atau ditolak. Silakan tunggu konfirmasi
                                        lebih lanjut.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Status Information Card -->
            <div
                class="p-4 mb-6 rounded-lg border-l-4
                @if ($status == 'Belum Magang') @if ($hasPendingContract)
                        bg-orange-50 border-orange-400
                    @else
                        bg-yellow-50 border-yellow-400 @endif
@elseif($status == 'Sedang Magang')
bg-blue-50 border-blue-400
@else
bg-green-50 border-green-400 @endif
            ">
                <div class="flex items-center mb-2">
                    <div
                        class="w-3 h-3 rounded-full mr-2
                        @if ($status == 'Belum Magang') @if ($hasPendingContract)
                                bg-orange-400
                            @else
                                bg-yellow-400 @endif
@elseif($status == 'Sedang Magang')
bg-blue-400
@else
bg-green-400 @endif
                    ">
                    </div>
                    <h4 class="font-medium text-gray-800">Status Progress</h4>
                </div>
                <p class="text-sm text-gray-600">{{ $statusMessage }}</p>
            </div>

            <div class="space-y-4">
                <flux:field>
                    <flux:label class="text-sm font-medium text-gray-700">
                        Pilih Status Magang
                    </flux:label>

                    <flux:select wire:model.live="status" placeholder="Pilih status magang..."
                        :disabled="$isStatusLocked || $hasPendingContract" class="mt-1">
                        @foreach ($availableStatuses as $value => $label)
                            <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    @if ($isStatusLocked)
                        <flux:description class="text-gray-500 text-sm mt-2">
                            <div class="flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"
                                        clip-rule="evenodd"></path>
                                </svg>
                                Status tidak dapat diubah karena magang telah selesai.
                            </div>
                        </flux:description>
                    @elseif($hasPendingContract)
                        <flux:description class="text-orange-600 text-sm mt-2">
                            <div class="flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                        clip-rule="evenodd"></path>
                                </svg>
                                Status tidak dapat diubah karena ada kontrak magang yang menunggu persetujuan.
                            </div>
                        </flux:description>
                    @elseif($status == 'Sedang Magang')
                        <flux:description class="text-amber-600 text-sm mt-2">
                            <div class="flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                        clip-rule="evenodd"></path>
                                </svg>
                                Selama status "Sedang Magang", Anda tidak dapat mengubah data perusahaan atau kembali ke
                                status sebelumnya.
                            </div>
                        </flux:description>
                    @endif

                    <flux:error name="status" />
                </flux:field>
            </div>

            <!-- Conditional Components based on status -->
            @if ($status == 'Sedang Magang')
                <div class="mt-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                    <h5 class="font-medium text-blue-800 mb-2">Mode Magang Aktif</h5>
                    <p class="text-sm text-blue-700 mb-4">
                        Anda sedang dalam status magang aktif. Fitur perubahan data perusahaan telah dinonaktifkan untuk
                        menjaga konsistensi data.
                    </p>
                    <livewire:components.mahasiswa.pembaruan-status-magang.sedang-magang />
                </div>
            @elseif ($status == 'Selesai Magang')
                <div class="mt-6">
                    <livewire:components.mahasiswa.pembaruan-status-magang.selesai-magang />
                </div>
            @elseif ($status == 'Belum Magang' && !$hasPendingContract)
                <div class="mt-6 p-4 bg-green-50 rounded-lg border border-green-200">
                    <h5 class="font-medium text-green-800 mb-2">Siap Memulai Magang</h5>
                    <p class="text-sm text-green-700">
                        Anda dapat memperbarui data perusahaan dan mengubah status ke "Sedang Magang" ketika telah
                        diterima di perusahaan.
                    </p>
                </div>
            @endif
        </div>
    </div>
</div>
