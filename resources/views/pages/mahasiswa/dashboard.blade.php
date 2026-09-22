<?php

use function Livewire\Volt\{state, mount, computed};
use App\Models\FinalRankRecommendation;
use App\Models\LowonganMagang;
use Illuminate\Support\Facades\DB;

state(['recommendations', 'preferences_data', 'all_alternatives']);

mount(function () {
    $userId = auth('mahasiswa')->user()->id;

    // Latest final_rank per lowongan for this mahasiswa (parameterized).
    $latestRecommendationsSubquery = DB::table('final_rank_recommendation')
        ->select('lowongan_magang_id', DB::raw('MAX(created_at) as latest_created_at'))
        ->where('mahasiswa_id', $userId)
        ->groupBy('lowongan_magang_id');

    $uniqueRecommendations = DB::table('final_rank_recommendation as frr1')
        ->joinSub($latestRecommendationsSubquery, 'latest', function ($join) {
            $join->on('frr1.lowongan_magang_id', '=', 'latest.lowongan_magang_id')
                ->on('frr1.created_at', '=', 'latest.latest_created_at');
        })
        ->where('frr1.mahasiswa_id', $userId)
        ->select('frr1.*')
        ->orderBy('frr1.rank', 'asc')
        ->take(10)
        ->get();

    // Single eager-loaded lookup to avoid N+1.
    $lowonganById = LowonganMagang::with(['perusahaan.bidangIndustri', 'pekerjaan', 'lokasiMagang'])
        ->whereIn('id', $uniqueRecommendations->pluck('lowongan_magang_id'))
        ->get()
        ->keyBy('id');

    $this->recommendations = $uniqueRecommendations
        ->map(function ($item) use ($lowonganById) {
            /** @var \App\Models\LowonganMagang|null $lowonganMagang */
            $lowonganMagang = $lowonganById->get($item->lowongan_magang_id);

            if (! $lowonganMagang) {
                return null;
            }

            $perusahaan = $lowonganMagang->perusahaan;

            return [
                'rank' => $item->rank,
                'lowongan_id' => $item->lowongan_magang_id,
                'pekerjaan' => $lowonganMagang->pekerjaan->nama ?? '',
                'bidang_industri' => $perusahaan->bidangIndustri->nama ?? '',
                'lokasi' => $this->categorizeLocation($perusahaan->lokasi ?? ''),
                'jenis_magang' => $lowonganMagang->jenis_magang ?? '',
                'open_remote' => $lowonganMagang->open_remote ?? '',
                'nama_perusahaan' => $perusahaan->nama ?? '',
            ];
        })
        ->filter()
        ->toArray();

    // Load user preferences
    $this->preferences_data = $this->loadUserPreferences($userId);
});

// Simplified location categorization
$categorizeLocation = function ($lokasi) {
    if (empty($lokasi)) {
        return 'Tidak Diketahui';
    }

    $lokasi = strtolower(trim($lokasi));

    // Remote work
    if (strpos($lokasi, 'remote') !== false || strpos($lokasi, 'wfh') !== false) {
        return 'Remote';
    }

    // Malang area
    $malangAreas = ['malang', 'batu'];
    foreach ($malangAreas as $area) {
        if (strpos($lokasi, $area) !== false) {
            return 'Area Malang Raya';
        }
    }

    // International
    $international = ['singapore', 'malaysia', 'japan', 'korea', 'usa', 'australia'];
    foreach ($international as $country) {
        if (strpos($lokasi, $country) !== false) {
            return 'Luar Negeri';
        }
    }

    // East Java cities
    $eastJavaCities = ['surabaya', 'sidoarjo', 'kediri', 'blitar', 'jember'];
    foreach ($eastJavaCities as $city) {
        if (strpos($lokasi, $city) !== false) {
            return 'Luar Area Malang (Jawa Timur)';
        }
    }

    return 'Luar Provinsi Jawa Timur';
};

// Load user preferences
$loadUserPreferences = function ($userId) {
    $preferences = [];

    // Get job preference
    $pekerjaanPref = DB::table('kriteria_pekerjaan')->join('pekerjaan', 'kriteria_pekerjaan.pekerjaan_id', '=', 'pekerjaan.id')->where('kriteria_pekerjaan.mahasiswa_id', $userId)->orderBy('kriteria_pekerjaan.rank', 'asc')->first();

    if ($pekerjaanPref) {
        $preferences['pekerjaan'] = $pekerjaanPref->nama;
    }

    // Get industry preference
    $bidangPref = DB::table('kriteria_bidang_industri')->join('bidang_industri', 'kriteria_bidang_industri.bidang_industri_id', '=', 'bidang_industri.id')->where('kriteria_bidang_industri.mahasiswa_id', $userId)->orderBy('kriteria_bidang_industri.rank', 'asc')->first();

    if ($bidangPref) {
        $preferences['bidang_industri'] = $bidangPref->nama;
    }

    // Get location preference
    $lokasiPref = DB::table('kriteria_lokasi_magang')->join('lokasi_magang', 'kriteria_lokasi_magang.lokasi_magang_id', '=', 'lokasi_magang.id')->where('kriteria_lokasi_magang.mahasiswa_id', $userId)->orderBy('kriteria_lokasi_magang.rank', 'asc')->first();

    if ($lokasiPref) {
        $originalPreference = $lokasiPref->kategori_lokasi;
        $preferences['lokasi'] = $this->isAllPreference($originalPreference) ? $originalPreference : $this->categorizeLocation($originalPreference);
    }

    // Get internship type preference
    $jenisPref = DB::table('kriteria_jenis_magang')->where('mahasiswa_id', $userId)->orderBy('rank', 'asc')->first();

    if ($jenisPref) {
        $preferences['jenis_magang'] = $jenisPref->jenis_magang;
    }

    // Get remote preference
    $remotePref = DB::table('kriteria_open_remote')->where('mahasiswa_id', $userId)->orderBy('rank', 'asc')->first();

    if ($remotePref) {
        $preferences['open_remote'] = $remotePref->open_remote;
    }

    return $preferences;
};

// Check if preference is "all"
$isAllPreference = function ($preference) {
    return in_array(strtolower(trim($preference)), ['semua', 'all', 'semua jenis', 'semua bidang', 'semua lokasi']);
};

// Analyze preference matches
$analyzePreferenceMatches = computed(function () {
    if (!$this->preferences_data || !$this->recommendations) {
        return [];
    }

    $matches = [
        'job_type' => 0,
        'industry' => 0,
        'location' => 0,
        'payment_type' => 0,
        'remote_work' => 0,
        'total_perfect_matches' => 0,
        'total_analyzed' => count($this->recommendations),
    ];

    foreach ($this->recommendations as $recommendation) {
        $matchCount = 0;
        $totalCriteria = 0;

        // Check job type match
        if (isset($this->preferences_data['pekerjaan'])) {
            $totalCriteria++;
            if ($this->isAllPreference($this->preferences_data['pekerjaan']) || strtolower(trim($this->preferences_data['pekerjaan'])) === strtolower(trim($recommendation['pekerjaan']))) {
                $matches['job_type']++;
                $matchCount++;
            }
        }

        // Check industry match
        if (isset($this->preferences_data['bidang_industri'])) {
            $totalCriteria++;
            if ($this->isAllPreference($this->preferences_data['bidang_industri']) || strtolower(trim($this->preferences_data['bidang_industri'])) === strtolower(trim($recommendation['bidang_industri']))) {
                $matches['industry']++;
                $matchCount++;
            }
        }

        // Check location match
        if (isset($this->preferences_data['lokasi'])) {
            $totalCriteria++;
            if ($this->isAllPreference($this->preferences_data['lokasi']) || $this->preferences_data['lokasi'] === $recommendation['lokasi']) {
                $matches['location']++;
                $matchCount++;
            }
        }

        // Check payment type match
        if (isset($this->preferences_data['jenis_magang'])) {
            $totalCriteria++;
            if ($this->isAllPreference($this->preferences_data['jenis_magang']) || strtolower(trim($this->preferences_data['jenis_magang'])) === strtolower(trim($recommendation['jenis_magang']))) {
                $matches['payment_type']++;
                $matchCount++;
            }
        }

        // Check remote work match
        if (isset($this->preferences_data['open_remote'])) {
            $totalCriteria++;
            if ($this->isAllPreference($this->preferences_data['open_remote']) || strtolower(trim($this->preferences_data['open_remote'])) === strtolower(trim($recommendation['open_remote']))) {
                $matches['remote_work']++;
                $matchCount++;
            }
        }

        // Count perfect matches
        if ($matchCount === $totalCriteria && $totalCriteria > 0) {
            $matches['total_perfect_matches']++;
        }
    }

    return $matches;
});

?>

<div>
    @if ($recommendations && count($recommendations) > 0)
        <!-- Analysis Section -->
        <div class="mb-6 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg">
            <h3 class="font-bold text-indigo-800 mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                    </path>
                </svg>
                Analisis Rekomendasi
            </h3>

            @php $analysis = $this->analyzePreferenceMatches; @endphp

            <!-- Basic Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                <div class="text-center p-3 bg-white rounded-lg shadow-sm">
                    <p class="text-xl font-bold text-blue-600">{{ count($recommendations) }}</p>
                    <p class="text-sm text-gray-600">Total Rekomendasi</p>
                </div>
                <div class="text-center p-3 bg-white rounded-lg shadow-sm">
                    <p class="text-xl font-bold text-green-600">{{ $analysis['total_perfect_matches'] ?? 0 }}</p>
                    <p class="text-sm text-gray-600">Kecocokan Sempurna</p>
                </div>
                <div class="text-center p-3 bg-white rounded-lg shadow-sm">
                    <p class="text-xl font-bold text-purple-600">{{ $analysis['job_type'] ?? 0 }}</p>
                    <p class="text-sm text-gray-600">Pekerjaan Sesuai</p>
                </div>
                <div class="text-center p-3 bg-white rounded-lg shadow-sm">
                    <p class="text-xl font-bold text-orange-600">{{ $analysis['industry'] ?? 0 }}</p>
                    <p class="text-sm text-gray-600">Industri Sesuai</p>
                </div>
            </div>

            <!-- Preference Display -->
            @if ($preferences_data)
                <div class="bg-white p-3 rounded-lg shadow-sm">
                    <p class="text-sm font-medium text-gray-700 mb-2">Preferensi Anda:</p>
                    <div class="flex flex-wrap gap-2 text-xs">
                        @if (isset($preferences_data['pekerjaan']))
                            <span
                                class="bg-blue-100 text-blue-700 px-2 py-1 rounded">{{ $preferences_data['pekerjaan'] }}</span>
                        @endif
                        @if (isset($preferences_data['bidang_industri']))
                            <span
                                class="bg-green-100 text-green-700 px-2 py-1 rounded">{{ $preferences_data['bidang_industri'] }}</span>
                        @endif
                        @if (isset($preferences_data['lokasi']))
                            <span
                                class="bg-yellow-100 text-yellow-700 px-2 py-1 rounded">{{ $preferences_data['lokasi'] }}</span>
                        @endif
                        @if (isset($preferences_data['jenis_magang']))
                            <span
                                class="bg-purple-100 text-purple-700 px-2 py-1 rounded">{{ $preferences_data['jenis_magang'] }}</span>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <!-- Recommendations Title -->
        <div class="p-4 bg-gradient-to-r from-gray-50 to-slate-50 border border-gray-200 rounded-lg mb-4">
            <h2 class="font-bold text-gray-800 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10">
                    </path>
                </svg>
                Top 10 Rekomendasi Magang
            </h2>
        </div>

        <!-- Recommendations List -->
        <div class="space-y-3">
            @foreach ($recommendations as $index => $item)
                <div onclick="window.location='{{ route('mahasiswa.detail-lowongan-magang', $item['lowongan_id']) }}'"
                    class="flex items-center justify-between p-4 bg-white rounded-lg shadow-sm border hover:bg-blue-50 hover:border-blue-300 transition-all cursor-pointer">
                    <div class="flex items-center space-x-4">
                        <span
                            class="flex items-center justify-center w-8 h-8 bg-blue-500 text-white rounded-full text-sm font-bold">
                            {{ $index + 1 }}
                        </span>
                        <div>
                            <p class="font-semibold text-gray-800">{{ $item['pekerjaan'] }}</p>
                            <p class="text-gray-600">{{ $item['nama_perusahaan'] }}</p>
                            <div class="flex flex-wrap gap-1 text-xs mt-1">
                                <span
                                    class="bg-blue-100 text-blue-700 px-2 py-1 rounded">{{ $item['bidang_industri'] }}</span>
                                <span
                                    class="bg-green-100 text-green-700 px-2 py-1 rounded">{{ $item['lokasi'] }}</span>
                                @if ($item['jenis_magang'])
                                    <span
                                        class="bg-yellow-100 text-yellow-700 px-2 py-1 rounded">{{ $item['jenis_magang'] }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="bg-blue-100 text-blue-700 px-3 py-1 rounded font-medium text-sm">
                            Rank #{{ $item['rank'] }}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <!-- No Recommendations -->
        <div class="p-6 bg-gray-50 border border-gray-300 rounded-lg text-center">
            <svg class="w-12 h-12 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <h3 class="font-bold text-gray-700 mb-2">Tidak ada rekomendasi tersedia</h3>
            <p class="text-gray-600 mb-4">Belum ada data lowongan magang yang sesuai dengan preferensi Anda.</p>
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <p class="text-blue-800 font-medium mb-2">💡 Saran:</p>
                <ul class="text-blue-700 text-sm space-y-1">
                    <li>• Pastikan preferensi magang sudah diatur</li>
                    <li>• Lengkapi profil Anda</li>
                    <li>• Coba cari lowongan secara manual</li>
                </ul>
            </div>
        </div>
    @endif
</div>
