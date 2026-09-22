<?php

use function Livewire\Volt\{layout, rules, state};
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use App\Models\Mahasiswa;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

layout('components.layouts.guest.with-navbar');

state([
    'nim',
    'nama',
    'email',
    'jurusan' => 'Teknologi Informasi',
    'program_studi',
    'angkatan',
    'jenis_kelamin',
    'tanggal_lahir',
    'alamat',
    'password',
    'password_confirmation'
]);

rules([
    'nim' => ['required', 'string', 'min:10', 'regex:/^[0-9]+$/'],
    'nama' => ['required', 'string', 'max:255'],
    'email' => ['required', 'string', 'email', 'max:255', 'unique:mahasiswa'],
    'program_studi' => ['required', 'string'],
    'angkatan' => ['required', 'numeric', 'min:1', 'max:100'],
    'jenis_kelamin' => ['required', 'in:L,P'],
    'tanggal_lahir' => ['required', 'date', 'before:today'],
    'alamat' => ['required', 'string', 'max:500'],
    'password' => ['required', 'string', 'confirmed', Password::default()],
])->messages([
    'nim.required' => 'NIM tidak boleh kosong.',
    'nim.min' => 'NIM harus terdiri dari minimal 10 karakter.',
    'nim.regex' => 'NIM hanya boleh terdiri dari angka.',
    'nama.required' => 'Nama lengkap tidak boleh kosong.',
    'email.required' => 'Email tidak boleh kosong.',
    'email.email' => 'Format email tidak valid.',
    'email.unique' => 'Email sudah terdaftar.',
    'program_studi.required' => 'Program studi harus dipilih.',
    'angkatan.required' => 'Angkatan tidak boleh kosong.',
    'angkatan.numeric' => 'Angkatan harus berupa angka.',
    'jenis_kelamin.required' => 'Jenis kelamin harus dipilih.',
    'tanggal_lahir.required' => 'Tanggal lahir tidak boleh kosong.',
    'tanggal_lahir.before' => 'Tanggal lahir harus sebelum hari ini.',
    'alamat.required' => 'Alamat tidak boleh kosong.',
    'password.required' => 'Password tidak boleh kosong.',
    'password.min' => 'Password harus terdiri dari minimal 8 karakter.',
    'password.confirmed' => 'Konfirmasi password tidak cocok.',
]);

$register = function (): void {
    try {
        $validated = $this->validate();

        $mahasiswa = Mahasiswa::forceCreate([
            'nama' => $this->nama,
            'nim' => $this->nim,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'jurusan' => $this->jurusan,
            'program_studi' => $this->program_studi,
            'angkatan' => $this->angkatan,
            'jenis_kelamin' => $this->jenis_kelamin,
            'tanggal_lahir' => $this->tanggal_lahir,
            'alamat' => $this->alamat,
        ]);

        event(new Registered($mahasiswa));

        Auth::guard('mahasiswa')->login($mahasiswa);

        session()->flash('success', 'Registrasi berhasil! Selamat datang.');

        $this->redirectIntended(route('mahasiswa.persiapan-preferensi', absolute: false), navigate: true);
    } catch (ValidationException $e) {
        throw $e;
    } catch (\Exception $e) {
        Log::error('Registration failed: ' . $e->getMessage(), [
            'email' => $this->email,
            'nim' => $this->nim,
            'exception' => $e
        ]);

        session()->flash('error', 'Terjadi kesalahan saat mendaftar. Silakan coba lagi.');
    }
};

?>

<div class="min-h-screen bg-gradient-to-br sm:py-8 px-4">
    <div class="max-w-4xl mx-auto">
        <div class="text-center mb-6 sm:mb-8">
            <div
                class="inline-flex items-center justify-center w-12 h-12 sm:w-16 sm:h-16 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-full mb-4 shadow-lg">
                <svg class="w-6 h-6 sm:w-8 sm:h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253">
                    </path>
                </svg>
            </div>
            <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-gray-900 mb-2">Daftar Akun Mahasiswa</h1>
            <p class="text-gray-600 text-base sm:text-lg px-4">Bergabunglah dengan platform sistem magang kami
            </p>
        </div>

        <!-- Progress Steps -->
        <div class="bg-white rounded-lg shadow-sm p-3 sm:p-4 mb-4 sm:mb-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <div
                        class="w-6 h-6 sm:w-8 sm:h-8 bg-blue-600 text-white rounded-full flex items-center justify-center text-xs sm:text-sm font-semibold">
                        1</div>
                    <span class="text-xs sm:text-sm font-medium text-blue-600">Data Pribadi</span>
                </div>
                <div class="flex-1 h-1 bg-gray-200 mx-2 sm:mx-4 rounded">
                    <div class="h-1 bg-blue-600 rounded" style="width: 100%"></div>
                </div>
                <div class="flex items-center space-x-2">
                    <div
                        class="w-6 h-6 sm:w-8 sm:h-8 bg-gray-200 text-gray-500 rounded-full flex items-center justify-center text-xs sm:text-sm font-semibold">
                        2</div>
                    <span class="text-xs sm:text-sm text-gray-500">Preferensi</span>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-xl overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 p-4 sm:p-6">
                <h2 class="text-xl sm:text-2xl font-bold text-white mb-2">Informasi Pribadi</h2>
                <p class="text-blue-100 text-sm sm:text-base">Lengkapi data diri Anda dengan benar</p>
            </div>

            <div class="p-4 sm:p-6 lg:p-8">
                <form wire:submit="register" class="space-y-4 sm:space-y-6">
                    <!-- Section 1: Identitas Mahasiswa -->
                    <div class="bg-gray-50 rounded-lg p-3 sm:p-4 lg:p-6">
                        <div class="flex items-center mb-3 sm:mb-4">
                            <div
                                class="w-5 h-5 sm:w-6 sm:h-6 bg-blue-600 rounded-full flex items-center justify-center mr-2 sm:mr-3">
                                <svg class="w-2.5 h-2.5 sm:w-3 sm:h-3 text-white" fill="currentColor"
                                    viewBox="0 0 20 20">
                                    <path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"></path>
                                </svg>
                            </div>
                            <h3 class="text-base sm:text-lg font-semibold text-gray-900">Identitas Mahasiswa</h3>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 sm:gap-4 lg:gap-6">
                            <flux:field>
                                <flux:label class="text-sm font-medium text-gray-700 mb-2">
                                    NIM <span class="text-red-500">*</span>
                                </flux:label>
                                <flux:input wire:model="nim" type="text" required placeholder="Contoh: 2022110001"
                                    class="w-full px-3 sm:px-4 py-2.5 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 text-sm sm:text-base" />
                                <flux:error name="nim" class="text-red-500 text-sm mt-1" />
                            </flux:field>

                            <flux:field>
                                <flux:label class="text-sm font-medium text-gray-700 mb-2">
                                    Nama Lengkap <span class="text-red-500">*</span>
                                </flux:label>
                                <flux:input wire:model="nama" type="text" required
                                    placeholder="Nama lengkap sesuai KTM"
                                    class="w-full px-3 sm:px-4 py-2.5 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 text-sm sm:text-base" />
                                <flux:error name="nama" class="text-red-500 text-sm mt-1" />
                            </flux:field>

                            <flux:field>
                                <flux:label class="text-sm font-medium text-gray-700 mb-2">
                                    Email <span class="text-red-500">*</span>
                                </flux:label>
                                <flux:input wire:model="email" type="email" required placeholder="email@contoh.com"
                                    class="w-full px-3 sm:px-4 py-2.5 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 text-sm sm:text-base" />
                                <flux:error name="email" class="text-red-500 text-sm mt-1" />
                            </flux:field>

                            <flux:field>
                                <flux:label class="text-sm font-medium text-gray-700 mb-2">Jurusan</flux:label>
                                <flux:input wire:model="jurusan" type="text" value="Teknologi Informasi" disabled
                                    class="w-full px-3 sm:px-4 py-2.5 sm:py-3 bg-gray-100 border border-gray-300 rounded-lg text-gray-600 text-sm sm:text-base" />
                            </flux:field>
                        </div>
                    </div>

                    <!-- Section 2: Program Studi & Angkatan -->
                    <div class="bg-gray-50 rounded-lg p-3 sm:p-4 lg:p-6">
                        <div class="flex items-center mb-3 sm:mb-4">
                            <div
                                class="w-5 h-5 sm:w-6 sm:h-6 bg-green-600 rounded-full flex items-center justify-center mr-2 sm:mr-3">
                                <svg class="w-2.5 h-2.5 sm:w-3 sm:h-3 text-white" fill="currentColor"
                                    viewBox="0 0 20 20">
                                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h3 class="text-base sm:text-lg font-semibold text-gray-900">Program Studi & Angkatan</h3>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 sm:gap-4 lg:gap-6">
                            <flux:field>
                                <flux:label class="text-sm font-medium text-gray-700 mb-2">
                                    Program Studi <span class="text-red-500">*</span>
                                </flux:label>
                                <flux:select wire:model="program_studi" required>
                                    <flux:select.option value="" default>Pilih program studi
                                    </flux:select.option>
                                    <flux:select.option value="D4 Teknik Informatika" selected>D4 Teknik Informatika
                                    </flux:select.option>
                                    <flux:select.option value="D4 Sistem Informasi Bisnis">D4 Sistem Informasi Bisnis
                                    </flux:select.option>
                                    <flux:select.option value="D2 Pengembangan Piranti Lunak Situs">D2 Pengembangan
                                        Piranti Lunak Situs</flux:select.option>
                                </flux:select>
                                <flux:error name="program_studi" class="text-red-500 text-sm mt-1" />
                            </flux:field>

                            <flux:field>
                                <flux:label class="text-sm font-medium text-gray-700 mb-2">
                                    Angkatan <span class="text-red-500">*</span>
                                </flux:label>
                                <flux:input wire:model="angkatan" type="number" required min="1"
                                    placeholder="contoh: 23"
                                    class="w-full px-3 sm:px-4 py-2.5 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 text-sm sm:text-base" />
                                <flux:error name="angkatan" class="text-red-500 text-sm mt-1" />
                            </flux:field>
                        </div>
                    </div>

                    <!-- Section 3: Data Pribadi -->
                    <div class="bg-gray-50 rounded-lg p-3 sm:p-4 lg:p-6">
                        <div class="flex items-center mb-3 sm:mb-4">
                            <div
                                class="w-5 h-5 sm:w-6 sm:h-6 bg-purple-600 rounded-full flex items-center justify-center mr-2 sm:mr-3">
                                <svg class="w-2.5 h-2.5 sm:w-3 sm:h-3 text-white" fill="currentColor"
                                    viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z"
                                        clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <h3 class="text-base sm:text-lg font-semibold text-gray-900">Data Pribadi</h3>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 lg:gap-6 mb-4 sm:mb-6">
                            <flux:field>
                                <flux:label class="text-sm font-medium text-gray-700 mb-2">
                                    Jenis Kelamin <span class="text-red-500">*</span>
                                </flux:label>
                                <flux:select wire:model="jenis_kelamin">
                                    <flux:select.option value="" default>Pilih jenis kelamin</flux:select.option>
                                    <flux:select.option value="L">Laki-laki</flux:select.option>
                                    <flux:select.option value="P">Perempuan</flux:select.option>
                                </flux:select>
                                <flux:error name="jenis_kelamin" class="text-red-500 text-sm mt-1" />
                            </flux:field>

                            <flux:field>
                                <flux:label class="text-sm font-medium text-gray-700 mb-2">
                                    Tanggal Lahir <span class="text-red-500">*</span>
                                </flux:label>
                                <div class="relative">
                                    <flux:input wire:model="tanggal_lahir" type="date" required
                                        max="{{ date('Y-m-d') }}"
                                        class="w-full px-3 sm:px-4 py-2.5 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 text-sm sm:text-base"
                                        style="color-scheme: light;"
                                        onchange="this.setAttribute('data-date', this.value)" data-date="" />
                                </div>
                                <flux:error name="tanggal_lahir" class="text-red-500 text-sm mt-1" />
                            </flux:field>
                        </div>

                        <flux:field>
                            <flux:label class="text-sm font-medium text-gray-700 mb-2">
                                Alamat Lengkap <span class="text-red-500">*</span>
                            </flux:label>
                            <flux:textarea wire:model="alamat" required
                                placeholder="Masukkan alamat lengkap domisili Anda"
                                class="w-full px-3 sm:px-4 py-2.5 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 min-h-[80px] sm:min-h-[100px] resize-none text-sm sm:text-base" />
                            <flux:error name="alamat" class="text-red-500 text-sm mt-1" />
                        </flux:field>
                    </div>

                    <!-- Section 4: Keamanan Akun -->
                    <div class="bg-gray-50 rounded-lg p-3 sm:p-4 lg:p-6">
                        <div class="flex items-center mb-3 sm:mb-4">
                            <div
                                class="w-5 h-5 sm:w-6 sm:h-6 bg-red-600 rounded-full flex items-center justify-center mr-2 sm:mr-3">
                                <svg class="w-2.5 h-2.5 sm:w-3 sm:h-3 text-white" fill="currentColor"
                                    viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"
                                        clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <h3 class="text-base sm:text-lg font-semibold text-gray-900">Keamanan Akun</h3>
                        </div>

                        <div class="space-y-4 sm:space-y-6">
                            <!-- Password Field dengan Tips -->
                            <flux:field>
                                <flux:label class="text-sm font-medium text-gray-700 mb-2">
                                    Password <span class="text-red-500">*</span>
                                </flux:label>
                                <flux:input wire:model="password" type="password" required
                                    placeholder="Masukkan password" viewable
                                    class="w-full pr-10 sm:pr-10 px-3 sm:px-4 py-2.5 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 text-sm sm:text-base" />
                                <div class="mt-2 p-2 sm:p-3 bg-blue-50 rounded-lg">
                                    <p class="text-xs font-medium text-blue-700 mb-1">Tips password yang kuat:</p>
                                    <ul class="text-xs text-blue-600 space-y-1">
                                        <li>• Minimal 8 karakter</li>
                                        <li>• Kombinasi huruf besar dan kecil</li>
                                        <li>• Sertakan angka dan simbol</li>
                                    </ul>
                                </div>
                                <flux:error name="password" class="text-red-500 text-sm mt-1" />
                            </flux:field>

                            <flux:field>
                                <flux:label class="text-sm font-medium text-gray-700 mb-2">
                                    Konfirmasi Password <span class="text-red-500">*</span>
                                </flux:label>
                                <flux:input wire:model="password_confirmation" type="password" required
                                    placeholder="Ulangi password" viewable
                                    class="w-full pr-10 sm:pr-10 px-3 sm:px-4 py-2.5 sm:py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 text-sm sm:text-base" />
                                <flux:error name="password_confirmation" class="text-red-500 text-sm mt-1" />
                            </flux:field>
                        </div>
                    </div>

                    <div class="flex flex-col space-y-4 pt-4 sm:pt-6 border-t border-gray-200">
                        <flux:button type="submit"
                            class="w-full bg-magnet-sky-teal! hover:bg-magnet-icy-blue! mb-3 border-0 text-white! py-3 text-sm sm:text-base font-semibold rounded-lg shadow-lg transform transition-all duration-200">
                            <span class="flex items-center justify-center">
                                <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-2" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                <span class="text-sm sm:text-base">Daftar Sekarang</span>
                            </span>
                        </flux:button>

                        <p class="text-sm text-gray-600 text-center">
                            Sudah punya akun?
                            <flux:link class="text-magnet-sky-teal hover:text-blue-800 font-medium hover:underline"
                                :href="route('login')" wire:navigate>
                                Masuk di sini
                            </flux:link>
                        </p>
                    </div>
                </form>
            </div>
        </div>

        <footer class="text-center mt-6 sm:mt-8 text-gray-600 px-4">
            <p class="text-xs sm:text-sm">
                Dengan mendaftar, Anda menyetujui
                <a href="#" class="text-blue-600 hover:underline">Syarat & Ketentuan</a>
                dan
                <a href="#" class="text-blue-600 hover:underline">Kebijakan Privasi</a> kami.
            </p>
        </footer>
    </div>

    <x-slot:bottomScript>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var testInput = document.createElement('input');
                testInput.type = 'date';

                if (testInput.type !== 'date') {
                    var dateInputs = document.querySelectorAll('input[type="date"]');
                    dateInputs.forEach(function(input) {
                        input.type = 'text';
                        input.placeholder = 'DD/MM/YYYY';
                        input.setAttribute('pattern', '\\d{2}/\\d{2}/\\d{4}');
                    });
                } else {
                    var dateInputs = document.querySelectorAll('input[type="date"]');
                    dateInputs.forEach(function(input) {
                        input.addEventListener('click', function() {
                            this.showPicker && this.showPicker();
                        });

                        input.addEventListener('touchstart', function() {
                            this.showPicker && this.showPicker();
                        });
                    });
                }
            });
        </script>
    </x-slot:bottomScript>
</div>
