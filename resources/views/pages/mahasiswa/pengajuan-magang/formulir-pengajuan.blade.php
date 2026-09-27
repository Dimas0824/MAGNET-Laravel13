<?php

use function Livewire\Volt\{layout, state, protect};

layout('components.layouts.user.main');

state([
    'mahasiswa' => auth('mahasiswa')->user(),
]);

?>
<div>
    <div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-indigo-50 py-8">
        <x-slot:user>mahasiswa</x-slot:user>

        <div class="max-w-4xl mx-auto px-4">
            <!-- Header Section -->
            <div class="text-center mb-8">
                <div
                    class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-full mb-4 shadow-lg">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Pengajuan Magang</h1>
                <p class="text-gray-600 text-lg">Lengkapi formulir di bawah ini untuk mengajukan magang</p>
            </div>

            <!-- Success/Error Messages -->
            @if (session('success'))
                <div class="mb-6 bg-green-50 border-l-4 border-green-400 p-4 rounded-r-lg shadow-sm">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-green-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd" />
                        </svg>
                        <p class="text-green-700 font-medium">{{ session('success') }}</p>
                    </div>
                </div>
            @endif

            @if (session('error'))
                <div class="mb-6 bg-red-50 border-l-4 border-red-400 p-4 rounded-r-lg shadow-sm">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-red-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                clip-rule="evenodd" />
                        </svg>
                        <p class="text-red-700 font-medium">{{ session('error') }}</p>
                    </div>
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-6 bg-red-50 border-l-4 border-red-400 p-4 rounded-r-lg shadow-sm">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-red-400 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                clip-rule="evenodd" />
                        </svg>
                        <div>
                            <p class="text-red-700 font-medium mb-2">Terdapat kesalahan pada form:</p>
                            <ul class="text-red-600 text-sm space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>• {{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Progress Steps -->
            <div class="mb-8">
                <div class="flex items-center justify-center">
                    <div class="flex items-center space-x-4">
                        <div class="flex items-center">
                            <div
                                class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-semibold shadow-md">
                                1</div>
                            <span class="ml-3 text-sm font-medium text-blue-600">Data Mahasiswa</span>
                        </div>
                        <div class="w-20 h-1 bg-blue-200 rounded-full">
                            <div class="w-full h-1 bg-blue-600 rounded-full"></div>
                        </div>
                        <div class="flex items-center">
                            <div
                                class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-semibold shadow-md">
                                2</div>
                            <span class="ml-3 text-sm font-medium text-blue-600">Upload Dokumen</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Form Card -->
            <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
                <form action="{{ route('mahasiswa.store-pengajuan-magang') }}" method="POST"
                    enctype="multipart/form-data" id="pengajuan-form">
                    @csrf

                    <!-- Student Information Section -->
                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 px-8 py-6 border-b border-gray-100">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                            <svg class="w-6 h-6 text-blue-600 mr-2" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            Data Mahasiswa
                        </h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-4">
                                <flux:input readonly value="{{ $mahasiswa->nama }}" type="text" label="Nama Lengkap"
                                    class="bg-gray-50 border-gray-200" />
                                <flux:input readonly value="{{ $mahasiswa->nim }}" type="text" label="NIM"
                                    class="bg-gray-50 border-gray-200" />
                            </div>
                            <div class="space-y-4">
                                <flux:input readonly value="Teknologi Informasi" type="text" label="Jurusan"
                                    class="bg-gray-50 border-gray-200" />
                                <flux:input readonly value="{{ $mahasiswa->program_studi ?? '-' }}" type="text"
                                    label="Program Studi" class="bg-gray-50 border-gray-200" />
                            </div>
                        </div>
                    </div>

                    <!-- Document Upload Section -->
                    <div class="px-8 py-8">
                        <h2 class="text-xl font-semibold text-gray-800 mb-6 flex items-center">
                            <svg class="w-6 h-6 text-blue-600 mr-2" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                            Upload Dokumen
                        </h2>

                        <div class="space-y-6">
                            <!-- CV Upload -->
                            <div
                                class="bg-gray-50 rounded-xl p-6 border-2 border-dashed border-gray-200 hover:border-blue-300 transition-colors">
                                <flux:field>
                                    <div class="flex items-center mb-3">
                                        <svg class="w-5 h-5 text-red-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                                clip-rule="evenodd" />
                                        </svg>
                                        <flux:label class="text-gray-800 font-medium">CV (Wajib)</flux:label>
                                    </div>
                                    <flux:input type="file" name="cv" accept=".pdf"
                                        description="Format: PDF. Maksimal ukuran file: 2 MB."
                                        onchange="validateFile(this, 'cv')"
                                        class="file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                                    @error('cv')
                                        <flux:error>{{ $message }}</flux:error>
                                    @enderror
                                </flux:field>
                            </div>

                            <!-- Transcript Upload -->
                            <div
                                class="bg-gray-50 rounded-xl p-6 border-2 border-dashed border-gray-200 hover:border-blue-300 transition-colors">
                                <flux:field>
                                    <div class="flex items-center mb-3">
                                        <svg class="w-5 h-5 text-red-500 mr-2" fill="currentColor"
                                            viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                                clip-rule="evenodd" />
                                        </svg>
                                        <flux:label class="text-gray-800 font-medium">Transkrip Nilai (Wajib)
                                        </flux:label>
                                    </div>
                                    <flux:input type="file" name="transkrip_nilai" accept=".pdf"
                                        description="Format: PDF. Maksimal ukuran file: 2 MB."
                                        onchange="validateFile(this, 'transkrip_nilai')"
                                        class="file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                                    @error('transkrip_nilai')
                                        <flux:error>{{ $message }}</flux:error>
                                    @enderror
                                </flux:field>
                            </div>

                            <!-- Portfolio Upload -->
                            <div
                                class="bg-gray-50 rounded-xl p-6 border-2 border-dashed border-gray-200 hover:border-blue-300 transition-colors">
                                <flux:field>
                                    <div class="flex items-center mb-3">
                                        <svg class="w-5 h-5 text-gray-400 mr-2" fill="currentColor"
                                            viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                                clip-rule="evenodd" />
                                        </svg>
                                        <flux:label class="text-gray-800 font-medium">Portofolio (Opsional)
                                        </flux:label>
                                    </div>
                                    <flux:input type="file" name="portfolio" accept=".pdf"
                                        description="Format: PDF. Maksimal ukuran file: 2 MB."
                                        onchange="validateFile(this, 'portfolio')"
                                        class="file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100" />
                                    @error('portfolio')
                                        <flux:error>{{ $message }}</flux:error>
                                    @enderror
                                </flux:field>
                            </div>
                        </div>

                        <!-- File Status Display -->
                        <div id="file-status" class="mt-6 space-y-2 hidden">
                            <h3 class="text-sm font-medium text-gray-700">File yang telah dipilih:</h3>
                            <div id="file-list" class="space-y-1"></div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="bg-gray-50 px-8 py-6 border-t border-gray-100">
                        <div class="flex flex-col sm:flex-row gap-4 justify-end">
                            <flux:button type="button" variant="ghost" onclick="window.history.back();"
                                class="px-6 py-3 border border-gray-300 text-gray-700 hover:bg-gray-100 transition-colors">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 19l-7-7 7-7" />
                                </svg>
                                Kembali
                            </flux:button>

                            <flux:modal.trigger name="confirm-form">
                                <flux:button type="button"
                                    class="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white px-8 py-3 font-semibold shadow-lg hover:shadow-xl transition-all duration-200 transform hover:-translate-y-0.5">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                    </svg>
                                    Kirim Pengajuan
                                </flux:button>
                            </flux:modal.trigger>
                        </div>
                    </div>

                    <!-- Confirmation Modal -->
                    <flux:modal name="confirm-form" class="min-w-[28rem] max-w-md">
                        <div class="space-y-6">
                            <div class="text-center">
                                <div
                                    class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 mb-4">
                                    <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                    </svg>
                                </div>
                                <flux:heading size="lg" class="text-gray-900">Konfirmasi Pengajuan</flux:heading>
                                <div class="mt-4">
                                    <flux:text class="text-gray-600">
                                        Apakah Anda yakin ingin mengirim pengajuan magang? Pastikan semua dokumen telah
                                        terupload dengan benar.
                                    </flux:text>
                                    <div id="modal-file-info"
                                        class="mt-4 p-3 bg-blue-50 rounded-lg text-sm text-blue-800 hidden">
                                        <!-- File info will be populated by JavaScript -->
                                    </div>
                                </div>
                            </div>

                            <div class="flex gap-3 justify-end">
                                <flux:modal.close>
                                    <flux:button variant="ghost" class="px-4 py-2">
                                        Batal
                                    </flux:button>
                                </flux:modal.close>
                                <flux:modal.close>
                                    <flux:button type="submit" form="pengajuan-form"
                                        class="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white px-6 py-2 font-semibold">
                                        Ya, Kirim
                                    </flux:button>
                                </flux:modal.close>
                            </div>
                        </div>
                    </flux:modal>
                </form>
            </div>
        </div>

        <script>
            // Simplified file validation function
            function validateFile(input, fieldName) {
                const fileStatus = document.getElementById('file-status');
                const fileList = document.getElementById('file-list');

                if (input.files[0]) {
                    const file = input.files[0];

                    // Validate file size (2MB limit)
                    if (file.size > 2 * 1024 * 1024) {
                        alert(`File ${file.name} terlalu besar! Maksimal 2MB.`);
                        input.value = '';
                        return false;
                    }

                    // Validate file type
                    if (file.type !== 'application/pdf') {
                        alert(`File ${file.name} bukan PDF! Hanya file PDF yang diperbolehkan.`);
                        input.value = '';
                        return false;
                    }

                    // Show file status
                    fileStatus.classList.remove('hidden');

                    // Update file list
                    const existingItem = document.querySelector(`[data-field="${fieldName}"]`);
                    if (existingItem) {
                        existingItem.remove();
                    }

                    const fileItem = document.createElement('div');
                    fileItem.className = 'flex items-center justify-between p-2 bg-white rounded border';
                    fileItem.setAttribute('data-field', fieldName);
                    fileItem.innerHTML = `
                    <div class="flex items-center space-x-2">
                        <svg class="w-4 h-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                        </svg>
                        <span class="text-sm font-medium">${fieldName.replace('_', ' ').toUpperCase()}</span>
                        <span class="text-sm text-gray-500">${file.name}</span>
                    </div>
                    <span class="text-xs text-gray-400">${(file.size/1024/1024).toFixed(2)} MB</span>
                `;

                    fileList.appendChild(fileItem);

                    // Update modal info
                    updateModalFileInfo();
                }

                return true;
            }

            // Update modal with file information
            function updateModalFileInfo() {
                const modalInfo = document.getElementById('modal-file-info');
                const cvFile = document.querySelector('input[name="cv"]').files[0];
                const transkripFile = document.querySelector('input[name="transkrip_nilai"]').files[0];
                const portfolioFile = document.querySelector('input[name="portfolio"]').files[0];

                let infoHtml = '';

                if (cvFile) {
                    infoHtml += `<div class="flex justify-between"><span>CV:</span><span>${cvFile.name}</span></div>`;
                }

                if (transkripFile) {
                    infoHtml +=
                        `<div class="flex justify-between"><span>Transkrip:</span><span>${transkripFile.name}</span></div>`;
                }

                if (portfolioFile) {
                    infoHtml +=
                        `<div class="flex justify-between"><span>Portfolio:</span><span>${portfolioFile.name}</span></div>`;
                }

                if (infoHtml) {
                    modalInfo.innerHTML = infoHtml;
                    modalInfo.classList.remove('hidden');
                }
            }
        </script>
    </div>
</div>
