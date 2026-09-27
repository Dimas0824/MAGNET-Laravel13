<?php

use function Livewire\Volt\{layout, state, protect};

layout('components.layouts.user.main');

$mahasiswa = auth('mahasiswa')->user();
$berkas = $mahasiswa->berkasPengajuanMagang()->latest('updated_at')->first();
$formPengajuan = $berkas?->formPengajuanMagang()->first();

state([
    'mahasiswa' => $mahasiswa,
    'status' => $mahasiswa->status_magang,
    'pengajuanMagang' => $formPengajuan?->status, // bisa null jika belum pernah ajukan
    'alasanDitolak' => $formPengajuan?->keterangan, // jika kamu simpan alasan di field ini
]);

?>

<div class="text-black flex flex-col gap-6">
    <x-slot:user>mahasiswa</x-slot:user>

    @if ($status == 'belum magang')
        @if (is_null($pengajuanMagang))
            {{-- Belum pernah mengajukan --}}
            <x-mahasiswa.pengajuan-magang.belum-diajukan />
        @elseif ($pengajuanMagang == 'diproses')
            {{-- Sedang diproses --}}
            <x-mahasiswa.pengajuan-magang.sedang-diproses />
        @elseif ($pengajuanMagang == 'diterima')
            {{-- Sudah diterima --}}
            <livewire:components.mahasiswa.pengajuan-magang.pengajuan-diterima /> {{-- Sudah mengajukan, tunggu proses selanjutnya --}}
        @elseif ($pengajuanMagang == 'ditolak')
            {{-- Ditolak, tampilkan alasan dan form ulang --}} <div class="bg-red-100 p-4 rounded shadow">
                <p class="text-red-600 font-semibold">Pengajuan Anda ditolak.</p>
                <p class="text-sm mt-1">Alasan: {{ $alasanDitolak ?? 'Tidak ada alasan yang diberikan.' }}</p>
            </div>
            <x-mahasiswa.pengajuan-magang.belum-diajukan />
        @endif
    @elseif (
        $status == 'sedang magang' ||
            $status == 'selesai magang' ||
            !in_array($status, ['belum magang', 'sedang magang', 'selesai magang']))
        <div
            class="bg-gradient-to-br from-blue-50 to-white border border-blue-100 rounded-2xl p-6 shadow flex items-center gap-6">
            <div
                class="flex items-center justify-center w-16 h-16 bg-blue-100 text-blue-600 rounded-full text-2xl shadow-inner">
                <i class="fa-solid fa-briefcase"></i>
            </div>
            <div class="flex-1">
                <h3 class="text-lg font-semibold text-gray-800">
                    {{ match ($status) {
                        'sedang magang' => 'Sedang Menjalani Magang',
                        'selesai magang' => 'Magang Telah Selesai',
                        default => 'Status Tidak Dikenali',
                    } }}
                </h3>
                <p class="text-sm text-gray-600 mt-1">
                    {{ match ($status) {
                        'sedang magang' => 'Anda sedang menjalani magang. Tidak perlu mengajukan kembali.',
                        'selesai magang' => 'Anda telah menyelesaikan magang. Terima kasih atas partisipasi Anda.',
                        default => 'Silakan hubungi admin jika informasi status magang Anda tidak sesuai.',
                    } }}
                </p>
            </div>
        </div>
    @endif
</div>
