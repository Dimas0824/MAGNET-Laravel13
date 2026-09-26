<?php

use Flux\Flux;
use function Livewire\Volt\{layout, state, mount};
use App\Models\Perusahaan;

layout('components.layouts.user.main');

state([
    'perusahaan',
    'nama',
    'lokasi_magang_id',
    'bidang_industri',
    'kategori',
    'rating',
    'logo',

    'isEditing' => false,

    'isDataDeleted' => false,
]);

mount(function (int $id) {
    $this->perusahaan = Perusahaan::findOrFail($id);
    $this->nama = $this->perusahaan->nama;
    $this->lokasi_magang_id = $this->perusahaan->lokasi_magang_id;
    $this->bidang_industri = $this->perusahaan->bidang_industri;
    $this->kategori = $this->perusahaan->kategori;
    $this->rating = $this->perusahaan->rating;
    $this->logo = $this->perusahaan->logo;
});

$editData = fn() => ($this->isEditing = !$this->isEditing);

$updateData = function () {
    $this->perusahaan->nama = $this->nama;
    $this->perusahaan->lokasi_magang_id = $this->lokasi_magang_id;
    $this->perusahaan->bidang_industri = $this->bidang_industri;
    $this->perusahaan->kategori = $this->kategori;

    $isSuccess = $this->perusahaan->save();

    $message = '';
    if ($isSuccess) {
        $message = 'Sukses memperbarui data perusahaan';
    } else {
        $message = 'Gagal memperbarui data perusahaan';
    }

    $this->isEditing = false;

    session()->flash('task', 'Memperbarui data perusahaan');
    session()->flash('message', $message);

    Flux::modal('response-modal')->show();
};

$deleteData = function () {
    $isSuccess = $this->perusahaan->delete();

    $message = '';
    if ($isSuccess) {
        $this->isDataDeleted = true;
        $message = 'Sukses menghapus data perusahaan';
    } else {
        $message = 'Gagal menghapus data perusahaan';
    }

    session()->flash('task', 'Menghapus data perusahaan');
    session()->flash('message', $message);

    Flux::modal('delete-data')->close();
    Flux::modal('response-modal')->show();
};

?>

<div class="flex flex-col gap-5">
    <x-slot:user>admin</x-slot:user>

    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}" icon="home" icon:variant="outline" />
        <flux:breadcrumbs.item href="{{ route('admin.data-perusahaan') }}" class="text-black">Kelola Data Perusahaan
        </flux:breadcrumbs.item>
        <flux:breadcrumbs.item class="text-black">Detail Data Perusahaan
        </flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <h1 class="text-base font-bold leading-6 text-black">Detail Informasi Perusahaan</h1>

    @if (!$isDataDeleted)
        <div class="min-h-screen flex gap-8 items-start">
            <div class="flex flex-col items-center bg-white p-4 rounded-xl shadow-md w-96 max-w-96">
                <img src="{{ asset($logo ?? 'img/user/unknown.jpeg') }}" alt="Foto perusahaan"
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

            <div class="card bg-white rounded-xl shadow-md w-full  text-black">
                <div class="card-body">
                    <h2 class="text-lg font-semibold">Informasi Perusahaan</h2>

                    <div class="flex flex-col gap-4">
                        <flux:field>
                            <flux:label>Nama</flux:label>
                            @if ($isEditing)
                                <flux:input value="{{ $nama }}" wire:model="nama" />
                            @else
                                <flux:input value="{{ $nama }}" wire:model="nama" readonly
                                    class="caret-transparent" />
                            @endif
                            <flux:error name="nama" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Lokasi</flux:label>
                            @if ($isEditing)
                                <flux:select placeholder="Pilih lokasi" wire:model="lokasi_magang_id">
                                    @foreach (\App\Models\LokasiMagang::orderBy('kategori_lokasi')->get() as $lokasi)
                                        <flux:select.option value="{{ $lokasi->id }}">{{ $lokasi->kategori_lokasi }} — {{ $lokasi->lokasi }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            @else
                                <flux:input value="{{ optional(\App\Models\LokasiMagang::find($lokasi_magang_id))->lokasi }}" readonly
                                    class="caret-transparent" />
                            @endif
                            <flux:error name="lokasi_magang_id" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Bidang Industri</flux:label>
                            <flux:select placeholder="Bidang industri perusahaan"
                                wire:model="storePerusahaanBidangIndustri">
                                <flux:select.option value="Perbankan">Perbankan</flux:select.option>
                                <flux:select.option value="Kesehatan">Kesehatan</flux:select.option>
                                <flux:select.option value="Pendidikan">Pendidikan</flux:select.option>
                                <flux:select.option value="E-Commerce">E-Commerce</flux:select.option>
                                <flux:select.option value="Telekomunikasi">Telekomunikasi</flux:select.option>
                                <flux:select.option value="Transportasi">Transportasi</flux:select.option>
                                <flux:select.option value="Pemerintahan">Pemerintahan</flux:select.option>
                                <flux:select.option value="Manufaktur">Manufaktur</flux:select.option>
                                <flux:select.option value="Energi">Energi</flux:select.option>
                                <flux:select.option value="Media">Media</flux:select.option>
                                <flux:select.option value="Teknologi">Teknologi</flux:select.option>
                                <flux:select.option value="Agrikultur">Agrikultur</flux:select.option>
                                <flux:select.option value="Pariwisata">Pariwisata</flux:select.option>
                                <flux:select.option value="Keamanan">Keamanan</flux:select.option>
                            </flux:select>
                            <flux:error name="bidang_industri" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Kategori Relasi</flux:label>
                            <flux:select placeholder="Kategori relasi perusahaan" wire:model="storePerusahaanKategori">
                                <flux:select.option value="mitra">Mitra</flux:select.option>
                                <flux:select.option value="non_mitra">Non-Mitra</flux:select.option>
                            </flux:select>
                            <flux:error name="kategori" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Rating</flux:label>
                            <flux:input value="{{ $rating }}" wire:model="nama" diasbled
                                class="caret-transparent" />
                        </flux:field>
                    </div>
                </div>
            </div>
        </div>
    @else
        <x-user.card.data-is-deleted backRoute="{{ route('admin.data-perusahaan') }}" />
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
                <flux:heading size="lg">Hapus data perusahaan</flux:heading>
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
</div>
