<?php

use Flux\Flux;
use function Livewire\Volt\{layout, state, mount, computed};
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\KontrakMagang;
use App\Models\Mahasiswa;
use App\Models\DosenPembimbing;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

layout('components.layouts.user.main');

state([
    'kontrakMagang' => null,
    'mahasiswa' => null,
    'dosen_selected' => null,
    'dosenList' => [],
    'admin_keterangan' => '',
    'rejection_reason' => '',
    'isProcessing' => false,
]);

mount(function () {
    $mahasiswa_id = (int) request()->route('id');

    $this->kontrakMagang = KontrakMagang::with(['mahasiswa', 'lowonganMagang.perusahaan', 'lowonganMagang.pekerjaan', 'dosenPembimbing'])
        ->where('mahasiswa_id', $mahasiswa_id)
        ->latest()
        ->first();

    if ($this->kontrakMagang) {
        $this->mahasiswa = $this->kontrakMagang->mahasiswa;
        $this->dosen_selected = $this->kontrakMagang->dosen_id;
    }

    // Resolve the dosen options once at mount so the render does not issue a
    // read query of its own.
    $this->dosenList = DosenPembimbing::select('id', 'nama', 'nidn')
        ->whereNotNull('nama')
        ->orderBy('nama', 'asc')
        ->get()
        ->all();
});

$canApprove = computed(function () {
    return $this->kontrakMagang && $this->kontrakMagang->status === 'menunggu_persetujuan' && $this->dosen_selected && !$this->isProcessing;
});

$canReject = computed(function () {
    return $this->kontrakMagang && $this->kontrakMagang->status === 'menunggu_persetujuan' && !$this->isProcessing;
});

$approveContract = function () {
    // Debug: Log status awal
    \Log::info('Approve Contract - Status awal:', [
        'contract_id' => $this->kontrakMagang->id,
        'current_status' => $this->kontrakMagang->status,
        'dosen_selected' => $this->dosen_selected,
    ]);

    // Validasi awal
    if (!$this->canApprove) {
        session()->flash('error', 'Tidak dapat menyetujui kontrak ini.');
        return;
    }

    if (!$this->dosen_selected) {
        session()->flash('error', 'Dosen pembimbing harus dipilih.');
        return;
    }

    // Validasi apakah dosen pembimbing ada
    $dosenExists = DosenPembimbing::find($this->dosen_selected);
    if (!$dosenExists) {
        session()->flash('error', 'Dosen pembimbing yang dipilih tidak valid.');
        return;
    }

    $this->isProcessing = true;

    try {
        $adminName = auth()->user()->name ?? 'Admin';
        $defaultKeterangan = "Kontrak disetujui oleh {$adminName} pada " . now()->format('d M Y H:i');
        $finalKeterangan = !empty(trim($this->admin_keterangan)) ? $this->admin_keterangan : $defaultKeterangan;

        // Debug: Log sebelum update
        \Log::info('Approve Contract - Sebelum update:', [
            'contract_id' => $this->kontrakMagang->id,
            'new_status' => 'disetujui',
            'dosen_id' => $this->dosen_selected,
            'keterangan' => $finalKeterangan,
        ]);

        DB::transaction(function () use ($finalKeterangan) {
            // Update menggunakan query builder untuk memastikan
            $updated = DB::table('kontrak_magang')
                ->where('id', $this->kontrakMagang->id)
                ->update([
                    'status' => 'disetujui',
                    'dosen_id' => $this->dosen_selected,
                    'keterangan' => $finalKeterangan,
                    'updated_at' => now(),
                ]);

            \Log::info('Approve Contract - Hasil update kontrak:', [
                'contract_id' => $this->kontrakMagang->id,
                'rows_affected' => $updated,
            ]);

            // Update status mahasiswa
            $mahasiswaUpdated = DB::table('mahasiswa')
                ->where('id', $this->mahasiswa->id)
                ->update([
                    'status_magang' => 'sedang magang',
                    'updated_at' => now(),
                ]);

            \Log::info('Approve Contract - Hasil update mahasiswa:', [
                'mahasiswa_id' => $this->mahasiswa->id,
                'rows_affected' => $mahasiswaUpdated,
            ]);
        });

        // Refresh data setelah update dengan force reload dari database
        $this->kontrakMagang = KontrakMagang::with(['mahasiswa', 'lowonganMagang.perusahaan', 'lowonganMagang.pekerjaan', 'dosenPembimbing'])->find($this->kontrakMagang->id);
        $this->mahasiswa = $this->kontrakMagang->mahasiswa;

        // Debug: Log setelah refresh
        \Log::info('Approve Contract - Setelah refresh:', [
            'contract_id' => $this->kontrakMagang->id,
            'final_status' => $this->kontrakMagang->status,
            'mahasiswa_status' => $this->mahasiswa->status_magang,
        ]);

        // Reset form
        $this->admin_keterangan = '';

        session()->flash('success', 'Kontrak magang berhasil disetujui dan status telah diperbarui.');
        Flux::modal('success-modal')->show();

        // Force re-render component
        $this->dispatch('$refresh');
    } catch (\Exception $e) {
        \Log::error('Error approving contract: ' . $e->getMessage(), [
            'contract_id' => $this->kontrakMagang->id,
            'dosen_id' => $this->dosen_selected,
            'trace' => $e->getTraceAsString(),
        ]);

        session()->flash('error', 'Terjadi kesalahan saat menyetujui kontrak: ' . $e->getMessage());
    } finally {
        $this->isProcessing = false;
        Flux::modal('approve-modal')->close();
    }
};

$rejectContract = function () {
    // Debug: Log status awal
    \Log::info('Reject Contract - Status awal:', [
        'contract_id' => $this->kontrakMagang->id,
        'current_status' => $this->kontrakMagang->status,
        'rejection_reason' => $this->rejection_reason,
    ]);

    // Validasi awal
    if (!$this->canReject) {
        session()->flash('error', 'Tidak dapat menolak kontrak ini.');
        return;
    }

    if (empty(trim($this->rejection_reason))) {
        session()->flash('error', 'Alasan penolakan harus diisi.');
        return;
    }

    $this->isProcessing = true;

    try {
        $adminName = auth()->user()->name ?? 'Admin';
        $finalRejectionReason = "Ditolak oleh {$adminName} pada " . now()->format('d M Y H:i') . '. Alasan: ' . $this->rejection_reason;

        DB::transaction(function () use ($finalRejectionReason) {
            // Update menggunakan query builder untuk memastikan
            $updated = DB::table('kontrak_magang')
                ->where('id', $this->kontrakMagang->id)
                ->update([
                    'status' => 'ditolak',
                    'keterangan' => $finalRejectionReason,
                    'updated_at' => now(),
                ]);

            \Log::info('Reject Contract - Hasil update kontrak:', [
                'contract_id' => $this->kontrakMagang->id,
                'rows_affected' => $updated,
            ]);

            // Update status mahasiswa kembali ke 'belum_magang'
            $mahasiswaUpdated = DB::table('mahasiswa')
                ->where('id', $this->mahasiswa->id)
                ->update([
                    'status_magang' => 'belum magang',
                    'updated_at' => now(),
                ]);

            \Log::info('Reject Contract - Hasil update mahasiswa:', [
                'mahasiswa_id' => $this->mahasiswa->id,
                'rows_affected' => $mahasiswaUpdated,
            ]);
        });

        // Refresh data setelah update dengan force reload dari database
        $this->kontrakMagang = KontrakMagang::with(['mahasiswa', 'lowonganMagang.perusahaan', 'lowonganMagang.pekerjaan', 'dosenPembimbing'])->find($this->kontrakMagang->id);
        $this->mahasiswa = $this->kontrakMagang->mahasiswa;

        // Debug: Log setelah refresh
        \Log::info('Reject Contract - Setelah refresh:', [
            'contract_id' => $this->kontrakMagang->id,
            'final_status' => $this->kontrakMagang->status,
            'mahasiswa_status' => $this->mahasiswa->status_magang,
        ]);

        // Reset form
        $this->rejection_reason = '';

        session()->flash('success', 'Kontrak magang berhasil ditolak dan status telah diperbarui.');
        Flux::modal('success-modal')->show();

        // Force re-render component
        $this->dispatch('$refresh');
    } catch (\Exception $e) {
        \Log::error('Error rejecting contract: ' . $e->getMessage(), [
            'contract_id' => $this->kontrakMagang->id,
            'rejection_reason' => $this->rejection_reason,
            'trace' => $e->getTraceAsString(),
        ]);

        session()->flash('error', 'Terjadi kesalahan saat menolak kontrak: ' . $e->getMessage());
    } finally {
        $this->isProcessing = false;
        Flux::modal('reject-modal')->close();
    }
};

$showApprovalModal = function () {
    if (!$this->dosen_selected) {
        session()->flash('error', 'Pilih dosen pembimbing terlebih dahulu.');
        return;
    }
    Flux::modal('approve-modal')->show();
};

$showRejectionModal = function () {
    Flux::modal('reject-modal')->show();
};

?>

<div class="flex flex-col gap-5">
    <x-slot:user>admin</x-slot:user>

    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" icon:variant="outline" />
        <flux:breadcrumbs.item class="text-black">Detail Kontrak Magang</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="flex justify-between items-center">
        <h1 class="text-xl font-bold leading-6 text-black">Detail Kontrak Magang</h1>
        <div class="flex gap-2">
            @if ($this->kontrakMagang)
                @php
                    $badgeColor = match ($this->kontrakMagang->status) {
                        'disetujui' => 'green',
                        'menunggu_persetujuan' => 'yellow',
                        'ditolak' => 'red',
                        default => 'gray',
                    };
                    $statusText = match ($this->kontrakMagang->status) {
                        'disetujui' => 'Kontrak Disetujui',
                        'menunggu_persetujuan' => 'Menunggu Persetujuan',
                        'ditolak' => 'Kontrak Ditolak',
                        default => 'Status Tidak Dikenal',
                    };
                @endphp

                <flux:badge variant="solid" color="{{ $badgeColor }}" size="lg">
                    {{ $statusText }}
                </flux:badge>
            @endif
        </div>
    </div>

    @if (session()->has('success'))
        <div class="p-4 text-sm text-green-800 rounded-lg bg-green-50 border border-green-200">
            {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="p-4 text-sm text-red-800 rounded-lg bg-red-50 border border-red-200">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column - Main Info -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Mahasiswa Data -->
            @if ($mahasiswa)
                <div class="bg-white p-6 rounded-xl shadow border border-gray-200">
                    <h2 class="text-lg font-semibold text-black mb-4 flex items-center gap-2">
                        <flux:icon.user class="w-5 h-5" />
                        Data Mahasiswa
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-3">
                            <div>
                                <label class="text-sm font-medium text-gray-500">Nama Lengkap</label>
                                <p class="text-gray-900">{{ $mahasiswa->nama ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">NIM</label>
                                <p class="text-gray-900">{{ $mahasiswa->nim ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Email</label>
                                <p class="text-gray-900">{{ $mahasiswa->email ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Jenis Kelamin</label>
                                <p class="text-gray-900">
                                    {{ $mahasiswa->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan' }}
                                </p>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <div>
                                <label class="text-sm font-medium text-gray-500">Jurusan</label>
                                <p class="text-gray-900">{{ $mahasiswa->jurusan ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Program Studi</label>
                                <p class="text-gray-900">{{ $mahasiswa->program_studi ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Angkatan</label>
                                <p class="text-gray-900">{{ $mahasiswa->angkatan ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Status Magang</label>
                                <p class="text-gray-900">
                                    {{ ucwords(str_replace('_', ' ', $mahasiswa->status_magang ?? 'N/A')) }}</p>
                            </div>
                        </div>
                        @if ($mahasiswa->alamat)
                            <div class="md:col-span-2">
                                <label class="text-sm font-medium text-gray-500">Alamat</label>
                                <p class="text-gray-900">{{ $mahasiswa->alamat }}</p>
                            </div>
                        @endif
                        @if ($mahasiswa->tanggal_lahir)
                            <div class="md:col-span-2">
                                <label class="text-sm font-medium text-gray-500">Tanggal Lahir</label>
                                <p class="text-gray-900">
                                    {{ Carbon::parse($mahasiswa->tanggal_lahir)->format('d M Y') }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Contract Info -->
            @if ($kontrakMagang)
                <div class="bg-white p-6 rounded-xl shadow border border-gray-200">
                    <h2 class="text-lg font-semibold text-black mb-4 flex items-center gap-2">
                        <flux:icon.document-text class="w-5 h-5" />
                        Informasi Kontrak Magang
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-medium text-gray-500">Perusahaan</label>
                            <p class="text-gray-900 font-medium">
                                {{ $kontrakMagang->lowonganMagang->perusahaan->nama ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Posisi</label>
                            <p class="text-gray-900 font-medium">
                                {{ $kontrakMagang->lowonganMagang->pekerjaan->nama ?? 'N/A' }}</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Waktu Mulai</label>
                            <p class="text-gray-900">
                                {{ $kontrakMagang->waktu_awal ? Carbon::parse($kontrakMagang->waktu_awal)->format('d M Y') : 'N/A' }}
                            </p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Waktu Selesai</label>
                            <p class="text-gray-900">
                                {{ $kontrakMagang->waktu_akhir ? Carbon::parse($kontrakMagang->waktu_akhir)->format('d M Y') : 'N/A' }}
                            </p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Status Kontrak</label>
                            <p class="text-gray-900 font-medium">
                                {{ ucwords(str_replace('_', ' ', $kontrakMagang->status)) }}</p>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-gray-500">Dosen Pembimbing</label>
                            <p class="text-gray-900 font-medium">
                                {{ $kontrakMagang->dosenPembimbing->nama ?? 'Belum Ditentukan' }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Supervisor Information -->
            @if ($kontrakMagang && $kontrakMagang->dosenPembimbing)
                <div class="bg-white p-6 rounded-xl shadow border border-gray-200">
                    <h2 class="text-lg font-semibold text-black mb-4 flex items-center gap-2">
                        <flux:icon.academic-cap class="w-5 h-5" />
                        Informasi Dosen Pembimbing
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-medium text-gray-500">Nama Dosen</label>
                            <p class="text-gray-900 font-medium">{{ $kontrakMagang->dosenPembimbing->nama }}</p>
                        </div>
                        @if ($kontrakMagang->dosenPembimbing->nidn)
                            <div>
                                <label class="text-sm font-medium text-gray-500">NIDN</label>
                                <p class="text-gray-900">{{ $kontrakMagang->dosenPembimbing->nidn }}</p>
                            </div>
                        @endif
                        @if ($kontrakMagang->dosenPembimbing->email)
                            <div>
                                <label class="text-sm font-medium text-gray-500">Email</label>
                                <p class="text-gray-900">{{ $kontrakMagang->dosenPembimbing->email }}</p>
                            </div>
                        @endif
                        @if ($kontrakMagang->dosenPembimbing->telepon)
                            <div>
                                <label class="text-sm font-medium text-gray-500">Telepon</label>
                                <p class="text-gray-900">{{ $kontrakMagang->dosenPembimbing->telepon }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <!-- Right Column - Actions & Status -->
        <div class="space-y-6">
            <!-- Contract Approval (only for pending status) -->
            @if ($kontrakMagang && $kontrakMagang->status === 'menunggu_persetujuan')
                <div class="bg-white p-6 rounded-xl shadow border border-gray-200">
                    <h2 class="text-lg font-semibold text-black mb-4 flex items-center gap-2">
                        <flux:icon.document-check class="w-5 h-5" />
                        Persetujuan Kontrak Magang
                    </h2>
                    <div class="space-y-4">
                        <flux:field>
                            <flux:label>Dosen Pembimbing</flux:label>
                            <flux:select wire:model.live="dosen_selected" placeholder="Pilih Dosen Pembimbing">
                                @foreach ($dosenList as $dosen)
                                    <flux:select.option value="{{ $dosen->id }}">
                                        {{ $dosen->nama }}{{ $dosen->nidn ? " (NIDN: {$dosen->nidn})" : '' }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="dosen_selected" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Keterangan</flux:label>
                            <flux:textarea wire:model="admin_keterangan"
                                placeholder="Tambahkan keterangan untuk persetujuan kontrak..." />
                        </flux:field>
                    </div>

                    <div class="flex flex-col gap-3 mt-6">
                        <flux:button wire:click="showApprovalModal" variant="primary" icon="check-circle"
                            class="w-full justify-center" :disabled="$isProcessing || !$dosen_selected">
                            {{ $isProcessing ? 'Memproses...' : 'Setujui Kontrak' }}
                        </flux:button>

                        <flux:button wire:click="showRejectionModal" variant="danger" icon="x-circle"
                            class="w-full justify-center" :disabled="$isProcessing">
                            Tolak Kontrak
                        </flux:button>
                    </div>
                </div>
            @endif

            <!-- Status Information -->
            @if ($kontrakMagang)
                <div class="bg-white p-6 rounded-xl shadow border border-gray-200">
                    <h2 class="text-lg font-semibold text-black mb-4 flex items-center gap-2">
                        <flux:icon.information-circle class="w-5 h-5" />
                        Informasi Status
                    </h2>
                    <div class="space-y-3">
                        <div>
                            <label class="text-sm font-medium text-gray-500">Waktu Kontrak Dibuat</label>
                            <p class="text-gray-900">{{ $kontrakMagang->created_at->format('d M Y H:i') }}</p>
                        </div>
                        @if ($kontrakMagang->updated_at != $kontrakMagang->created_at)
                            <div>
                                <label class="text-sm font-medium text-gray-500">Terakhir Diperbarui</label>
                                <p class="text-gray-900">{{ $kontrakMagang->updated_at->format('d M Y H:i') }}</p>
                            </div>
                        @endif
                        @if ($kontrakMagang->keterangan)
                            <div>
                                <label class="text-sm font-medium text-gray-500">Keterangan</label>
                                <p class="text-gray-900 text-sm bg-gray-50 p-3 rounded-lg">
                                    {{ $kontrakMagang->keterangan }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Approval Confirmation Modal -->
    <flux:modal name="approve-modal" title="Konfirmasi Persetujuan Kontrak">
        <div class="space-y-4">
            <p>Apakah Anda yakin ingin menyetujui kontrak magang ini?</p>
            @if ($dosen_selected && $kontrakMagang)
                @php
                    $selectedDosen = DosenPembimbing::find($dosen_selected);
                @endphp
                <div class="bg-blue-50 p-4 rounded-lg">
                    <p class="text-sm"><strong>Dosen Pembimbing:</strong> {{ $selectedDosen?->nama ?? 'N/A' }}</p>
                    <p class="text-sm"><strong>Perusahaan:</strong>
                        {{ $kontrakMagang->lowonganMagang->perusahaan->nama ?? 'N/A' }}</p>
                    <p class="text-sm"><strong>Posisi:</strong>
                        {{ $kontrakMagang->lowonganMagang->pekerjaan->nama ?? 'N/A' }}</p>
                </div>
            @endif
        </div>

        <div class="flex justify-end gap-3 mt-6">
            <flux:button variant="ghost" onclick="Flux.modal('approve-modal').close()">
                Batal
            </flux:button>
            <flux:button wire:click="approveContract" variant="primary" :disabled="$isProcessing">
                {{ $isProcessing ? 'Memproses...' : 'Ya, Setujui Kontrak' }}
            </flux:button>
        </div>
    </flux:modal>

    <!-- Rejection Modal -->
    <flux:modal name="reject-modal" title="Tolak Kontrak Magang">
        <div class="space-y-4">
            <p>Berikan alasan penolakan kontrak magang ini:</p>
            <div class="bg-yellow-50 p-3 rounded-lg border border-yellow-200">
                <p class="text-sm text-yellow-800">
                    <strong>Peringatan:</strong> Menolak kontrak akan mengembalikan status menjadi "Ditolak"
                    dan mahasiswa perlu mengajukan ulang.
                </p>
            </div>
            <flux:field>
                <flux:label>Alasan Penolakan *</flux:label>
                <flux:textarea wire:model="rejection_reason"
                    placeholder="Masukkan alasan penolakan kontrak yang jelas..." rows="4" required />
                <flux:error name="rejection_reason" />
            </flux:field>
        </div>

        <div class="flex justify-end gap-3 mt-6">
            <flux:button variant="ghost" onclick="Flux.modal('reject-modal').close()">
                Batal
            </flux:button>
            <flux:button wire:click="rejectContract" variant="danger" :disabled="$isProcessing">
                {{ $isProcessing ? 'Memproses...' : 'Tolak Kontrak' }}
            </flux:button>
        </div>
    </flux:modal>

    <!-- Success Modal -->
    <flux:modal name="success-modal" title="Berhasil">
        <div class="text-center py-4">
            <flux:icon.check-circle class="w-16 h-16 text-green-500 mx-auto mb-4" />
            <p class="text-lg">Kontrak berhasil diproses!</p>
        </div>
        <div class="flex justify-center mt-6">
            <flux:button onclick="Flux.modal('success-modal').close()" variant="primary">
                Tutup
            </flux:button>
        </div>
    </flux:modal>
</div>
