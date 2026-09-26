<?php

use Flux\Flux;
use function Livewire\Volt\{state, mount};
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

state([
    'dosen',

    // Personal data fields
    'nama',
    'nidn',
    'jenis_kelamin',
    'foto',

    // Password fields
    'current_password',
    'new_password',
    'new_password_confirmation',

    // Edit states
    'isUpdatePersonalData' => false,
    'isUpdatePassword' => false,

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

    // Photo states
    'currentPhoto',
    'hasPhoto',
    'showDeleteModal' => false,
    'deleteLoading' => false,

    // Dropdown options
    'jenis_kelamin_options' => [
        'L' => 'Laki-laki',
        'P' => 'Perempuan',
    ],
]);

mount(function () {
    $this->dosen = Auth::guard('dosen')->user();

    // Load personal data
    $this->nama = $this->dosen->nama;
    $this->nidn = $this->dosen->nidn;
    $this->jenis_kelamin = $this->dosen->jenis_kelamin;
    $this->currentPhoto = $this->dosen->foto ? asset('foto_dosen/' . $this->dosen->foto) : asset('img/user/lecturer-man.png');
    $this->hasPhoto = (bool) $this->dosen->foto;
});

// Section navigation function
$setActiveSection = function ($section) {
    $this->activeSection = $section;
};

// Personal Data Functions
$updatePersonalData = function () {
    $this->isUpdatePersonalData = true;
};

$savePersonalData = function () {
    try {
        $this->validate([
            'nama' => 'required|string|max:255',
            'nidn' => 'required|string|max:20|unique:dosen,nidn,' . $this->dosen->id,
            'jenis_kelamin' => 'required|in:L,P',
            'foto' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $updateData = [
            'nama' => $this->nama,
            'nidn' => $this->nidn,
            'jenis_kelamin' => $this->jenis_kelamin,
            'updated_at' => now(),
        ];

        // Handle photo upload
        if ($this->foto) {
            // Delete old photo if exists
            if ($this->dosen->foto && Storage::disk('public')->exists('foto_dosen/' . $this->dosen->foto)) {
                Storage::disk('public')->delete('foto_dosen/' . $this->dosen->foto);
            }

            // Store new photo
            $filename = time() . '_' . $this->foto->getClientOriginalName();
            $this->foto->storeAs('foto_dosen', $filename, 'public');
            $updateData['foto'] = $filename;

            $this->hasPhoto = true;
            $this->currentPhoto = asset('storage/foto_dosen/' . $filename);
        }

        $this->dosen->update($updateData);

        // Clear form fields
        $this->reset(['foto']);

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
    $this->nama = $this->dosen->nama;
    $this->nidn = $this->dosen->nidn;
    $this->jenis_kelamin = $this->dosen->jenis_kelamin;
    $this->reset(['foto']);

    $this->isUpdatePersonalData = false;
};

// Photo Functions
$deletePhoto = function () {
    $this->deleteLoading = true;

    try {
        if ($this->dosen->foto) {
            // Delete photo from storage
            if (Storage::disk('public')->exists('foto_dosen/' . $this->dosen->foto)) {
                Storage::disk('public')->delete('foto_dosen/' . $this->dosen->foto);
            }

            // Update user record
            $this->dosen->update(['foto' => null]);

            // Update component state
            $this->hasPhoto = false;
            $this->currentPhoto = asset('img/user/lecturer-man.png');
            $this->showDeleteModal = false;

            $this->showModal('success', 'Foto Berhasil Dihapus', 'Foto profil berhasil dihapus.');
        }
    } catch (\Exception $e) {
        $this->showModal('error', 'Gagal Menghapus Foto', 'Terjadi kesalahan saat menghapus foto. Silakan coba lagi.');
    } finally {
        $this->deleteLoading = false;
    }
};

$openDeleteModal = function () {
    $this->showDeleteModal = true;
};

$closeDeleteModal = function () {
    $this->showDeleteModal = false;
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
            'new_password' => ['required', Password::min(8)->mixedCase()->numbers(), 'confirmed'],
            'new_password_confirmation' => 'required',
        ]);

        // Verify current password
        if (!Hash::check($this->current_password, $this->dosen->password)) {
            $this->showModal('error', 'Password Lama Salah', 'Password lama yang Anda masukkan tidak sesuai.');
            return;
        }

        // Update password
        $this->dosen->forceFill([
            'password' => Hash::make($this->new_password),
            'updated_at' => now(),
        ])->save();

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

// Password visibility toggle functions
$toggleCurrentPassword = function () {
    $this->show_current_password = !$this->show_current_password;
};

$toggleNewPassword = function () {
    $this->show_new_password = !$this->show_new_password;
};

$toggleNewPasswordConfirmation = function () {
    $this->show_new_password_confirmation = !$this->show_new_password_confirmation;
};

// Modal Function
$showModal = function ($type, $title, $message) {
    $this->modalType = $type;
    $this->modalTitle = $title;
    $this->modalMessage = $message;
    Flux::modal('response-modal')->show();
};

?>

<div>
    <!-- Response Modal -->
    <flux:modal name="response-modal" class="md:w-96">
        <div class="space-y-6">
            <div
                class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full
                {{ $modalType === 'success' ? 'bg-green-100' : 'bg-red-100' }}">
                @if ($modalType === 'success')
                    <flux:icon.check class="w-6 h-6 text-green-600" />
                @else
                    <flux:icon.x-mark class="w-6 h-6 text-red-600" />
                @endif
            </div>
            <div class="text-center">
                <flux:heading size="lg">{{ $modalTitle }}</flux:heading>
                <flux:text class="mt-2 text-gray-600">{{ $modalMessage }}</flux:text>
            </div>
            <div class="flex justify-center">
                <flux:button x-on:click="$flux.modal('response-modal').close()" variant="primary">
                    OK
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Delete Photo Confirmation Modal -->
    <flux:modal wire:model="showDeleteModal" class="md:w-96">
        <div class="space-y-6">
            <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full bg-red-100">
                <flux:icon.trash class="w-6 h-6 text-red-600" />
            </div>
            <div class="text-center">
                <flux:heading size="lg">Hapus Foto Profil</flux:heading>
                <flux:text class="mt-2 text-gray-600">
                    Apakah Anda yakin ingin menghapus foto profil? Tindakan ini tidak dapat dibatalkan.
                </flux:text>
            </div>
            <div class="flex space-x-3">
                <flux:button wire:click="closeDeleteModal" variant="ghost" class="flex-1">
                    Batal
                </flux:button>
                <flux:button wire:click="deletePhoto" variant="danger" class="flex-1" :loading="$deleteLoading">
                    Hapus Foto
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Enhanced Header with Navigation -->
    <div class="bg-gradient-to-r from-slate-600 via-gray-600 to-slate-700 text-white rounded-2xl">
        <div class="max-w-6xl mx-auto px-6 py-8">
            <!-- Header Content -->
            <div class="flex items-center justify-between mb-8">
                <div class="flex items-center gap-4">
                    <div>
                        <h1 class="text-3xl font-bold">Profil Dosen</h1>
                        <p class="text-slate-200 mt-1">Kelola informasi dan keamanan akun Anda</p>
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
            <div class="{{ $activeSection === 'personal-data' ? '' : 'hidden' }}">
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
                        <!-- Profile Photo Section -->
                        <div class="flex flex-col md:flex-row items-center gap-6 mb-6">
                            <!-- Avatar -->
                            <div class="relative">
                                <div
                                    class="w-24 h-24 rounded-full ring-4 ring-slate-100 shadow-lg overflow-hidden bg-gray-200">
                                    @if ($foto)
                                        <img src="{{ $foto->temporaryUrl() }}" alt="Profile Photo"
                                            class="w-full h-full object-cover" />
                                    @elseif ($currentPhoto)
                                        <img src="{{ $currentPhoto }}" alt="Profile Photo"
                                            class="w-full h-full object-cover" />
                                    @else
                                        <img src="{{ asset('img/user/lecturer-man.png') }}" alt="Default Profile Photo"
                                            class="w-full h-full object-cover" />
                                    @endif
                                </div>
                                <div
                                    class="absolute bottom-1 right-1 w-5 h-5 bg-green-400 border-2 border-white rounded-full">
                                </div>
                            </div>

                            <!-- User Info -->
                            <div class="text-center md:text-left">
                                <h3 class="text-2xl font-bold text-gray-800">{{ $nama }}</h3>
                                <p class="text-gray-600">NIDN: {{ $nidn }}</p>
                                <div class="flex items-center justify-center md:justify-start mt-2">
                                    <flux:badge color="green" size="sm">
                                        <flux:icon.check-circle class="w-3 h-3 mr-1" />
                                        Aktif
                                    </flux:badge>
                                </div>
                            </div>

                            <!-- Photo Upload Controls (only when editing) -->
                            @if ($isUpdatePersonalData)
                                <div class="flex flex-col items-center space-y-3 ml-auto">
                                    <div class="flex items-center space-x-3">
                                        <flux:button type="button" variant="outline" size="sm" icon="camera"
                                            onclick="document.getElementById('foto-input').click()">
                                            Ganti Foto
                                        </flux:button>

                                        <input type="file" id="foto-input" wire:model="foto"
                                            accept="image/jpeg,image/png,image/jpg" class="hidden">

                                        @if ($hasPhoto || $foto)
                                            <flux:button type="button" wire:click="openDeleteModal" variant="danger"
                                                size="sm" icon="trash">
                                                Hapus Foto
                                            </flux:button>
                                        @endif
                                    </div>

                                    <div class="text-center">
                                        <p class="text-xs text-gray-500">Format: JPG, PNG. Maksimal 2MB.</p>
                                        @error('foto')
                                            <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                                        @enderror

                                        <div wire:loading wire:target="foto" class="text-sm text-blue-600 mt-1">
                                            <flux:icon.arrow-path class="w-4 h-4 animate-spin inline mr-1" />
                                            Mengunggah foto...
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- Form Fields -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @if (!$isUpdatePersonalData)
                                <flux:input readonly value="{{ $nama }}" type="text" label="Nama Lengkap"
                                    class="bg-gray-50" />
                                <flux:input readonly value="{{ $nidn }}" type="text" label="NIDN"
                                    class="bg-gray-50" />
                                <flux:input readonly value="{{ $jenis_kelamin == 'L' ? 'Laki-laki' : 'Perempuan' }}"
                                    type="text" label="Jenis Kelamin" class="bg-gray-50" />
                            @else
                                <flux:input wire:model="nama" type="text" label="Nama Lengkap" />
                                @error('nama')
                                    <flux:error>{{ $message }}</flux:error>
                                @enderror

                                <flux:input wire:model="nidn" type="text" label="NIDN" />
                                @error('nidn')
                                    <flux:error>{{ $message }}</flux:error>
                                @enderror

                                <flux:select wire:model="jenis_kelamin" label="Jenis Kelamin"
                                    placeholder="Pilih jenis kelamin">
                                    @foreach ($jenis_kelamin_options as $value => $label)
                                        <flux:select.option value="{{ $value }}">{{ $label }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                                @error('jenis_kelamin')
                                    <flux:error>{{ $message }}</flux:error>
                                @enderror
                            @endif
                        </div>

                        @if ($dosen->updated_at)
                            <div class="mt-4 text-sm text-gray-500 flex items-center gap-2">
                                <flux:icon.clock class="w-4 h-4" />
                                <span>Terakhir diperbarui: {{ $dosen->updated_at->format('d M Y, H:i:s') }}</span>
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
            </div>

            <!-- Security Section -->
            <div class="{{ $activeSection === 'security' ? '' : 'hidden' }}">
                <div class="card bg-white shadow-lg rounded-xl border border-gray-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-slate-50 to-gray-50 px-6 py-4 border-b border-gray-100">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-slate-100 rounded-lg">
                                <flux:icon.lock-closed class="w-5 h-5 text-slate-600" />
                            </div>
                            <div>
                                <h2 class="text-xl font-bold text-gray-800">Keamanan Akun</h2>
                                <p class="text-gray-600 text-sm">Kelola password dan keamanan akun</p>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-6">
                        @if (!$isUpdatePassword)
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                <div class="flex items-center gap-3">
                                    <flux:icon.lock-closed class="w-5 h-5 text-gray-600" />
                                    <div>
                                        <p class="font-medium text-gray-800">Password</p>
                                        <p class="text-sm text-gray-600">Terakhir diubah:
                                            {{ $dosen->updated_at ? $dosen->updated_at->format('d M Y') : 'Belum pernah diubah' }}
                                        </p>
                                    </div>
                                </div>
                                <flux:button wire:click="updatePassword" variant="outline" size="sm"
                                    icon="pencil">
                                    Ubah Password
                                </flux:button>
                            </div>
                        @else
                            <div class="space-y-4">
                                <!-- Current Password -->
                                <div class="space-y-1">
                                    <div class="relative">
                                        <flux:input wire:model="current_password"
                                            type="{{ $show_current_password ? 'text' : 'password' }}"
                                            label="Password Lama" placeholder="Masukkan password lama" required />
                                        <button type="button" wire:click="toggleCurrentPassword"
                                            class="absolute right-3 top-8 text-gray-500 hover:text-gray-700">
                                            @if ($show_current_password)
                                                <flux:icon.eye-slash class="w-5 h-5" />
                                            @else
                                                <flux:icon.eye class="w-5 h-5" />
                                            @endif
                                        </button>
                                    </div>
                                    @error('current_password')
                                        <flux:error>{{ $message }}</flux:error>
                                    @enderror
                                </div>

                                <!-- New Password -->
                                <div class="space-y-1">
                                    <div class="relative">
                                        <flux:input wire:model="new_password"
                                            type="{{ $show_new_password ? 'text' : 'password' }}"
                                            label="Password Baru" placeholder="Masukkan password baru" required />
                                        <button type="button" wire:click="toggleNewPassword"
                                            class="absolute right-3 top-8 text-gray-500 hover:text-gray-700">
                                            @if ($show_new_password)
                                                <flux:icon.eye-slash class="w-5 h-5" />
                                            @else
                                                <flux:icon.eye class="w-5 h-5" />
                                            @endif
                                        </button>
                                    </div>
                                    @error('new_password')
                                        <flux:error>{{ $message }}</flux:error>
                                    @enderror
                                    <p class="text-xs text-gray-500">Minimal 8 karakter dengan huruf besar, kecil, dan
                                        angka</p>
                                </div>

                                <!-- Confirm New Password -->
                                <div class="space-y-1">
                                    <div class="relative">
                                        <flux:input wire:model="new_password_confirmation"
                                            type="{{ $show_new_password_confirmation ? 'text' : 'password' }}"
                                            label="Konfirmasi Password Baru" placeholder="Ulangi password baru"
                                            required />
                                        <button type="button" wire:click="toggleNewPasswordConfirmation"
                                            class="absolute right-3 top-8 text-gray-500 hover:text-gray-700">
                                            @if ($show_new_password_confirmation)
                                                <flux:icon.eye-slash class="w-5 h-5" />
                                            @else
                                                <flux:icon.eye class="w-5 h-5" />
                                            @endif
                                        </button>
                                    </div>
                                    @error('new_password_confirmation')
                                        <flux:error>{{ $message }}</flux:error>
                                    @enderror
                                </div>
                            </div>
                        @endif
                    </div>

                    @if ($isUpdatePassword)
                        <div class="card-actions flex justify-end p-6 bg-gray-50 rounded-b-xl">
                            <div class="flex gap-2">
                                <flux:button wire:click="cancelUpdatePassword"
                                    class="bg-gray-500! text-white! hover:bg-gray-600!" icon="x">
                                    Batalkan
                                </flux:button>
                                <flux:button wire:click="saveNewPassword"
                                    class="bg-green-600! text-white! hover:bg-green-700!" icon="check">
                                    Simpan Password
                                </flux:button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</div>
