<?php

use App\Models\{KontrakMagang, LowonganMagang, UlasanMagang};
use Illuminate\Support\Facades\{Auth, Storage};
use Livewire\WithFileUploads;
use function Livewire\Volt\{state, mount, rules, uses};

uses(WithFileUploads::class);

state([
    'bukti_surat_selesai_magang' => null,
    'mahasiswa' => null,
    'kontrak_magang' => null,
    'review_rating' => null,
    'review_komentar' => '',
    'show_review_form' => false,
    'existing_review' => null,
]);

rules([
    'bukti_surat_selesai_magang' => 'required|file|mimes:pdf|max:2048',
    'review_rating' => 'required|integer|min:1|max:5',
    'review_komentar' => 'required|string|min:10|max:500',
]);

mount(function () {
    $this->mahasiswa = Auth::guard('mahasiswa')->user();

    if (!$this->mahasiswa) {
        session()->flash('error', 'Anda harus login sebagai mahasiswa.');
        return;
    }

    // Get active internship contract
    $this->kontrak_magang = KontrakMagang::with(['lowonganMagang.perusahaan'])
        ->where('mahasiswa_id', $this->mahasiswa->id)
        ->first();

    // Check if student already has a review for this internship
    if ($this->kontrak_magang) {
        $this->existing_review = UlasanMagang::where('kontrak_magang_id', $this->kontrak_magang->id)->first();

        if ($this->existing_review) {
            $this->review_rating = $this->existing_review->rating;
            $this->review_komentar = $this->existing_review->komentar;
        }
    }
});

$showReviewForm = function () {
    $this->show_review_form = true;
    $this->resetErrorBag();
    // Pre-fill with existing review data if available
    if ($this->existing_review) {
        $this->review_rating = $this->existing_review->rating;
        $this->review_komentar = $this->existing_review->komentar;
    }
};

$hideReviewForm = function () {
    $this->show_review_form = false;
    $this->resetErrorBag();
    // Reset form data
    $this->reset(['review_rating', 'review_komentar', 'bukti_surat_selesai_magang']);
};

$validateStep = function ($step) {
    if ($step === 'review') {
        $this->validateOnly('review_rating');
        $this->validateOnly('review_komentar');
    } elseif ($step === 'file') {
        $this->validateOnly('bukti_surat_selesai_magang');
    }
};

$completeInternship = function () {
    try {
        if (!$this->mahasiswa) {
            session()->flash('error', 'Data mahasiswa tidak ditemukan.');
            return;
        }

        if (!$this->kontrak_magang) {
            session()->flash('error', 'Kontrak magang tidak ditemukan.');
            return;
        }

        // Check if student is currently doing internship
        if (!$this->isCurrentlyDoingInternship()) {
            session()->flash('error', 'Anda tidak sedang melakukan magang aktif.');
            return;
        }

        $this->validate([
            'bukti_surat_selesai_magang' => 'required|file|mimes:pdf|max:2048',
            'review_rating' => 'required|integer|min:1|max:5',
            'review_komentar' => 'required|string|min:10|max:500',
        ]);

        // Start database transaction
        \DB::beginTransaction();

        try {
            // Store the uploaded file
            $filePath = $this->bukti_surat_selesai_magang->store('surat-selesai-magang', 'public');

            // Create or update the review
            if ($this->existing_review) {
                $this->existing_review->forceFill([
                    'rating' => $this->review_rating,
                    'komentar' => $this->review_komentar,
                ])->save();
            } else {
                UlasanMagang::forceCreate([
                    'kontrak_magang_id' => $this->kontrak_magang->id,
                    'rating' => $this->review_rating,
                    'komentar' => $this->review_komentar,
                ]);
            }

            // Update mahasiswa status
            $updateData = ['status_magang' => 'selesai magang'];

            if (\Schema::hasColumn('mahasiswa', 'bukti_surat_selesai_magang')) {
                $updateData['bukti_surat_selesai_magang'] = $filePath;
            }

            $this->mahasiswa->update($updateData);

            // Update kontrak magang end date
            $kontrakUpdateData = ['waktu_akhir' => now()];

            if (\Schema::hasColumn('kontrak_magang', 'status')) {
                $kontrakUpdateData['status'] = 'selesai';
            }

            $this->kontrak_magang->update($kontrakUpdateData);

            \DB::commit();

            session()->flash('success', 'Status magang berhasil diperbarui menjadi selesai. Ulasan dan surat selesai magang telah tersimpan.');

            // Reset form
            $this->reset(['bukti_surat_selesai_magang', 'show_review_form']);

            // Refresh data
            $this->mahasiswa->refresh();
            $this->existing_review = UlasanMagang::where('kontrak_magang_id', $this->kontrak_magang->id)->first();

            // Emit event to parent component to refresh
            $this->dispatch('refreshParent');
        } catch (\Exception $e) {
            \DB::rollBack();
            throw $e;
        }
    } catch (\Illuminate\Validation\ValidationException $e) {
        // Fixed: Use Laravel's Arr::flatten or collect()->flatten() instead of array_flatten
        $errors = collect($e->errors())->flatten()->implode(', ');
        session()->flash('error', 'Error validasi: ' . $errors);
    } catch (\Illuminate\Database\QueryException $e) {
        session()->flash('error', 'Error database: ' . $e->getMessage());
    } catch (\Exception $e) {
        session()->flash('error', 'Terjadi kesalahan: ' . $e->getMessage() . ' (Line: ' . $e->getLine() . ')');
    }
};

$getInternshipInfo = function () {
    if (!$this->kontrak_magang || !$this->kontrak_magang->lowonganMagang || !$this->kontrak_magang->lowonganMagang->perusahaan) {
        return 'Informasi magang tidak tersedia';
    }

    $perusahaan = $this->kontrak_magang->lowonganMagang->perusahaan;
    return "{$perusahaan->nama} - {$perusahaan->lokasi}";
};

$isCurrentlyDoingInternship = function () {
    if (!$this->mahasiswa || !$this->kontrak_magang) {
        return false;
    }

    // Check if student status indicates active internship
    $activeStatuses = ['sedang magang', 'magang berlangsung', 'aktif magang'];
    $currentStatus = strtolower($this->mahasiswa->status_magang ?? '');

    // Check mahasiswa status
    $isActiveByStatus = in_array($currentStatus, $activeStatuses);

    // Check if contract is still active (not finished)
    $contractStatus = strtolower($this->kontrak_magang->status ?? 'aktif');
    $isActiveByContract = !in_array($contractStatus, ['selesai', 'completed', 'finished']);

    // Check if end date hasn't passed or is null (ongoing)
    $isActiveByDate = is_null($this->kontrak_magang->waktu_akhir) || $this->kontrak_magang->waktu_akhir->isFuture();

    return $isActiveByStatus && $isActiveByContract && $isActiveByDate;
};

$hasCompletedInternship = function () {
    if (!$this->mahasiswa) {
        return false;
    }

    $completedStatuses = ['selesai', 'selesai magang', 'completed', 'finished'];
    $currentStatus = strtolower($this->mahasiswa->status_magang ?? '');

    return in_array($currentStatus, $completedStatuses);
};

?>

<div class="space-y-4 border-t pt-4 mt-4">
    <h3 class="text-md font-semibold text-gray-700">Dokumen Selesai Magang</h3>

    @if (!$mahasiswa)
        <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50">
            Anda harus login sebagai mahasiswa untuk mengakses fitur ini.
        </div>
    @else
        @if (session()->has('success'))
            <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50">
                {{ session('success') }}
            </div>
        @endif

        @if (session()->has('error'))
            <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50">
                {{ session('error') }}
            </div>
        @endif

        @if ($this->hasCompletedInternship())
            <!-- Student has already completed internship -->
            <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50">
                <strong>Status:</strong> Magang telah selesai<br>
                @if ($mahasiswa->bukti_surat_selesai_magang)
                    <strong>Surat selesai magang:</strong>
                    <a href="{{ Storage::url($mahasiswa->bukti_surat_selesai_magang) }}" target="_blank"
                        class="text-blue-600 hover:underline">
                        Lihat dokumen
                    </a>
                @endif

                @if ($kontrak_magang)
                    <br><strong>Lokasi magang:</strong> {{ $this->getInternshipInfo() }}
                    <br><strong>Periode:</strong>
                    {{ $kontrak_magang->waktu_awal->format('d M Y') }} -
                    {{ $kontrak_magang->waktu_akhir ? $kontrak_magang->waktu_akhir->format('d M Y') : 'Sekarang' }}
                @endif

                @if ($existing_review)
                    <br><strong>Ulasan yang diberikan:</strong>
                    <div class="mt-2 p-3 bg-white rounded border">
                        <div class="flex items-center mb-2">
                            <strong>Rating:</strong>
                            <span class="ml-2">{{ $existing_review->rating }}/5</span>
                            <div class="ml-2 flex">
                                @for ($i = 1; $i <= 5; $i++)
                                    <svg class="w-4 h-4 {{ $i <= $existing_review->rating ? 'text-yellow-400' : 'text-gray-300' }}"
                                        fill="currentColor" viewBox="0 0 20 20">
                                        <path
                                            d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                    </svg>
                                @endfor
                            </div>
                        </div>
                        <strong>Komentar:</strong>
                        <p class="text-gray-700 mt-1">{{ $existing_review->komentar }}</p>
                    </div>
                @endif
            </div>
        @elseif ($this->isCurrentlyDoingInternship())
            <!-- Student is currently doing internship - show upload form with review -->
            @if ($kontrak_magang)
                <div class="p-4 mb-4 text-sm text-blue-800 rounded-lg bg-blue-50">
                    <strong>Mahasiswa:</strong> {{ $mahasiswa->nama }} ({{ $mahasiswa->nim }})<br>
                    <strong>Status saat ini:</strong>
                    {{ ucwords(str_replace('_', ' ', $mahasiswa->status_magang)) }}<br>
                    <strong>Lokasi magang:</strong> {{ $this->getInternshipInfo() }}<br>
                    <strong>Mulai magang:</strong> {{ $kontrak_magang->waktu_awal->format('d M Y') }}<br>
                </div>

                @if (!$show_review_form)
                    <!-- Enhanced Call-to-Action -->
                    <div
                        class="text-center py-8 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg border border-blue-200">
                        <div class="max-w-md mx-auto">
                            <div class="mb-4">
                                <svg class="w-12 h-12 mx-auto text-blue-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">Siap Menyelesaikan Magang?</h3>
                            <p class="text-gray-600 mb-6 text-sm leading-relaxed">
                                Untuk menyelesaikan magang Anda, kami membutuhkan:
                            </p>
                            <div class="space-y-2 mb-6 text-left">
                                <div class="flex items-center text-sm text-gray-700">
                                    <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                            clip-rule="evenodd"></path>
                                    </svg>
                                    Ulasan pengalaman magang Anda
                                </div>
                                <div class="flex items-center text-sm text-gray-700">
                                    <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                            clip-rule="evenodd"></path>
                                    </svg>
                                    Laporan akhir magang (PDF)
                                </div>
                            </div>
                            <flux:button wire:click="showReviewForm" variant="filled" class="px-8">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                    </path>
                                </svg>
                                Mulai Penyelesaian Magang
                            </flux:button>
                        </div>
                    </div>
                @else
                    <!-- Improved Multi-Step Form -->
                    <div class="max-w-4xl mx-auto">
                        <!-- Progress Indicator -->
                        <div class="mb-8">
                            <div class="flex items-center justify-center space-x-4">
                                <div class="flex items-center">
                                    <div
                                        class="flex items-center justify-center w-8 h-8 rounded-full bg-blue-600 text-white text-sm font-semibold">
                                        1
                                    </div>
                                    <span class="ml-2 text-sm font-medium text-blue-600">Ulasan</span>
                                </div>
                                <div class="flex-1 h-px bg-gray-300"></div>
                                <div class="flex items-center">
                                    <div
                                        class="flex items-center justify-center w-8 h-8 rounded-full {{ $review_rating && $review_komentar ? 'bg-blue-600 text-white' : 'bg-gray-300 text-gray-500' }} text-sm font-semibold">
                                        2
                                    </div>
                                    <span
                                        class="ml-2 text-sm font-medium {{ $review_rating && $review_komentar ? 'text-blue-600' : 'text-gray-500' }}">Dokumen</span>
                                </div>
                                <div class="flex-1 h-px bg-gray-300"></div>
                                <div class="flex items-center">
                                    <div
                                        class="flex items-center justify-center w-8 h-8 rounded-full {{ $bukti_surat_selesai_magang && $review_rating && $review_komentar ? 'bg-blue-600 text-white' : 'bg-gray-300 text-gray-500' }} text-sm font-semibold">
                                        3
                                    </div>
                                    <span
                                        class="ml-2 text-sm font-medium {{ $bukti_surat_selesai_magang && $review_rating && $review_komentar ? 'text-blue-600' : 'text-gray-500' }}">Selesai</span>
                                </div>
                            </div>
                        </div>

                        <form wire:submit.prevent="completeInternship" class="space-y-8">
                            <!-- Step 1: Review Section -->
                            <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                                <div class="flex items-center mb-6">
                                    <div
                                        class="flex items-center justify-center w-10 h-10 rounded-full bg-blue-100 text-blue-600 mr-4">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z">
                                            </path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900">Bagikan Pengalaman Anda</h3>
                                        <p class="text-sm text-gray-600">Berikan ulasan untuk
                                            {{ $this->getInternshipInfo() }}</p>
                                    </div>
                                </div>

                                <div class="grid md:grid-cols-2 gap-6">
                                    <!-- Enhanced Rating -->
                                    <flux:field>
                                        <flux:label class="text-base font-medium">Bagaimana pengalaman magang Anda?
                                        </flux:label>
                                        <div class="mt-3">
                                            <div class="flex items-center space-x-1 mb-3">
                                                @for ($i = 1; $i <= 5; $i++)
                                                    <button type="button"
                                                        wire:click="$set('review_rating', {{ $i }})"
                                                        class="focus:outline-none transition-colors duration-200">
                                                        <svg class="w-8 h-8 {{ $review_rating && $review_rating >= $i ? 'text-yellow-400' : 'text-gray-300 hover:text-yellow-300' }}"
                                                            fill="currentColor" viewBox="0 0 20 20">
                                                            <path
                                                                d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                                        </svg>
                                                    </button>
                                                @endfor
                                            </div>
                                            @if ($review_rating)
                                                <div class="text-sm text-gray-600">
                                                    @switch($review_rating)
                                                        @case(1)
                                                            <span class="text-red-600">Sangat Buruk</span>
                                                        @break

                                                        @case(2)
                                                            <span class="text-orange-600">Buruk</span>
                                                        @break

                                                        @case(3)
                                                            <span class="text-yellow-600">Cukup</span>
                                                        @break

                                                        @case(4)
                                                            <span class="text-green-600">Baik</span>
                                                        @break

                                                        @case(5)
                                                            <span class="text-blue-600">Sangat Baik</span>
                                                        @break
                                                    @endswitch
                                                </div>
                                            @endif
                                        </div>
                                        <flux:error for="review_rating" />
                                    </flux:field>

                                    <!-- Enhanced Comment -->
                                    <flux:field>
                                        <flux:label class="text-base font-medium">Ceritakan pengalaman Anda</flux:label>
                                        <div class="relative mt-2">
                                            <textarea wire:model.live="review_komentar" rows="5"
                                                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm resize-none"
                                                placeholder="Bagikan detail pengalaman magang Anda di perusahaan ini. Apa yang Anda pelajari? Bagaimana lingkungan kerjanya? Apa saran untuk mahasiswa lain?"></textarea>
                                            <div class="absolute bottom-2 right-2 text-xs text-gray-400">
                                                {{ strlen($review_komentar ?? '') }}/500
                                            </div>
                                        </div>
                                        <div class="mt-2 flex items-center text-xs text-gray-500">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                                                </path>
                                            </svg>
                                            Ulasan Anda akan membantu mahasiswa lain dalam memilih perusahaan
                                        </div>
                                        <flux:error for="review_komentar" />
                                    </flux:field>
                                </div>
                            </div>

                            <!-- Step 2: File Upload Section -->
                            <div
                                class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm {{ !$review_rating || !$review_komentar ? 'opacity-60' : '' }}">
                                <div class="flex items-center mb-6">
                                    <div
                                        class="flex items-center justify-center w-10 h-10 rounded-full {{ $review_rating && $review_komentar ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-400' }} mr-4">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                            </path>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900">Upload Dokumen</h3>
                                        <p class="text-sm text-gray-600">Upload laporan akhir magang Anda</p>
                                    </div>
                                </div>

                                <flux:field>
                                    <flux:input type="file" wire:model="bukti_surat_selesai_magang"
                                        label="Laporan Akhir Magang" accept=".pdf"
                                        placeholder="Pilih file PDF (maksimal 2MB)" />

                                    @if ($bukti_surat_selesai_magang && !$errors->has('bukti_surat_selesai_magang'))
                                        <div class="mt-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                                            <div class="flex items-center">
                                                <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor"
                                                    viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd"
                                                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                        clip-rule="evenodd"></path>
                                                </svg>
                                                <span class="text-sm text-green-700 font-medium">
                                                    {{ $bukti_surat_selesai_magang->getClientOriginalName() }}
                                                </span>
                                            </div>
                                        </div>
                                    @endif

                                    <div wire:loading wire:target="bukti_surat_selesai_magang" class="mt-2">
                                        <div class="flex items-center text-sm text-blue-600">
                                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-blue-600"
                                                xmlns="http://www.w3.org/2000/svg" fill="none"
                                                viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10"
                                                    stroke="currentColor" stroke-width="4">
                                                </circle>
                                                <path class="opacity-75" fill="currentColor"
                                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                                </path>
                                            </svg>
                                            Mengunggah file...
                                        </div>
                                    </div>

                                    <flux:error for="bukti_surat_selesai_magang" />
                                </flux:field>
                            </div>

                            <!-- Enhanced Form Actions -->
                            <div class="flex justify-between items-center pt-6 border-t border-gray-200">
                                <flux:button wire:click="hideReviewForm" variant="outline"
                                    class="flex items-center px-6 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-100 transition-colors duration-150"
                                    type="button">
                                    <span>Batal & Kembali</span>
                                </flux:button>

                                <div class="flex items-center space-x-4">
                                    @if ($bukti_surat_selesai_magang && $review_rating && $review_komentar)
                                        <div class="flex items-center text-sm text-green-600">
                                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                    clip-rule="evenodd"></path>
                                            </svg>
                                            Siap untuk diselesaikan
                                        </div>
                                    @endif

                                    <flux:button type="submit" wire:loading.attr="disabled"
                                        :disabled="!$bukti_surat_selesai_magang || !$review_rating || !$review_komentar"
                                        class="px-8 bg-blue-600 hover:bg-blue-700 text-white border-blue-600">
                                        <span wire:loading.remove wire:target="completeInternship"
                                            class="flex items-center">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            Selesaikan Magang
                                        </span>
                                        <span wire:loading wire:target="completeInternship" class="flex items-center">
                                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4"
                                                xmlns="http://www.w3.org/2000/svg" fill="none"
                                                viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10"
                                                    stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor"
                                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                                </path>
                                            </svg>
                                            Memproses...
                                        </span>
                                    </flux:button>
                                </div>
                            </div>
                        </form>
                    </div>
                @endif
            @else
                <div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50">
                    <strong>Peringatan:</strong> Kontrak magang tidak ditemukan untuk mahasiswa
                    {{ $mahasiswa->nama ?? 'Unknown' }}<br>
                    <strong>Mahasiswa ID:</strong> {{ $mahasiswa->id ?? 'N/A' }}<br>
                    <strong>Status mahasiswa:</strong> {{ $mahasiswa->status_magang ?? 'N/A' }}
                </div>
            @endif
        @else
            <!-- Student is not currently doing internship and hasn't completed one -->
            <div class="p-4 mb-4 text-sm text-gray-800 rounded-lg bg-gray-50">
                <strong>Status:</strong> Tidak sedang melakukan magang<br>
                <strong>Status mahasiswa:</strong>
                {{ ucwords(str_replace('_', ' ', $mahasiswa->status_magang ?? 'Belum ada status')) }}<br>

                @if (!$kontrak_magang)
                    <div class="mt-2 text-sm text-yellow-700">
                        Belum ada kontrak magang yang terdaftar.
                    </div>
                @else
                    <strong>Kontrak terakhir:</strong> {{ $this->getInternshipInfo() }}<br>
                    @if ($kontrak_magang->waktu_akhir)
                        <strong>Berakhir:</strong> {{ $kontrak_magang->waktu_akhir->format('d M Y') }}
            </div>
        @endif
    @endif
</div>
@endif
@endif
</div>
