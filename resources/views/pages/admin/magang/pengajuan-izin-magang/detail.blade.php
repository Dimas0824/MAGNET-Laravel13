<?php

use Flux\Flux;
use function Livewire\Volt\{layout, state, mount};
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\FormPengajuanMagang;
use App\Models\DosenPembimbing;

layout('components.layouts.user.main');

state([
    'formPengajuanMagang' => null,
    'status_pengajuan' => null,

    'berkasPengajuanMagang' => null,

    'nama_lengkap' => null,
    'nim' => null,
    'jurusan' => null,
    'program_studi' => null,
    'jenis_kelamin' => null,
    'tanggal_lahir' => null,

    'cv' => null,
    'transkrip_nilai' => null,
    'portfolio' => null,

    'dosenPembimbingList' => [],
    'dosenPembimbingSelected' => null,

    'isDataFound' => true,
]);

mount(function (int $id) {
    try {
        $this->formPengajuanMagang = FormPengajuanMagang::with(['berkasPengajuanMagang.mahasiswa'])->findOrFail($id);

        $status = $this->formPengajuanMagang->status;
        $this->status_pengajuan = $status == 'diproses' ? 'Belum diverifikasi' : ucfirst($status);

        $berkas = $this->formPengajuanMagang->berkasPengajuanMagang;

        $this->cv = $berkas->cv;
        $this->transkrip_nilai = $berkas->transkrip_nilai;
        $this->portfolio = $berkas->portfolio;
        $this->nama_lengkap = optional($berkas->mahasiswa)->nama;
        $this->nim = optional($berkas->mahasiswa)->nim;
        $this->jurusan = optional($berkas->mahasiswa)->jurusan;
        $this->program_studi = optional($berkas->mahasiswa)->program_studi;
        $this->jenis_kelamin = optional($berkas->mahasiswa)->jenis_kelamin;
        $this->tanggal_lahir = optional($berkas->mahasiswa)->tanggal_lahir;

        $this->dosenPembimbingList = DosenPembimbing::pluck('nama', 'id');
    } catch (ModelNotFoundException $error) {
        $this->isDataFound = false;
    }
});

$verifyFormRequest = function (string $status) {
    $this->formPengajuanMagang->status = $status;
    $success = $this->formPengajuanMagang->save();

    if ($success) {
        $task = "Pengajuan magang berhasil " . $status;
        $message = "Anda telah " . $status == 'diterima' ? 'menyetujui' : 'menolak'  ." pengajuan magang";
        $this->status_pengajuan = ucfirst($this->formPengajuanMagang->status);
    } else {
        $task = "Pengajuan magang gagal disetujui";
        $message = "Verifikasi berkas pengajuan magang gagal dilakukan.";
    }

    session()->flash('task', $task);
    session()->flash('message', $message);

    Flux::modal('confirm-accept-form')->close();
    Flux::modal('success-accept-form')->show();
};

?>

<div class="flex flex-col gap-5">
    <x-slot:user>admin</x-slot:user>

    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" icon:variant="outline" />
        <flux:breadcrumbs.item href="{{ route('admin.data-pengajuan-izin-magang') }}" class="text-black">Kelola data pengajuan
            magang
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item class="text-black">Detail pengajuan magang
            Magang
        </flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <h1 class="text-base font-bold leading-6 text-black">Detail Informasi Pengajuan Magang</h1>

    @if ($isDataFound)
        <div class="min-h-full flex gap-8 items-start">
            <div class="flex flex-col items-center bg-white p-4 rounded-xl shadow-md w-96 max-w-96">
                <img src="{{ asset('img/user/student-girl.png') }}" alt="Foto Mahasiswa"
                    class="w-64 object-cover rounded-md" />
            </div>

            <div class="w-full flex flex-col gap-8">
                @php
                    $cardColor = match ($status_pengajuan) {
                        'Belum diverifikasi' => 'bg-amber-200 border-amber-600',
                        'Ditolak' => 'bg-red-200 border-red-600',
                        'Diterima' => 'bg-green-200 border-green-600',
                    };

                    $badgeColor = match ($status_pengajuan) {
                        'Belum diverifikasi' => 'amber',
                        'Ditolak' => 'red',
                        'Diterima' => 'green',
                    };
                @endphp
                <div class="card border {{ $cardColor }} rounded-2xl">
                    <div class="card-body flex flex-row gap-6 items-center">
                        <h3 class="font-semibold">Status pengajuan magang</h3>
                        <flux:badge variant="solid" color="{{ $badgeColor }}">{{ $status_pengajuan }}</flux:badge>
                    </div>
                </div>

                <div class="card bg-white rounded-xl shadow-md">
                    <div class="card-body">
                        <h2 class="text-lg font-semibold">Informasi Pelamar</h2>

                        <div class="flex flex-col gap-4">
                            <flux:field>
                                <flux:label>Nama Lengkap</flux:label>
                                <flux:input wire:model="nama_lengkap" readonly class="caret-transparent" />
                            </flux:field>

                            <flux:field>
                                <flux:label>NIM</flux:label>
                                <flux:input wire:model="nim" readonly class="caret-transparent" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Jurusan</flux:label>
                                <flux:input wire:model="jurusan" readonly class="caret-transparent" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Program Studi</flux:label>
                                <flux:input wire:model="program_studi" readonly class="caret-transparent" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Jenis Kelamin</flux:label>
                                <flux:input wire:model="jenis_kelamin" readonly class="caret-transparent" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Tanggal Lahir</flux:label>
                                <flux:input wire:model="tanggal_lahir" readonly class="caret-transparent" />
                            </flux:field>
                        </div>
                    </div>
                </div>

                <div class="card bg-white rounded-xl shadow-md">
                    <div class="card-body">
                        <h2 class="text-lg font-semibold">Dokumen-dokumen magang</h2>
                        <div class="flex flex-col gap-4">
                            <flux:field>
                                <flux:label>Daftar Riwayat Hidup (CV)</flux:label>
                                @if ($cv)
                                    <a href="{{ route('berkas.download', ['berkas' => $berkasPengajuanMagang->id, 'type' => 'cv']) }}"
                                        class="text-blue-600 underline" target="_blank">Unduh CV</a>
                                @else
                                    <span class="text-gray-400">Tidak ada</span>
                                @endif
                            </flux:field>

                            <flux:field>
                                <flux:label>Transkrip Nilai</flux:label>
                                @if ($transkrip_nilai)
                                    <a href="{{ route('berkas.download', ['berkas' => $berkasPengajuanMagang->id, 'type' => 'transkrip_nilai']) }}"
                                        class="text-blue-600 underline" target="_blank">Unduh Transkrip Nilai</a>
                                @else
                                    <span class="text-gray-400">Tidak ada</span>
                                @endif
                            </flux:field>

                            <flux:field>
                                <flux:label>Portfolio</flux:label>
                                @if ($portfolio)
                                    <a href="{{ route('berkas.download', ['berkas' => $berkasPengajuanMagang->id, 'type' => 'portfolio']) }}"
                                        class="text-blue-600 underline" target="_blank">Unduh Portfolio</a>
                                @else
                                    <span class="text-gray-400">Tidak ada</span>
                                @endif
                            </flux:field>
                        </div>
                    </div>
                </div>

                @if ($status_pengajuan == 'Belum diverifikasi')
                    <div class="flex justify-end">
                        <div class="flex gap-4">
                            <flux:modal.trigger name="confirm-accept-form">
                                <flux:button variant="primary" class="bg-green-400 hover:bg-green-600">Setujui
                                </flux:button>
                            </flux:modal.trigger>
                            <flux:modal.trigger name="confirm-reject-form">
                                <flux:button variant="primary" class="bg-red-400 hover:bg-red-600">Tolak</flux:button>
                            </flux:modal.trigger>
                        </div>
                    </div>

                    <flux:modal name="confirm-accept-form">
                        <div class="space-y-6">
                            <div class="flex flex-col gap-4">
                                <flux:heading size="lg">Menyetujui pengajuan magang</flux:heading>
                                <flux:text>
                                    <p>Apakah anda yakin menyetujui pengajuan magang ini?</p>
                                </flux:text>
                            </div>
                            <div class="flex">
                                <flux:spacer />

                                <flux:modal.close>
                                    <flux:button variant="ghost">Batalkan</flux:button>
                                </flux:modal.close>

                                <flux:modal.close>
                                    <flux:button
                                        type="submit"
                                        variant="primary"
                                        wire:click="verifyFormRequest('diterima')"
                                        class="bg-magnet-sky-teal hover:bg-cyan-600">
                                        Iya
                                    </flux:button>
                                </flux:modal.close>
                            </div>
                        </div>
                    </flux:modal>


                    <flux:modal name="confirm-reject-form">
                        <div class="space-y-6">
                            <div class="flex flex-col gap-4">
                                <flux:heading size="lg">Menolak pengajuan magang</flux:heading>
                                <flux:text>
                                    <p>Apakah anda yakin akan menolak pengajuan magang ini?</p>
                                </flux:text>
                            </div>
                            <div class="flex">
                                <flux:spacer />

                                <flux:modal.close>
                                    <flux:button variant="ghost">Batalkan</flux:button>
                                </flux:modal.close>

                                <flux:modal.close>
                                    <flux:button
                                        type="submit"
                                        variant="primary"
                                        wire:click="verifyFormRequest('ditolak')"
                                        class="bg-magnet-sky-teal hover:bg-cyan-600">
                                        Iya
                                    </flux:button>
                                </flux:modal.close>
                            </div>
                        </div>
                    </flux:modal>

                    <flux:modal name="success-accept-form">
                        <div class="space-y-6">
                            <div class="flex flex-col gap-4">
                                <flux:heading size="lg">{{ session('task') }}</flux:heading>
                                <flux:text>
                                    <p>{{ session('message') }}</p>
                                </flux:text>
                            </div>
                            <div class="flex">
                                <flux:spacer />

                                <flux:modal.close>
                                    <flux:button type="submit" variant="primary"
                                        class="bg-magnet-sky-teal hover:bg-cyan-600">
                                        Oke</flux:button>
                                </flux:modal.close>
                            </div>
                        </div>
                    </flux:modal>
                @endif
            </div>
        </div>
    @else
        <div class="card">
            <div class="card-body bg-red-100 text-red-600  rounded-xl w-full flex flex-row items-center">
                <flux:icon.triangle-alert />
                <h1 class="font-semibold">Data formulir pengajuan magang tidak bisa ditemukan!</h1>
            </div>
        </div>
    @endif
</div>
