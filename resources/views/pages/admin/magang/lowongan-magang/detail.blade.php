<?php

use Flux\Flux;
use function Livewire\Volt\{layout, state, mount};
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\{LowonganMagang, Perusahaan, LokasiMagang, Pekerjaan};

layout('components.layouts.user.main');

state([
    'lowongan_magang',
    'deskripsi',
    'persyaratan',
    'kuota',
    'pekerjaan_id',
    'jenis_magang',
    'status',
    'lokasi_id',
    'logo',
    'perusahaan_id',
    'jumlah_mhs_magang',
    'sisa_kuota',

    'perusahaan_list' => [],
    'pekerjaan_list' => [],
    'lokasi_list' => [],

    'isEditing' => false,
    'isDataDeleted' => false,
    'isDataFound' => true
]);

mount(function (int $id) {
    try {
        $this->lowongan_magang = LowonganMagang::with('perusahaan')->findOrFail($id);
        $this->deskripsi = $this->lowongan_magang->deskripsi;
        $this->persyaratan = $this->lowongan_magang->persyaratan;
        $this->kuota = $this->lowongan_magang->kuota;
        $this->pekerjaan_id = $this->lowongan_magang->pekerjaan_id;
        $this->jenis_magang = $this->lowongan_magang->jenis_magang;
        $this->status = $this->lowongan_magang->status;
        $this->lokasi_id = $this->lowongan_magang->lokasi_magang_id;
        $this->logo = $this->lowongan_magang->perusahaan->logo;
        $this->perusahaan_id = $this->lowongan_magang->perusahaan_id;
        $this->jumlah_mhs_magang = $this->lowongan_magang->kontrak_magang()->count();
        $this->sisa_kuota = $this->kuota - $this->jumlah_mhs_magang;

        $this->perusahaan_list = Perusahaan::select('id', 'nama')->get()->toArray();
        $this->pekerjaan_list = Pekerjaan::select('id', 'nama')->get()->toArray();
        $this->lokasi_list = LokasiMagang::select('id', 'lokasi')->get()->toArray();
    } catch (ModelNotFoundException $error) {
        $this->isDataFound = false;
    }
});

$editData = fn() => ($this->isEditing = !$this->isEditing);

$updateData = function () {
    $this->lowongan_magang->deskripsi = $this->deskripsi;
    $this->lowongan_magang->persyaratan = $this->persyaratan;
    $this->lowongan_magang->kuota = $this->kuota;
    $this->lowongan_magang->pekerjaan_id = $this->pekerjaan_id;
    $this->lowongan_magang->jenis_magang = $this->jenis_magang;
    $this->lowongan_magang->status = $this->status;
    $this->lowongan_magang->lokasi_magang_id = $this->lokasi_id;
    $this->lowongan_magang->perusahaan_id = $this->perusahaan_id;
    $isSuccess = $this->lowongan_magang->save();

    $message = '';
    if ($isSuccess) {
        $message = 'Sukses memperbarui data lowongan magang';
    } else {
        $message = 'Gagal memperbarui data lowongan magang';
    }

    $this->isEditing = false;

    session()->flash('task', 'Memperbarui data lowongan perusahaan');
    session()->flash('message', $message);

    Flux::modal('response-modal')->show();
};

$deleteData = function () {
    $isSuccess = $this->lowongan_magang->delete();

    $message = '';
    if ($isSuccess) {
        $this->isDataDeleted = true;
        $message = 'Sukses menghapus data lowongan magangn';
    } else {
        $message = 'Gagal menghapus data lowongan magang';
    }

    session()->flash('task', 'Menghapus data lowongan magang');
    session()->flash('message', $message);

    Flux::modal('delete-data')->close();
    Flux::modal('response-modal')->show();
};

?>


<div class="flex flex-col gap-5">
    <x-slot:user>admin</x-slot:user>

    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" icon:variant="outline" />
        <flux:breadcrumbs.item href="{{ route('admin.data-lowongan') }}" class="text-black">Kelola Data Lowongan Magang
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item class="text-black">Detail Data Lowongan Magang
        </flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <h1 class="text-base font-bold leading-6 text-black">Detail Informasi Lowongan Magang</h1>

    @if ($isDataFound)
        @if (!$isDataDeleted)
            <div class="min-h-full flex gap-8 items-start">
                <div class="flex flex-col items-center bg-white p-4 rounded-xl shadow-md w-96 max-w-96">
                    <img src="{{ asset('img/user/unknown.jpeg') }}" alt="Logo Perusahaan"
                        class="w-64 object-cover rounded-md mb-4" />
                    <div class="flex flex-col gap-4 w-full">
                        @if ($isEditing)
                            <div class="flex w-full gap-2">
                                <flux:button wire:click="editData" icon="x"
                                    class="w-1/2 bg-gray-700! hover:bg-gray-400! text-white! text-wrap! text-left rounded-md items-center">
                                    Batalkan
                                </flux:button>
                                <flux:button wire:click="updateData" icon="pencil"
                                    class="w-1/2 bg-emerald-400! hover:bg-green-500! text-white! rounded-md items-center">
                                    Simpan
                                </flux:button>
                            </div>
                        @else
                            <flux:button wire:click="editData" icon="pencil"
                                class="bg-emerald-400! hover:bg-green-500! text-white! rounded-md items-center">
                                Edit
                            </flux:button>
                        @endif

                        <flux:modal.trigger name="delete-data">
                            <flux:button icon="trash-2"
                                class="bg-red-400! hover:bg-red-500! text-white! rounded-md items-center">
                                Hapus
                            </flux:button>
                        </flux:modal.trigger>
                    </div>
                </div>

                <div class="card bg-white rounded-xl shadow-md w-full">
                    <div class="card-body gap-4">
                        <h2 class="text-lg font-semibold mb-4">Informasi Perusahaan</h2>

                        <div class="space-y-4">
                            <flux:field>
                                <flux:label>Perusahaan</flux:label>
                                @if ($isEditing)
                                    <flux:select placeholder="Perusahaan" wire:model="perusahaan_id">
                                        @foreach ($perusahaan_list as $perusahaan)
                                            <flux:select.option value="{{ $perusahaan['id'] }}">{{ $perusahaan['nama'] }}
                                            </flux:select.option>
                                        @endforeach
                                    </flux:select>
                                @else
                                    <flux:select placeholder="Perusahaan" wire:model="perusahaan_id" disabled>
                                        @foreach ($perusahaan_list as $perusahaan)
                                            <flux:select.option value="{{ $perusahaan['id'] }}">{{ $perusahaan['nama'] }}
                                            </flux:select.option>
                                        @endforeach
                                    </flux:select>
                                @endif
                            </flux:field>

                            <flux:field>
                                <flux:label>Pekerjaan</flux:label>
                                @if ($isEditing)
                                    <flux:select placeholder="Pekerjaan" wire:model="pekerjaan_id">
                                        @foreach ($pekerjaan_list as $pekerjaan)
                                            <flux:select.option value="{{ $pekerjaan['id'] }}">{{ $pekerjaan['nama'] }}
                                            </flux:select.option>
                                        @endforeach
                                    </flux:select>
                                @else
                                    <flux:select placeholder="Pekerjaan" wire:model="pekerjaan_id" disabled>
                                        @foreach ($pekerjaan_list as $pekerjaan)
                                            <flux:select.option value="{{ $pekerjaan['id'] }}">{{ $pekerjaan['nama'] }}
                                            </flux:select.option>
                                        @endforeach
                                    </flux:select>
                                @endif
                            </flux:field>

                            <flux:field>
                                <flux:label>Lokasi</flux:label>
                                @if ($isEditing)
                                    <flux:select placeholder="Perusahaan" wire:model="lokasi_id">
                                        @foreach ($lokasi_list as $lokasi)
                                            <flux:select.option value="{{ $lokasi['id'] }}">{{ $lokasi['lokasi'] }}
                                            </flux:select.option>
                                        @endforeach
                                    </flux:select>
                                @else
                                    <flux:select placeholder="Perusahaan" wire:model="lokasi_id" disabled>
                                        @foreach ($lokasi_list as $lokasi)
                                            <flux:select.option value="{{ $lokasi['id'] }}">{{ $lokasi['lokasi'] }}
                                            </flux:select.option>
                                        @endforeach
                                    </flux:select>
                                @endif
                            </flux:field>

                            <flux:field>
                                <flux:label>Status</flux:label>
                                @if ($isEditing)
                                    <flux:select placeholder="Status" wire:model="status">
                                        <flux:select.option value="buka">Buka</flux:select.option>
                                        <flux:select.option value="tutup">Tutup</flux:select.option>
                                    </flux:select>
                                @else
                                    <flux:select placeholder="Status" wire:model="status" disabled>
                                        <flux:select.option value="buka">Buka</flux:select.option>
                                        <flux:select.option value="tutup">Tutup</flux:select.option>
                                    </flux:select>
                                @endif
                            </flux:field>

                            <flux:field>
                                <flux:label>Deskripsi</flux:label>
                                @if ($isEditing)
                                    <flux:textarea wire:model="deskripsi" />
                                @else
                                    <flux:textarea wire:model="deskripsi" disabled />
                                @endif
                            </flux:field>

                            <flux:field>
                                <flux:label>Persyaratan</flux:label>
                                @if ($isEditing)
                                    <flux:textarea wire:model="persyaratan]" />
                                @else
                                    <flux:textarea wire:model="persyaratan" disabled />
                                @endif
                            </flux:field>

                            <flux:field>
                                <flux:label>Maksimum Kuota</flux:label>
                                @if ($isEditing)
                                    <flux:input wire:model="kuota" />
                                @else
                                    <flux:input wire:model="kuota" readonly class="caret-transparent" />
                                @endif
                            </flux:field>

                            <flux:field>
                                <flux:label>Jumlah Pendaftar</flux:label>
                                <flux:input wire:model="jumlah_mhs_magang" readonly class="caret-transparent" />
                            </flux:field>

                            <flux:field>
                                <flux:label>Sisa Kuota</flux:label>
                                <flux:input wire:model="sisa_kuota" readonly class="caret-transparent" />
                            </flux:field>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <x-user.card.data-is-deleted backRoute="{{ route('admin.data-lowongan') }}" />
        @endif

        <flux:modal name="response-modal" class="min-w-[22rem]">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ session('task') }}</flux:heading>
                    <flux:text class="mt-2">
                        <p>{{ session('message') }}</p>
                    </flux:text>
                </div>
                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button type="submit" variant="primary" class="bg-magnet-sky-teal">Simpan</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        </flux:modal>

        <flux:modal name="delete-data" class="min-w-[22rem]">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Hapus Data?</flux:heading>
                    <flux:text class="mt-2">
                        <p>Apakah anda yakin ingin menghapus data perusahaan ?</p>
                    </flux:text>
                </div>
                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">Batalkan</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="danger" wire:click="deleteData">Hapus</flux:button>
                </div>
            </div>
        </flux:modal>

        <flux:modal name="tutup-lowongan" class="min-w-[22rem]">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Tutup Lowongan?</flux:heading>
                    <flux:text class="mt-2">
                        <p>Apakah anda yakin ingin menutup lowongan magang ini ?</p>
                    </flux:text>
                </div>
                <div class="flex gap-2">
                    <flux:spacer />
                    <flux:modal.close>
                        <flux:button variant="ghost">Batalkan</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="danger">Tutup</flux:button>
                </div>
            </div>
        </flux:modal>
    @else
        <div class="card">
            <div class="card-body bg-red-100 text-red-600  rounded-xl w-full flex flex-row items-center">
                <flux:icon.triangle-alert />
                <h1 class="font-semibold">Data lowongan magang tidak bisa ditemukan!</h1>
            </div>
        </div>
    @endif

</div>
