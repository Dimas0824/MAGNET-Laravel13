<?php

use Flux\Flux;
use function Livewire\Volt\{state, mount};
use Illuminate\Support\Facades\Hash;
use App\Models\{Mahasiswa, BidangIndustri, LokasiMagang, Pekerjaan};
use App\Helpers\DecisionMaking\ROC;
use App\Events\MahasiswaPreferenceUpdated;

state([
    'mahasiswa',

    // Personal data fields
    'nama',
    'nim',
    'jurusan',
    'program_studi',
    'jenis_kelamin',
    'alamat',

    // Preference fields
    'bidang_industri',
    'jenis_magang',
    'lokasi_magang',
    'pekerjaan',
    'open_remote',

    // Password fields
    'current_password',
    'new_password',
    'new_password_confirmation',

    // Criteria ranking fields
    'criteria_rankings' => [],
    'temp_rankings' => [],

    // Options for dropdowns
    'program_studi_options' => Mahasiswa::distinct('program_studi')->pluck('program_studi', 'program_studi')->toArray(),
    'bidang_industri_options' => BidangIndustri::pluck('nama', 'nama')->toArray(),
    'jenis_magang_options' => [
        'Semua' => 'Semua',
        'berbayar' => 'Magang Berbayar (Paid Internship)',
        'tidak berbayar' => 'Magang Tidak Berbayar (Unpaid Internship)',
    ],
    'lokasi_magang_options' => LokasiMagang::pluck('kategori_lokasi', 'kategori_lokasi')->toArray(),
    'pekerjaan_options' => Pekerjaan::pluck('nama', 'nama')->toArray(),
    'open_remote_options' => [
        'Semua' => 'Semua',
        'ya' => 'Ya',
        'tidak' => 'Tidak',
    ],
    'jenis_kelamin_options' => [
        'L' => 'Laki-laki',
        'P' => 'Perempuan',
    ],

    // Edit states
    'isUpdatePersonalData' => false,
    'isUpdatePreference' => false,
    'isUpdatePassword' => false,
    'isUpdateRanking' => false,

    // Modal states
    'modalType' => '', // 'success' or 'error'
    'modalTitle' => '',
    'modalMessage' => '',

    // Active section state
    'activeSection' => 'personal-data',

    // Password visibility states
    'show_current_password' => false,
    'show_new_password' => false,
    'show_new_password_confirmation' => false,
]);

mount(function () {
    $this->mahasiswa = auth('mahasiswa')->user();

    // Load personal data
    $this->nama = $this->mahasiswa->nama;
    $this->nim = $this->mahasiswa->nim;
    $this->jurusan = $this->mahasiswa->jurusan;
    $this->program_studi = $this->mahasiswa->program_studi;
    $this->jenis_kelamin = $this->mahasiswa->jenis_kelamin;
    $this->alamat = $this->mahasiswa->alamat;

    // Load preference data dengan nama, bukan ID
    $this->bidang_industri = $this->mahasiswa->kriteriaBidangIndustri->bidangIndustri->nama;
    $this->jenis_magang = $this->mahasiswa->kriteriaJenisMagang->jenis_magang;
        $this->lokasi_magang = $this->mahasiswa->kriteriaLokasiMagang->lokasiMagang->kategori_lokasi;
    $this->pekerjaan = $this->mahasiswa->kriteriaPekerjaan->pekerjaan->nama;
    $this->open_remote = $this->mahasiswa->kriteriaOpenRemote->open_remote;

    // Load criteria rankings
    $this->loadCriteriaRankings();
});

// Section navigation function
$setActiveSection = function ($section) {
    $this->activeSection = $section;
};

// Load criteria rankings
$loadCriteriaRankings = function () {
    $this->criteria_rankings = [
        [
            'key' => 'pekerjaan',
            'label' => 'Pekerjaan',
            'icon' => 'briefcase',
            'description' => 'Jenis pekerjaan yang diinginkan',
            'rank' => $this->mahasiswa->kriteriaPekerjaan->rank,
            'bobot' => $this->mahasiswa->kriteriaPekerjaan->bobot,
        ],
        [
            'key' => 'bidang_industri',
            'label' => 'Bidang Industri',
            'icon' => 'building-office',
            'description' => 'Sektor industri yang diminati',
            'rank' => $this->mahasiswa->kriteriaBidangIndustri->rank,
            'bobot' => $this->mahasiswa->kriteriaBidangIndustri->bobot,
        ],
        [
            'key' => 'lokasi_magang',
            'label' => 'Lokasi Magang',
            'icon' => 'map-pin',
            'description' => 'Preferensi lokasi magang',
            'rank' => $this->mahasiswa->kriteriaLokasiMagang->rank,
            'bobot' => $this->mahasiswa->kriteriaLokasiMagang->bobot,
        ],
        [
            'key' => 'jenis_magang',
            'label' => 'Jenis Magang',
            'icon' => 'currency-dollar',
            'description' => 'Berbayar atau tidak berbayar',
            'rank' => $this->mahasiswa->kriteriaJenisMagang->rank,
            'bobot' => $this->mahasiswa->kriteriaJenisMagang->bobot,
        ],
        [
            'key' => 'open_remote',
            'label' => 'Remote Work',
            'icon' => 'computer-desktop',
            'description' => 'Kesempatan kerja remote',
            'rank' => $this->mahasiswa->kriteriaOpenRemote->rank,
            'bobot' => $this->mahasiswa->kriteriaOpenRemote->bobot,
        ],
    ];

    // Sort by rank (ascending)
    usort($this->criteria_rankings, fn($a, $b) => $a['rank'] <=> $b['rank']);
};

// Personal Data Functions
$updatePersonalData = function () {
    $this->isUpdatePersonalData = true;
};

$savePersonalData = function () {
    try {
        $this->validate([
            'nama' => 'required|string|max:255',
            'nim' => 'required|string|max:20|unique:mahasiswa,nim,' . $this->mahasiswa->id,
            'jurusan' => 'required|string|max:255',
            'program_studi' => 'required|string|max:255',
            'jenis_kelamin' => 'required|in:L,P',
            'alamat' => 'required|string|max:500',
        ]);

        $this->mahasiswa->update([
            'nama' => $this->nama,
            'nim' => $this->nim,
            'jurusan' => $this->jurusan,
            'program_studi' => $this->program_studi,
            'jenis_kelamin' => $this->jenis_kelamin,
            'alamat' => $this->alamat,
            'updated_at' => now(),
        ]);

        $this->showModal('success', 'Data Personal Berhasil Diperbarui', 'Data personal Anda telah berhasil diperbarui.');
        $this->isUpdatePersonalData = false;
    } catch (\Illuminate\Validation\ValidationException $e) {
        $this->showModal('error', 'Gagal Memperbarui Data Personal', 'Terjadi kesalahan validasi. Silakan periksa kembali data Anda.');
    } catch (\Exception $e) {
        $this->showModal('error', 'Gagal Memperbarui Data Personal', 'Terjadi kesalahan sistem. Silakan coba lagi.');
    }
};

$cancelUpdatePersonalData = function () {
    // Reset ke nilai asli
    $this->nama = $this->mahasiswa->nama;
    $this->nim = $this->mahasiswa->nim;
    $this->jurusan = $this->mahasiswa->jurusan;
    $this->program_studi = $this->mahasiswa->program_studi;
    $this->jenis_kelamin = $this->mahasiswa->jenis_kelamin;
    $this->alamat = $this->mahasiswa->alamat;

    $this->isUpdatePersonalData = false;
};

// Preference Functions
$updatePreference = function () {
    $this->isUpdatePreference = true;
};

$saveNewPreference = function () {
    try {
        // Cari ID berdasarkan nama untuk bidang industri
        $bidangIndustri = BidangIndustri::where('nama', $this->bidang_industri)->first();
        if (!$bidangIndustri) {
            throw new \Exception('Bidang Industri tidak ditemukan');
        }

        // Cari ID berdasarkan kategori_lokasi untuk lokasi magang
        $lokasiMagang = LokasiMagang::where('kategori_lokasi', $this->lokasi_magang)->first();
        if (!$lokasiMagang) {
            throw new \Exception('Lokasi Magang tidak ditemukan');
        }

        // Cari ID berdasarkan nama untuk pekerjaan
        $pekerjaan = Pekerjaan::where('nama', $this->pekerjaan)->first();
        if (!$pekerjaan) {
            throw new \Exception('Pekerjaan tidak ditemukan');
        }

        // Update data dengan ID yang sesuai
        $this->mahasiswa->kriteriaBidangIndustri()->update(['bidang_industri_id' => $bidangIndustri->id]);
        $this->mahasiswa->kriteriaJenisMagang()->update(['jenis_magang' => $this->jenis_magang]);
        $this->mahasiswa->kriteriaLokasiMagang()->update(['lokasi_magang_id' => $lokasiMagang->id]);
        $this->mahasiswa->kriteriaPekerjaan()->update(['pekerjaan_id' => $pekerjaan->id]);
        $this->mahasiswa->kriteriaOpenRemote()->update(['open_remote' => $this->open_remote]);

        $this->mahasiswa->touch();

        event(new MahasiswaPreferenceUpdated($this->mahasiswa));

        $this->showModal('success', 'Preferensi Magang Berhasil Diperbarui', 'Preferensi magang Anda telah berhasil diperbarui dan sistem rekomendasi telah dijalankan ulang.');
        $this->isUpdatePreference = false;
    } catch (\Exception $e) {
        $this->showModal('error', 'Gagal Memperbarui Preferensi', 'Error: ' . $e->getMessage());
    }
};

$cancelUpdatePreference = function () {
    // Reset ke nilai asli menggunakan nama
    $this->bidang_industri = $this->mahasiswa->kriteriaBidangIndustri->bidangIndustri->nama;
    $this->jenis_magang = $this->mahasiswa->kriteriaJenisMagang->jenis_magang;
        $this->lokasi_magang = $this->mahasiswa->kriteriaLokasiMagang->lokasiMagang->kategori_lokasi;
    $this->pekerjaan = $this->mahasiswa->kriteriaPekerjaan->pekerjaan->nama;
    $this->open_remote = $this->mahasiswa->kriteriaOpenRemote->open_remote;

    $this->isUpdatePreference = false;
};

// Criteria Ranking Functions
$updateRanking = function () {
    $this->isUpdateRanking = true;
    $this->temp_rankings = $this->criteria_rankings;
};

$moveUp = function ($index) {
    if ($index > 0) {
        $temp = $this->temp_rankings[$index];
        $this->temp_rankings[$index] = $this->temp_rankings[$index - 1];
        $this->temp_rankings[$index - 1] = $temp;

        // Update ranks
        foreach ($this->temp_rankings as $key => $criteria) {
            $this->temp_rankings[$key]['rank'] = $key + 1;
        }
    }
};

$moveDown = function ($index) {
    if ($index < count($this->temp_rankings) - 1) {
        $temp = $this->temp_rankings[$index];
        $this->temp_rankings[$index] = $this->temp_rankings[$index + 1];
        $this->temp_rankings[$index + 1] = $temp;

        // Update ranks
        foreach ($this->temp_rankings as $key => $criteria) {
            $this->temp_rankings[$key]['rank'] = $key + 1;
        }
    }
};

$saveRanking = function () {
    try {
        foreach ($this->temp_rankings as $index => $criteria) {
            $rank = $index + 1;

            switch ($criteria['key']) {
                case 'pekerjaan':
                    $this->mahasiswa->kriteriaPekerjaan()->update([
                        'rank' => $rank,
                        'bobot' => ROC::getWeight($rank, config('recommendation-system.roc.total_criteria')),
                    ]);
                    break;
                case 'bidang_industri':
                    $this->mahasiswa->kriteriaBidangIndustri()->update([
                        'rank' => $rank,
                        'bobot' => ROC::getWeight($rank, config('recommendation-system.roc.total_criteria')),
                    ]);
                    break;
                case 'lokasi_magang':
                    $this->mahasiswa->kriteriaLokasiMagang()->update([
                        'rank' => $rank,
                        'bobot' => ROC::getWeight($rank, config('recommendation-system.roc.total_criteria')),
                    ]);
                    break;
                case 'jenis_magang':
                    $this->mahasiswa->kriteriaJenisMagang()->update([
                        'rank' => $rank,
                        'bobot' => ROC::getWeight($rank, config('recommendation-system.roc.total_criteria')),
                    ]);
                    break;
                case 'open_remote':
                    $this->mahasiswa->kriteriaOpenRemote()->update([
                        'rank' => $rank,
                        'bobot' => ROC::getWeight($rank, config('recommendation-system.roc.total_criteria')),
                    ]);
                    break;
            }
        }

        $this->mahasiswa->touch();

        $this->loadCriteriaRankings();

        $this->showModal('success', 'Prioritas Kriteria Berhasil Diperbarui', 'Urutan prioritas kriteria Anda telah berhasil diperbarui dan sistem rekomendasi telah dijalankan ulang.');
        $this->isUpdateRanking = false;
    } catch (\Exception $e) {
        $this->showModal('error', 'Gagal Memperbarui Prioritas', 'Terjadi kesalahan saat memperbarui prioritas kriteria. Silakan coba lagi.');
    }
};

$cancelUpdateRanking = function () {
    $this->temp_rankings = [];
    $this->isUpdateRanking = false;
};

// Password Functions
$updatePassword = function () {
    $this->isUpdatePassword = true;
    $this->current_password = '';
    $this->new_password = '';
    $this->new_password_confirmation = '';
};

$saveNewPassword = function () {
    try {
        $this->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:8|confirmed',
            'new_password_confirmation' => 'required',
        ]);

        // Verify current password
        if (!Hash::check($this->current_password, $this->mahasiswa->password)) {
            $this->showModal('error', 'Password Lama Salah', 'Password lama yang Anda masukkan tidak sesuai.');
            return;
        }

        // Update password
        $this->mahasiswa->update([
            'password' => Hash::make($this->new_password),
            'updated_at' => now(),
        ]);

        $this->showModal('success', 'Password Berhasil Diubah', 'Password Anda telah berhasil diubah.');
        $this->isUpdatePassword = false;

        // Clear password fields
        $this->current_password = '';
        $this->new_password = '';
        $this->new_password_confirmation = '';
    } catch (\Illuminate\Validation\ValidationException $e) {
        $this->showModal('error', 'Gagal Mengubah Password', 'Silakan periksa kembali password yang Anda masukkan.');
    } catch (\Exception $e) {
        $this->showModal('error', 'Gagal Mengubah Password', 'Terjadi kesalahan sistem. Silakan coba lagi.');
    }
};

$cancelUpdatePassword = function () {
    $this->current_password = '';
    $this->new_password = '';
    $this->new_password_confirmation = '';
    $this->isUpdatePassword = false;
};

// Tambahkan functions untuk toggle password visibility
$toggleCurrentPassword = function () {
    $this->show_current_password = !$this->show_current_password;
};

$toggleNewPassword = function () {
    $this->show_new_password = !$this->show_new_password;
};

$toggleNewPasswordConfirmation = function () {
    $this->show_new_password_confirmation = !$this->show_new_password_confirmation;
};

$showModal = function ($type, $title, $message) {
    $this->modalType = $type;
    $this->modalTitle = $title;
    $this->modalMessage = $message;
    Flux::modal('response-modal')->show();
};

?>

<div>
    <!-- Enhanced Header with Navigation -->
    <div class="bg-gradient-to-r from-slate-600 via-gray-600 to-slate-700 text-white rounded-2xl">
        <div class="max-w-6xl mx-auto px-6 py-8">
            <!-- Header Content -->
            <div class="flex items-center justify-between mb-8">
                <div class="flex items-center gap-4">
                    <div>
                        <h1 class="text-3xl font-bold">Profil Mahasiswa</h1>
                        <p class="text-slate-200 mt-1">Kelola informasi dan preferensi akun Anda</p>
                    </div>
                </div>
                <div class="hidden md:flex items-center gap-3 text-sm">
                    <div class="flex items-center gap-2 bg-white/10 backdrop-blur-sm px-3 py-2 rounded-lg">
                        <flux:icon.clock class="w-4 h-4" />
                        <span>{{ now()->format('d M Y') }}</span>
                    </div>
                </div>
            </div>

            <!-- Navigation Tabs -->
            <div class="flex flex-wrap gap-2">
                <button wire:click="setActiveSection('personal-data')"
                    class="nav-tab {{ $activeSection === 'personal-data' ? 'active bg-white/20' : 'bg-white/8' }} flex items-center gap-2 px-4 py-3 backdrop-blur-sm rounded-lg hover:bg-white/15 transition-all duration-200 font-medium"
                    id="tab-personal">
                    <flux:icon.user class="w-4 h-4" />
                    <span>Data Personal</span>
                </button>
                <button wire:click="setActiveSection('preferences')"
                    class="nav-tab {{ $activeSection === 'preferences' ? 'active bg-white/20' : 'bg-white/8' }} flex items-center gap-2 px-4 py-3 backdrop-blur-sm rounded-lg hover:bg-white/15 transition-all duration-200 font-medium"
                    id="tab-preferences">
                    <flux:icon.cog-6-tooth class="w-4 h-4" />
                    <span>Preferensi Magang</span>
                </button>
                <button wire:click="setActiveSection('security')"
                    class="nav-tab {{ $activeSection === 'security' ? 'active bg-white/20' : 'bg-white/8' }} flex items-center gap-2 px-4 py-3 backdrop-blur-sm rounded-lg hover:bg-white/15 transition-all duration-200 font-medium"
                    id="tab-security">
                    <flux:icon.lock-closed class="w-4 h-4" />
                    <span>Keamanan</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="bg-gray-50 min-h-screen -mt-4">
        <div class="gap-8 flex flex-col max-w-6xl mx-auto px-6 py-8">

            <!-- Personal Data Section -->
            <section wire:show="activeSection == 'personal-data'" x-transition>
                <div class="card bg-white shadow-lg rounded-xl border border-gray-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-slate-50 to-gray-50 px-6 py-4 border-b border-gray-100">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-slate-100 rounded-lg">
                                <flux:icon.user class="w-5 h-5 text-slate-600" />
                            </div>
                            <div>
                                <h2 class="text-xl font-bold text-gray-800">Data Personal</h2>
                                <p class="text-gray-600 text-sm">Informasi pribadi dan akademik</p>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-6">
                        <div class="flex items-center gap-4 mb-6">
                            <flux:avatar circle src="https://unavatar.io/x/{{ $nama }}"
                                class="w-20 h-20 ring-4 ring-slate-100" />
                            <div>
                                <h3 class="text-2xl font-bold text-gray-800">{{ $nama }}</h3>
                                <p class="text-gray-600">{{ $nim }} • {{ $program_studi }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @if (!$isUpdatePersonalData)
                                <flux:input readonly value="{{ $nama }}" type="text" label="Nama Lengkap"
                                    class="bg-gray-50" />
                                <flux:input readonly value="{{ $nim }}" type="text" label="NIM"
                                    class="bg-gray-50" />
                                <flux:input readonly value="{{ $jurusan }}" type="text" label="Jurusan"
                                    class="bg-gray-50" />
                                <flux:input readonly value="{{ $program_studi }}" type="text" label="Program Studi"
                                    class="bg-gray-50" />
                                <flux:input readonly value="{{ $jenis_kelamin == 'L' ? 'Laki-laki' : 'Perempuan' }}"
                                    type="text" label="Jenis Kelamin" class="bg-gray-50" />
                                <flux:input readonly value="{{ $alamat }}" type="text" label="Alamat"
                                    class="bg-gray-50" />
                            @else
                                <flux:input wire:model="nama" type="text" label="Nama Lengkap" />
                                <flux:input wire:model="nim" type="text" label="NIM" />
                                <flux:input wire:model="jurusan" type="text" label="Jurusan" />
                                <flux:select wire:model="program_studi" label="Program Studi"
                                    placeholder="Pilih program studi">
                                    @foreach ($program_studi_options as $value => $label)
                                        <flux:select.option value="{{ $value }}">{{ $label }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:select wire:model="jenis_kelamin" label="Jenis Kelamin"
                                    placeholder="Pilih jenis kelamin">
                                    @foreach ($jenis_kelamin_options as $value => $label)
                                        <flux:select.option value="{{ $value }}">{{ $label }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:input wire:model="alamat" type="text" label="Alamat" />
                            @endif
                        </div>

                        @if ($mahasiswa->updated_at)
                            <div class="mt-4 text-sm text-gray-500 flex items-center gap-2">
                                <flux:icon.clock class="w-4 h-4" />
                                <span>Terakhir diperbarui: {{ $mahasiswa->updated_at->format('d M Y, H:i:s') }}</span>
                            </div>
                        @endif
                    </div>

                    <div class="card-actions flex justify-end p-6 bg-gray-50 rounded-b-xl">
                        <div wire:show="!isUpdatePersonalData">
                            <flux:button wire:click="updatePersonalData"
                                class="bg-slate-600! text-white! hover:bg-slate-700!" icon="pencil">
                                Edit Data Personal
                            </flux:button>
                        </div>
                        <div wire:show="isUpdatePersonalData" class="flex gap-2">
                            <flux:button wire:click="cancelUpdatePersonalData"
                                class="bg-gray-500! text-white! hover:bg-gray-600!" icon="x">
                                Batalkan
                            </flux:button>
                            <flux:button wire:click="savePersonalData"
                                class="bg-green-600! text-white! hover:bg-green-700!" icon="check">
                                Simpan Data Personal
                            </flux:button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Preferences Section -->
            <section wire:show="activeSection == 'preferences'" x-transition>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Preferences Card (Left) -->
                    <div class="order-1 flex flex-col h-full">
                        <div
                            class="card bg-white shadow-lg rounded-xl border border-gray-100 overflow-hidden flex flex-col h-full">
                            <div class="bg-gradient-to-r from-slate-50 to-gray-50 px-6 py-4 border-b border-gray-100">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 bg-slate-100 rounded-lg">
                                        <flux:icon.cog-6-tooth class="w-5 h-5 text-slate-600" />
                                    </div>
                                    <div>
                                        <h2 class="text-xl font-bold text-gray-800">Preferensi Magang</h2>
                                        <p class="text-gray-600 text-sm">Atur kriteria magang yang Anda inginkan</p>
                                    </div>
                                </div>
                            </div>

                            <div class="card-body p-6 flex-1">
                                <div class="grid grid-cols-1 gap-4">
                                    @if (!$isUpdatePreference)
                                        <div class="space-y-3">
                                            <div>
                                                <flux:label>Pekerjaan</flux:label>
                                                <flux:input readonly value="{{ $pekerjaan }}" type="text"
                                                    class="bg-gray-50" />
                                            </div>
                                            <flux:input readonly value="{{ $bidang_industri }}" type="text"
                                                label="Bidang Industri" class="bg-gray-50" />
                                            <flux:input readonly value="{{ $lokasi_magang }}" type="text"
                                                label="Lokasi Magang" class="bg-gray-50" />
                                            <flux:input readonly
                                                value="{{ $jenis_magang == 'berbayar' ? 'Magang Berbayar (Paid)' : ($jenis_magang == 'tidak berbayar' ? 'Magang Tidak Berbayar (Unpaid)' : 'Semua') }}"
                                                type="text" label="Jenis Magang" class="bg-gray-50" />
                                            <flux:input readonly value="{{ ucfirst($open_remote) }}" type="text"
                                                label="Remote Work" class="bg-gray-50" />
                                        </div>
                                    @else
                                        <flux:select wire:model="pekerjaan" label="Pekerjaan"
                                            placeholder="Pilih pekerjaan">
                                            @foreach ($pekerjaan_options as $value => $label)
                                                <flux:select.option value="{{ $value }}">{{ $label }}
                                                </flux:select.option>
                                            @endforeach
                                        </flux:select>

                                        <flux:select wire:model="bidang_industri" label="Bidang Industri"
                                            placeholder="Pilih bidang industri">
                                            @foreach ($bidang_industri_options as $value => $label)
                                                <flux:select.option value="{{ $value }}">{{ $label }}
                                                </flux:select.option>
                                            @endforeach
                                        </flux:select>

                                        <flux:select wire:model="lokasi_magang" label="Lokasi Magang"
                                            placeholder="Pilih lokasi magang">
                                            @foreach ($lokasi_magang_options as $value => $label)
                                                <flux:select.option value="{{ $value }}">{{ $label }}
                                                </flux:select.option>
                                            @endforeach
                                        </flux:select>

                                        <flux:select wire:model="jenis_magang" label="Jenis Magang"
                                            placeholder="Pilih jenis magang">
                                            @foreach ($jenis_magang_options as $value => $label)
                                                <flux:select.option value="{{ $value }}">{{ $label }}
                                                </flux:select.option>
                                            @endforeach
                                        </flux:select>

                                        <flux:select wire:model="open_remote" label="Remote Work"
                                            placeholder="Pilih opsi remote">
                                            @foreach ($open_remote_options as $value => $label)
                                                <flux:select.option value="{{ $value }}">{{ $label }}
                                                </flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    @endif
                                </div>
                            </div>

                            <div class="card-actions flex justify-end p-6 bg-gray-50">
                                @if (!$isUpdatePreference)
                                    <flux:button wire:click="updatePreference"
                                        class="bg-slate-600! text-white! hover:bg-slate-700!" icon="pencil" right>
                                        Edit Preferensi Magang
                                    </flux:button>
                                @else
                                    <div class="flex gap-2">
                                        <flux:button wire:click="cancelUpdatePreference"
                                            class="bg-gray-500! text-white! hover:bg-gray-600!" icon="x">
                                            Batalkan
                                        </flux:button>
                                        <flux:button wire:click="saveNewPreference"
                                            class="bg-green-600! text-white! hover:bg-green-700!" icon="check">
                                            Perbarui Preferensi
                                        </flux:button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    <!-- Criteria Ranking Card (Right) -->
                    <div class="order-2 flex flex-col h-full">
                        <div
                            class="card bg-white shadow-lg rounded-xl border border-gray-100 overflow-hidden flex flex-col h-full">
                            <div
                                class="bg-gradient-to-r from-amber-50 to-orange-50 px-6 py-4 border-b border-gray-100">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 bg-amber-100 rounded-lg">
                                        <flux:icon.bars-3 class="w-5 h-5 text-amber-600" />
                                    </div>
                                    <div>
                                        <h2 class="text-xl font-bold text-gray-800">Prioritas Kriteria</h2>
                                        <p class="text-gray-600 text-sm">Atur urutan prioritas kriteria yang paling
                                            penting
                                            bagi Anda</p>
                                    </div>
                                </div>
                            </div>

                            <div class="card-body p-6 flex-1">
                                @if (!$isUpdateRanking)
                                    <div class="space-y-3">
                                        @foreach ($criteria_rankings as $index => $criteria)
                                            <div
                                                class="flex items-center gap-4 p-4 bg-gradient-to-r from-gray-50 to-gray-100 rounded-lg border border-gray-200">
                                                <div
                                                    class="flex items-center justify-center w-8 h-8 bg-slate-600 text-white font-bold rounded-full text-sm">
                                                    {{ $index + 1 }}
                                                </div>
                                                <div class="p-2 bg-white rounded-lg">
                                                    <flux:icon name="{{ $criteria['icon'] }}"
                                                        class="w-5 h-5 text-gray-600" />
                                                </div>
                                                <div class="flex-1">
                                                    <h3 class="font-semibold text-gray-800">{{ $criteria['label'] }}
                                                    </h3>
                                                    <p class="text-sm text-gray-600">{{ $criteria['description'] }}
                                                    </p>
                                                </div>
                                                <div class="text-right">
                                                    <div class="text-sm font-medium text-gray-800">
                                                        Bobot: {{ number_format($criteria['bobot'] * 100, 1) }}%
                                                    </div>
                                                    <div class="text-xs text-gray-500">Prioritas
                                                        {{ $criteria['rank'] }}</div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="space-y-3">
                                        <div class="bg-slate-50 border border-slate-200 rounded-lg p-4 mb-4">
                                            <div class="flex items-center gap-2">
                                                <flux:icon.information-circle class="w-5 h-5 text-slate-600" />
                                                <p class="text-sm text-slate-800">
                                                    <strong>Cara menggunakan:</strong> Gunakan tombol panah untuk
                                                    mengatur
                                                    urutan
                                                    prioritas. Urutan teratas adalah yang paling penting bagi Anda.
                                                </p>
                                            </div>
                                        </div>

                                        @foreach ($temp_rankings as $index => $criteria)
                                            <div
                                                class="flex items-center gap-4 p-4 bg-white border-2 border-dashed border-gray-300 rounded-lg hover:border-slate-400 transition-colors">
                                                <div
                                                    class="flex items-center justify-center w-8 h-8 bg-slate-600 text-white font-bold rounded-full text-sm">
                                                    {{ $index + 1 }}
                                                </div>
                                                <div class="p-2 bg-gray-100 rounded-lg">
                                                    <flux:icon name="{{ $criteria['icon'] }}"
                                                        class="w-5 h-5 text-gray-600" />
                                                </div>
                                                <div class="flex-1">
                                                    <h3 class="font-semibold text-gray-800">{{ $criteria['label'] }}
                                                    </h3>
                                                    <p class="text-sm text-gray-600">{{ $criteria['description'] }}
                                                    </p>
                                                </div>
                                                <div class="flex flex-col gap-1">
                                                    <button wire:click="moveUp({{ $index }})"
                                                        @if ($index === 0) disabled @endif
                                                        class="p-1 rounded {{ $index === 0 ? 'bg-gray-200 text-gray-400' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }} transition-colors">
                                                        <flux:icon.chevron-up class="w-4 h-4" />
                                                    </button>
                                                    <button wire:click="moveDown({{ $index }})"
                                                        @if ($index === count($temp_rankings) - 1) disabled @endif
                                                        class="p-1 rounded {{ $index === count($temp_rankings) - 1 ? 'bg-gray-200 text-gray-400' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }} transition-colors">
                                                        <flux:icon.chevron-down class="w-4 h-4" />
                                                    </button>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="mt-6 p-4 bg-amber-50 border border-amber-200 rounded-lg">
                                    <div class="flex items-start gap-2">
                                        <flux:icon.light-bulb class="w-5 h-5 text-amber-600 mt-0.5" />
                                        <div class="text-sm text-amber-800">
                                            <p class="font-semibold mb-1">Tips Mengatur Prioritas:</p>
                                            <ul class="list-disc list-inside space-y-1 text-xs">
                                                <li>Prioritas 1 memiliki bobot tertinggi dalam sistem rekomendasi</li>
                                                <li>Sistem akan mencari magang yang paling sesuai dengan kriteria
                                                    prioritas
                                                    tinggi</li>
                                                <li>Bobot akan dihitung otomatis berdasarkan urutan prioritas</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card-actions flex justify-end p-6 bg-gray-50">
                                <div wire:show="!isUpdateRanking">
                                    <flux:button wire:click="updateRanking"
                                        class="bg-amber-600! text-white! hover:bg-amber-700!" icon="bars-3">
                                        Atur Prioritas Kriteria
                                    </flux:button>
                                </div>
                                <div wire:show="isUpdateRanking" class="flex gap-2">
                                    <flux:button wire:click="cancelUpdateRanking"
                                        class="bg-gray-500! text-white! hover:bg-gray-600!" icon="x">
                                        Batalkan
                                    </flux:button>
                                    <flux:button wire:click="saveRanking"
                                        class="bg-green-600! text-white! hover:bg-green-700!" icon="check">
                                        Simpan Prioritas
                                    </flux:button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Security Section -->
            <section wire:show="activeSection == 'security'" x-transition>
                <div class="card bg-white shadow-lg rounded-xl border border-gray-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-slate-50 to-gray-50 px-6 py-4 border-b border-gray-100">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-slate-100 rounded-lg">
                                <flux:icon.lock-closed class="w-5 h-5 text-slate-600" />
                            </div>
                            <div>
                                <h2 class="text-xl font-bold text-gray-800">Keamanan Akun</h2>
                                <p class="text-gray-600 text-sm">Ubah password untuk menjaga keamanan akun Anda</p>
                            </div>
                        </div>
                    </div>

                    <!-- Security Section dengan Password Visibility Toggle -->
                    <div class="card-body p-6">
                        @if (!$isUpdatePassword)
                            <div class="space-y-4">
                                <flux:input readonly value="••••••••" type="password" label="Password Saat Ini"
                                    class="bg-gray-50" />
                                <div class="flex items-center gap-2 text-sm text-gray-600">
                                    <flux:icon.shield-check class="w-4 h-4 text-green-600" />
                                    <span>Password Anda aman dan terenkripsi</span>
                                </div>
                            </div>
                        @else
                            <div class="space-y-4">
                                <!-- Password Lama dengan Toggle -->
                                <div class="relative">
                                    <flux:input wire:model="current_password"
                                        type="{{ $show_current_password ? 'text' : 'password' }}"
                                        label="Password Lama" placeholder="Masukkan password lama Anda" />
                                    <button type="button" wire:click="toggleCurrentPassword"
                                        class="absolute right-3 top-9 text-gray-400 hover:text-gray-600 transition-colors">
                                        @if ($show_current_password)
                                            <flux:icon.eye-slash class="w-5 h-5" />
                                        @else
                                            <flux:icon.eye class="w-5 h-5" />
                                        @endif
                                    </button>
                                </div>

                                <!-- Password Baru dengan Toggle -->
                                <div class="relative">
                                    <flux:input wire:model="new_password"
                                        type="{{ $show_new_password ? 'text' : 'password' }}" label="Password Baru"
                                        placeholder="Masukkan password baru (minimal 8 karakter)" />
                                    <button type="button" wire:click="toggleNewPassword"
                                        class="absolute right-3 top-9 text-gray-400 hover:text-gray-600 transition-colors">
                                        @if ($show_new_password)
                                            <flux:icon.eye-slash class="w-5 h-5" />
                                        @else
                                            <flux:icon.eye class="w-5 h-5" />
                                        @endif
                                    </button>
                                </div>

                                <!-- Konfirmasi Password Baru dengan Toggle -->
                                <div class="relative">
                                    <flux:input wire:model="new_password_confirmation"
                                        type="{{ $show_new_password_confirmation ? 'text' : 'password' }}"
                                        label="Konfirmasi Password Baru" placeholder="Ulangi password baru" />
                                    <button type="button" wire:click="toggleNewPasswordConfirmation"
                                        class="absolute right-3 top-9 text-gray-400 hover:text-gray-600 transition-colors">
                                        @if ($show_new_password_confirmation)
                                            <flux:icon.eye-slash class="w-5 h-5" />
                                        @else
                                            <flux:icon.eye class="w-5 h-5" />
                                        @endif
                                    </button>
                                </div>

                                <div class="bg-slate-50 border border-slate-200 rounded-lg p-3">
                                    <div class="flex items-start gap-2">
                                        <flux:icon.information-circle class="w-4 h-4 text-slate-600 mt-0.5" />
                                        <div class="text-sm text-slate-800">
                                            <p class="font-semibold">Syarat Password:</p>
                                            <ul class="list-disc list-inside text-xs mt-1">
                                                <li>Minimal 8 karakter</li>
                                                <li>Kombinasi huruf dan angka direkomendasikan</li>
                                                <li>Hindari menggunakan informasi pribadi</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="card-actions flex justify-end p-6 bg-gray-50">
                        <div wire:show="!isUpdatePassword">
                            <flux:button wire:click="updatePassword"
                                class="bg-slate-600! text-white! hover:bg-slate-700!" icon="lock-closed">
                                Ubah Password
                            </flux:button>
                        </div>
                        <div wire:show="isUpdatePassword" class="flex gap-2">
                            <flux:button wire:click="cancelUpdatePassword"
                                class="bg-gray-500! text-white! hover:bg-gray-600!" icon="x">
                                Batalkan
                            </flux:button>
                            <flux:button wire:click="saveNewPassword"
                                class="bg-green-600! text-white! hover:bg-green-700!" icon="check">
                                Simpan Password Baru
                            </flux:button>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <flux:modal name="response-modal" class="min-w-[28rem]">
        <div class="space-y-6">
            <div class="text-center">
                <div
                    class="w-20 h-20 {{ $modalType === 'success' ? 'bg-green-100' : 'bg-red-100' }} rounded-full flex items-center justify-center mx-auto mb-4">
                    @if ($modalType === 'success')
                        <flux:icon.check-circle class="w-10 h-10 text-green-600" />
                    @else
                        <flux:icon.x-circle class="w-10 h-10 text-red-600" />
                    @endif
                </div>
                <flux:heading size="lg" class="text-gray-800">{{ $modalTitle }}</flux:heading>
                <flux:text class="mt-3 text-gray-600 leading-relaxed">
                    <p>{{ $modalMessage }}</p>
                </flux:text>
            </div>
            <div class="flex justify-center">
                <flux:modal.close>
                    <flux:button type="submit" variant="primary"
                        class="{{ $modalType === 'success' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700' }} px-8 py-2 text-white rounded-lg font-medium">
                        Oke, Mengerti
                    </flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
