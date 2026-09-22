<?php

use App\Events\MahasiswaPreferenceUpdated;
use App\Helpers\DecisionMaking\ROC;
use App\Models\BidangIndustri;
use App\Models\KriteriaBidangIndustri;
use App\Models\KriteriaJenisMagang;
use App\Models\KriteriaLokasiMagang;
use App\Models\KriteriaOpenRemote;
use App\Models\KriteriaPekerjaan;
use App\Models\LokasiMagang;
use App\Models\Mahasiswa;
use App\Models\Pekerjaan;
use Flux\Flux;
use Illuminate\Support\Facades\DB;

use function Livewire\Volt\layout;
use function Livewire\Volt\on;
use function Livewire\Volt\state;

layout('components.layouts.guest.with-navbar');

state([
    'step' => 1,
    'showStep1' => true,
    'showStep2' => false,

    'pekerjaan_list' => Pekerjaan::pluck('nama', 'id'),
    'bidang_industri_list' => BidangIndustri::pluck('nama', 'id'),
    'lokasi_magang_list' => LokasiMagang::pluck('kategori_lokasi', 'id'),

    'pekerjaan',
    'bidang_industri',
    'lokasi_magang',
    'jenis_magang',
    'open_remote',

    'pekerjaan_rank',
    'bidang_industri_rank',
    'lokasi_magang_rank',
    'jenis_magang_rank',
    'open_remote_rank',
]);

on([
    'update-step' => function (array $data) {
        $this->step = $data['step'];

        if ($this->step == 2) {
            $this->pekerjaan = $data['pekerjaan'];
            $this->bidang_industri = $data['bidang_industri'];
            $this->lokasi_magang = $data['lokasi_magang'];
            $this->jenis_magang = $data['jenis_magang'];
            $this->open_remote = $data['open_remote'];
        } elseif ($this->step == 3) {
            $this->pekerjaan_rank = $data['pekerjaan_rank'];
            $this->bidang_industri_rank = $data['bidang_industri_rank'];
            $this->lokasi_magang_rank = $data['lokasi_magang_rank'];
            $this->jenis_magang_rank = $data['jenis_magang_rank'];
            $this->open_remote_rank = $data['open_remote_rank'];

            $this->storePreferensiMahasiswa();
        }
    },
]);

$storePreferensiMahasiswa = function () {
    $status = 'success';
    $message = 'Data preferensi magang berhasil dibuat';

    $totalCriteria = config('recommendation-system.roc.total_criteria');

    try {
        DB::transaction(function () use ($totalCriteria) {
            $mhs_id = auth('mahasiswa')->user()->id;

            KriteriaPekerjaan::forceCreate([
                'pekerjaan_id' => $this->pekerjaan,
                'mahasiswa_id' => $mhs_id,
                'rank' => $this->pekerjaan_rank,
                'bobot' => ROC::getWeight($this->pekerjaan_rank, $totalCriteria),
            ]);

            KriteriaBidangIndustri::forceCreate([
                'bidang_industri_id' => $this->bidang_industri,
                'mahasiswa_id' => $mhs_id,
                'rank' => $this->bidang_industri_rank,
                'bobot' => ROC::getWeight($this->bidang_industri_rank, $totalCriteria),
            ]);

            KriteriaLokasiMagang::forceCreate([
                'lokasi_magang_id' => $this->lokasi_magang,
                'mahasiswa_id' => $mhs_id,
                'rank' => $this->lokasi_magang_rank,
                'bobot' => ROC::getWeight($this->lokasi_magang_rank, $totalCriteria),
            ]);

            KriteriaJenisMagang::forceCreate([
                'jenis_magang' => $this->jenis_magang,
                'mahasiswa_id' => $mhs_id,
                'rank' => $this->jenis_magang_rank,
                'bobot' => ROC::getWeight($this->jenis_magang_rank, $totalCriteria),
            ]);

            KriteriaOpenRemote::forceCreate([
                'open_remote' => $this->open_remote,
                'mahasiswa_id' => $mhs_id,
                'rank' => $this->open_remote_rank,
                'bobot' => ROC::getWeight($this->open_remote_rank, $totalCriteria),
            ]);

            $mahasiswa = Mahasiswa::find(auth('mahasiswa')->user()->id);

            event(new MahasiswaPreferenceUpdated($mahasiswa));
        });
    } catch (Exception $e) {
        $status = 'failed';
        $message = 'Data preferensi magang gagal dibuat. Pastikan data sudah terisi dengan benar';
    }

    session()->flash('status', $status);
    session()->flash('task', 'Membuat data preferensi magang');
    session()->flash('message', $message);

    Flux::modal('response-modal')->show();
};

$redirectToDashboard = fn () => redirect()->route('dashboard');
$resetPage = fn () => redirect()->back();

?>

<div class="min-h-screen h-full">
    <x-slot:topScript>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    </x-slot:topScript>

    <div wire:show="step == 1" x-transition:leave.duration.400ms>
        <livewire:components.mahasiswa.persiapan-preferensi.step-1 />
    </div>

    <div wire:show="step == 2" x-transition:leave.duration.400ms>
        <livewire:components.mahasiswa.persiapan-preferensi.step-2 />
    </div>

    <flux:modal name="response-modal" class="min-w-[24rem]">
        <div class="space-y-6">
            <div class="text-center">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <flux:icon.check class="w-8 h-8 text-green-600" />
                </div>
                <flux:heading size="lg">{{ session('task') }}</flux:heading>
                <flux:text class="mt-2 text-gray-600">
                    <p>{{ session('message') }}</p>
                </flux:text>
            </div>
            <div class="flex gap-2 justify-center">
                <flux:modal.close>
                    @if (session('status') == 'success')
                        <flux:button type="submit" variant="primary" class="bg-magnet-sky-teal px-8 py-2"
                            wire:click="redirectToDashboard">
                            Lanjut ke Dashboard
                        </flux:button>
                    @else
                        <flux:button type="submit" variant="primary" class="bg-magnet-sky-teal px-8 py-2"
                            wire:click="resetPage">
                            Kembali isi ulang data preferensi
                        </flux:button>
                    @endif
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
