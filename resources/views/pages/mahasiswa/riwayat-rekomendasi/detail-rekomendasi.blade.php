<?php

use App\Models\EncodedAlternatives;
use App\Models\FinalRankRecommendation;
use App\Models\FullMultiplicativeForm;
use App\Models\LowonganMagang;
use App\Models\Mahasiswa;
use App\Models\RatioSystem;
use App\Models\ReferencePoint;
use App\Models\VectorNormalization;

use function Livewire\Volt\layout;
use function Livewire\Volt\mount;
use function Livewire\Volt\state;

layout('components.layouts.user.main');

state([
    'mahasiswa' => null,
    'rankingKriteria' => [],
    'alternatifLowongan' => [],
    'numericTable' => [],
    'normalisasiEuclidean' => [],
    'normalisasiVektor' => [],
    'rankingRS' => [],
    'rankingRP' => [],
    'rankingFMF' => [],
    'finalRanking' => [],
    'rekomendasi' => [],
]);

mount(function () {
    $this->mahasiswa = Auth::guard('mahasiswa')->user();
    $this->rankingKriteria = $this->getRankingKriteria();
    $this->alternatifLowongan = $this->getAlternatifLowongan();
    $this->numericTable = $this->getNumericTable();
    $this->normalisasiEuclidean = $this->getNormalisasiEuclidean();
    $this->normalisasiVektor = $this->getUniqueVectorNormalization();
    $this->rankingRS = $this->getRankingRS();
    $this->rankingRP = $this->getRankingRP();
    $this->rankingFMF = $this->getRankingFMF();
    $this->finalRanking = $this->getRankingGlobal();
    $this->rekomendasi = $this->getTopRekomendasi();
});

$getRankingKriteria = function () {
    if (! $this->mahasiswa) {
        return [];
    }

    return [
        [
            'nama' => 'Lokasi',
            'ranking' => optional($this->mahasiswa->kriteriaLokasiMagang)->rank,
            'bobot' => optional($this->mahasiswa->kriteriaLokasiMagang)->bobot,
        ],
        [
            'nama' => 'Pekerjaan',
            'ranking' => optional($this->mahasiswa->kriteriaPekerjaan)->rank,
            'bobot' => optional($this->mahasiswa->kriteriaPekerjaan)->bobot,
        ],
        [
            'nama' => 'Bidang Industri',
            'ranking' => optional($this->mahasiswa->kriteriaBidangIndustri)->rank,
            'bobot' => optional($this->mahasiswa->kriteriaBidangIndustri)->bobot,
        ],
        [
            'nama' => 'Open Remote',
            'ranking' => optional($this->mahasiswa->kriteriaOpenRemote)->rank,
            'bobot' => optional($this->mahasiswa->kriteriaOpenRemote)->bobot,
        ],
        [
            'nama' => 'Jenis Magang',
            'ranking' => optional($this->mahasiswa->kriteriaJenisMagang)->rank,
            'bobot' => optional($this->mahasiswa->kriteriaJenisMagang)->bobot,
        ],
    ];
};

$getAlternatifLowongan = function () {
    return LowonganMagang::with(['lokasiMagang', 'perusahaan.bidangIndustri', 'pekerjaan'])->get();
};

$getNumericTable = function () {
    $tanggal = request('tanggal');

    $query = EncodedAlternatives::with(['mahasiswa', 'lowonganMagang'])
        ->when($tanggal, function ($query) use ($tanggal) {
            return $query->whereDate('created_at', $tanggal);
        })
        ->when(! $tanggal, function ($query) {
            return $query->whereDate('created_at', now()->toDateString());
        })
        ->orderBy('created_at', 'desc');

    // Ambil data dan filter untuk mendapatkan data terbaru per lowongan_magang_id
    $allData = $query->get();

    return $this->getUniqueByLowonganId($allData);
};

$getUniqueVectorNormalization = function () {
    $tanggal = request('tanggal');

    $query = VectorNormalization::with(['mahasiswa', 'lowonganMagang'])
        ->when($tanggal, function ($query) use ($tanggal) {
            return $query->whereDate('created_at', $tanggal);
        })
        ->when(! $tanggal, function ($query) {
            return $query->whereDate('created_at', now()->toDateString());
        })
        ->orderBy('created_at', 'desc');

    // Ambil data dan filter untuk mendapatkan data terbaru per lowongan_magang_id
    $allData = $query->get();

    return $this->getUniqueByLowonganId($allData);
};

$getNormalisasiEuclidean = function () {
    $numericTable = $this->getNumericTable();

    // Return empty array if no data
    if ($numericTable->isEmpty()) {
        return [
            'lokasi_magang' => 0,
            'open_remote' => 0,
            'jenis_magang' => 0,
            'bidang_industri' => 0,
            'pekerjaan' => 0,
        ];
    }

    // Daftar kolom kriteria numerik yang akan dihitung
    $criteriaColumns = ['pekerjaan', 'bidang_industri', 'lokasi_magang', 'open_remote', 'jenis_magang'];

    $euclideanNormalizationList = [];

    // Hitung Euclidean norm untuk masing-masing kolom
    foreach ($criteriaColumns as $column) {
        // Ambil semua nilai untuk kolom ini dan filter yang valid
        $values = $numericTable
            ->pluck($column)
            ->filter(function ($value) {
                return ! is_null($value) && is_numeric($value);
            })
            ->map(function ($value) {
                return (float) $value;
            });

        // Hitung sum of squares
        $sumOfSquares = $values->sum(function ($value) {
            return pow($value, 2);
        });

        // Hitung euclidean norm
        $euclideanNorm = $sumOfSquares > 0 ? sqrt($sumOfSquares) : 0;
        $euclideanNormalizationList[$column] = $euclideanNorm;
    }

    return $euclideanNormalizationList;
};

$getRankingRS = function () {
    $tanggal = request('tanggal');

    $query = RatioSystem::with(['mahasiswa', 'lowonganMagang'])
        ->when($tanggal, function ($query) use ($tanggal) {
            return $query->whereDate('created_at', $tanggal);
        })
        ->when(! $tanggal, function ($query) {
            return $query->whereDate('created_at', now()->toDateString());
        })
        ->orderBy('created_at', 'desc');

    // Ambil data dan filter untuk mendapatkan data terbaru per lowongan_magang_id
    $allData = $query->get();
    $uniqueData = $this->getUniqueByLowonganId($allData);

    // Urutkan berdasarkan rank setelah filtering
    return $uniqueData->sortBy('rank')->values();
};

$getRankingRP = function () {
    $tanggal = request('tanggal');

    $query = ReferencePoint::with(['mahasiswa', 'lowonganMagang'])
        ->when($tanggal, function ($query) use ($tanggal) {
            return $query->whereDate('created_at', $tanggal);
        })
        ->when(! $tanggal, function ($query) {
            return $query->whereDate('created_at', now()->toDateString());
        })
        ->orderBy('created_at', 'desc');

    // Ambil data dan filter untuk mendapatkan data terbaru per lowongan_magang_id
    $allData = $query->get();
    $uniqueData = $this->getUniqueByLowonganId($allData);

    // Urutkan berdasarkan rank setelah filtering
    return $uniqueData->sortBy('rank')->values();
};

$getRankingFMF = function () {
    $tanggal = request('tanggal');

    $query = FullMultiplicativeForm::with(['mahasiswa', 'lowonganMagang'])
        ->when($tanggal, function ($query) use ($tanggal) {
            return $query->whereDate('created_at', $tanggal);
        })
        ->when(! $tanggal, function ($query) {
            return $query->whereDate('created_at', now()->toDateString());
        })
        ->orderBy('created_at', 'desc');

    // Ambil data dan filter untuk mendapatkan data terbaru per lowongan_magang_id
    $allData = $query->get();
    $uniqueData = $this->getUniqueByLowonganId($allData);

    // Urutkan berdasarkan rank setelah filtering
    return $uniqueData->sortBy('rank')->values();
};

$getRankingGlobal = function () {
    $tanggal = request('tanggal');

    $query = FinalRankRecommendation::with(['mahasiswa', 'lowonganMagang.perusahaan', 'ratioSystem', 'referencePoint', 'fullMultiplicativeForm'])
        ->when($tanggal, fn ($query) => $query->whereDate('created_at', $tanggal))
        ->when(! $tanggal, fn ($query) => $query->whereDate('created_at', now()->toDateString()))
        ->orderBy('created_at', 'desc');

    // Ambil data dan filter untuk mendapatkan data terbaru per lowongan_magang_id
    $allData = $query->get();
    $uniqueData = $this->getUniqueByLowonganId($allData);

    // Urutkan berdasarkan rank setelah filtering
    return $uniqueData->sortBy('rank')->values();
};

$getTopRekomendasi = function () {
    $tanggal = request('tanggal');
    $mahasiswa = $this->mahasiswa;

    // Ambil data final ranking dengan relasi
    $query = FinalRankRecommendation::with(['mahasiswa', 'lowonganMagang', 'ratioSystem', 'referencePoint', 'fullMultiplicativeForm'])
        ->when($tanggal, function ($query) use ($tanggal) {
            return $query->whereDate('created_at', $tanggal);
        })
        ->when(! $tanggal, function ($query) {
            return $query->whereDate('created_at', now()->toDateString());
        })
        ->where('mahasiswa_id', $mahasiswa->id)
        ->orderBy('created_at', 'desc');

    // Ambil semua data dan filter untuk mendapatkan data terbaru per lowongan_magang_id
    $allData = $query->get();
    $uniqueData = $this->getUniqueByLowonganId($allData);

    // Urutkan berdasarkan avg_rank dan batasi 10 data
    $topRecommendations = $uniqueData->sortBy('avg_rank')->take(10)->values();

    // Re-rank berdasarkan urutan
    return $topRecommendations->map(function ($item, $index) {
        $item->display_rank = $index + 1;

        return $item;
    });
};

// Helper function untuk mendapatkan data unik berdasarkan lowongan_magang_id
$getUniqueByLowonganId = function ($collection) {
    $uniqueData = collect();
    $usedLowonganIds = [];

    foreach ($collection as $item) {
        $lowonganId = $item->lowongan_magang_id ?? $item->id;

        // Jika lowongan_magang_id belum ada dalam hasil, tambahkan
        // Data sudah diurutkan berdasarkan created_at desc, jadi yang pertama adalah yang terbaru
        if (! in_array($lowonganId, $usedLowonganIds)) {
            $uniqueData->push($item);
            $usedLowonganIds[] = $lowonganId;
        }
    }

    return $uniqueData;
};

?>

<div>
    <x-slot:user>mahasiswa</x-slot:user>
    @section('script')
        <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
    @endsection

    <div class="card bg-white shadow-md mb-3">
        <div class="card-body">
            <h2 class="text-base font-semibold">Kriteria yang anda pilih</h2>
            <p>Pemilihan kriteria menggunakan metode ROC</p>
            <table class="table-auto w-fit ">
                <thead>
                    <tr class="bg-gray-200">
                        <th class="border px-4 py-2">No</th>
                        <th class="border px-4 py-2">Kriteria</th>
                        <th class="border px-4 py-2">Ranking</th>
                        <th class="border px-4 py-2">Bobot</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rankingKriteria as $index => $kriteria)
                        <tr>
                            <td class="border px-4 py-2">{{ $index + 1 }}</td>
                            <td class="border px-4 py-2">{{ $kriteria['nama'] }}</td>
                            <td class="border px-4 py-2">{{ $kriteria['ranking'] ?? '-' }}</td>
                            <td class="border px-4 py-2">{{ $kriteria['bobot'] ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card bg-white shadow-md p-5">
        <div>
            <h2 class="text-base font-semibold">Tabel Perhitungan Multimoora</h2>
        </div>

        <div class="p-3 pb-4">
            <div class="collapse border-base-300 border-2 overflow-x-auto">
                <input type="checkbox" />
                <div class="collapse-title font-semibold">Tabel Detail Perhitungan</div>
                <div class="collapse-content text-sm">
                    <div class="pb-3">
                        <div class="font-bold text-lg mt-5">
                            <h2>Urutan Tabel Kriteria menurut {{ $mahasiswa->nama ?? 'Mahasiswa' }}</h2>
                        </div>
                        <table class="table-auto w-full">
                            <thead class="bg-white text-black">
                                <tr class="border-b bg-green-400">
                                    <th class="text-left px-6 py-3">Nama</th>
                                    <th class="text-center px-6 py-3">Urutan ranking</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white text-black">
                                @foreach (collect($rankingKriteria)->sortBy('ranking') as $kriteria)
                                    @if (!is_null($kriteria['ranking']))
                                        <tr>
                                            <td class="px-6 py-3">{{ $kriteria['nama'] }}</td>
                                            <td class="px-6 py-3 text-right">{{ $kriteria['ranking'] }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div>
                        <div class="font-bold text-lg mt-10">
                            <h2>Preferensi Mahasiswa {{ $mahasiswa->nama ?? 'Mahasiswa' }}</h2>
                        </div>
                        <table class="table-auto w-full">
                            <thead class="bg-white text-black">
                                <tr class="border-b bg-green-400">
                                    <th class="text-left px-6 py-3">Kriteria</th>
                                    <th class="text-left px-6 py-3">Nilai</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white text-black">
                                <tr>
                                    <td class="px-6 py-3">Lokasi</td>
                                    <td class="px-6 py-3">
                                        {{ $mahasiswa->kriteriaLokasiMagang->lokasiMagang->kategori_lokasi ?? '-' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-3">Pekerjaan</td>
                                    <td class="px-6 py-3">{{ $mahasiswa->kriteriaPekerjaan->pekerjaan->nama ?? '-' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-3">Bidang Industri</td>
                                    <td class="px-6 py-3">
                                        {{ $mahasiswa->kriteriaBidangIndustri->bidangIndustri->nama ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-3">Open Remote</td>
                                    <td class="px-6 py-3">{{ ($mahasiswa->kriteriaOpenRemote->open_remote ?? null) === 'ya' ? 'Ya' : 'Tidak' }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-3">Jenis Magang</td>
                                    <td class="px-6 py-3">
                                        @if ($mahasiswa->kriteriaJenisMagang->jenis_magang ?? null)
                                            @if ($mahasiswa->kriteriaJenisMagang->jenis_magang === 'berbayar')
                                                Dibayar (Paid Internship)
                                            @elseif($mahasiswa->kriteriaJenisMagang->jenis_magang === 'tidak berbayar')
                                                Tidak Dibayar (Unpaid Internship)
                                            @else
                                                {{ $mahasiswa->kriteriaJenisMagang->jenis_magang }}
                                            @endif
                                        @else
                                            -
                                        @endif
                                    </td>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div>
                        <div class="font-bold text-lg mt-10">
                            <h2>Tabel Awal</h2>
                        </div>
                        <div>
                            <table class="table-auto w-fit">
                                <thead class="bg-white text-black">
                                    <tr class="border-b bg-green-400">
                                        <th class="text-left px-6 py-3">ID Lowongan</th>
                                        <th class="text-left px-6 py-3">Perusahaan</th>
                                        <th class="text-left px-6 py-3">Lokasi</th>
                                        <th class="text-left px-6 py-3">Open remote</th>
                                        <th class="text-left px-6 py-3">Jenis magang</th>
                                        <th class="text-left px-6 py-3">Bidang industri</th>
                                        <th class="text-left px-6 py-3">Pekerjaan</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white text-black">
                                    @foreach ($alternatifLowongan->sortBy('id') as $lowongan)
                                        <tr>
                                            <td class="px-6 py-3">{{ $lowongan->id ?? '-' }}</td>
                                            <td class="px-6 py-3">{{ $lowongan->perusahaan->nama ?? '-' }}</td>
                                            <td class="px-6 py-3">
                                                {{ $lowongan->lokasiMagang->kategori_lokasi ?? '-' }}</td>
                                            <td class="px-6 py-3">
                                                {{ ucfirst($lowongan->open_remote) ? 'Ya' : 'Tidak' }}</td>
                                            <td class="px-6 py-3">{{ ucfirst($lowongan->jenis_magang) }}</td>
                                            <td class="px-6 py-3">
                                                {{ $lowongan->perusahaan->bidangIndustri->nama ?? '-' }}
                                            </td>
                                            <td class="px-6 py-3">{{ $lowongan->pekerjaan->nama ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div>
                        <div class="font-bold text-lg mt-10">
                            <h2>Tabel Numerik {{ $mahasiswa->nama ?? 'Mahasiswa' }}</h2>
                        </div>
                        <table class="table-auto w-fit">
                            <thead class="bg-white text-black">
                                <tr class="border-b bg-green-400">
                                    <th class="text-left px-6 py-3">ID Lowongan</th>
                                    <th class="text-left px-6 py-3">Pekerjaan</th>
                                    <th class="text-left px-6 py-3">Nama Perusahaan</th>
                                    <th class="text-center px-6 py-3">Lokasi Magang</th>
                                    <th class="text-center px-6 py-3">Open Remote</th>
                                    <th class="text-center px-6 py-3">Jenis Magang</th>
                                    <th class="text-center px-6 py-3">Bidang Industri</th>
                                    <th class="text-center px-6 py-3">Pekerjaan</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white text-black">
                                @foreach ($numericTable->sortBy('lowonganMagang.id') as $encoded)
                                    <tr>
                                        <td class="px-6 py-3">
                                            {{ $encoded->lowonganMagang->id ?? '-' }}
                                        </td>
                                        <td class="px-6 py-3">
                                            {{ $encoded->lowonganMagang->pekerjaan->nama ?? '-' }}
                                        </td>
                                        <td class="px-6 py-3">
                                            {{ $encoded->lowonganMagang->perusahaan->nama ?? '-' }}
                                        </td>
                                        <td class="px-6 py-3 text-center">
                                            {{ $encoded->lokasi_magang ?? '-' }}
                                        </td>
                                        <td class="px-6 py-3 text-center">
                                            {{ $encoded->open_remote ?? '-' }}
                                        </td>
                                        <td class="px-6 py-3 text-center">
                                            {{ $encoded->jenis_magang ?? '-' }}
                                        </td>
                                        <td class="px-6 py-3 text-center">
                                            {{ $encoded->bidang_industri ?? '-' }}
                                        </td>
                                        <td class="px-6 py-3 text-center">
                                            {{ $encoded->pekerjaan ?? '-' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div>
                        <div class="font-bold text-lg mt-5">
                            <h2>Normalisasi Euclidean</h2>
                        </div>

                        <table class="table-auto w-full">
                            <thead class="bg-white text-black">
                                <tr class="border-b bg-green-400">
                                    <th class="text-left px-6 py-3">Nilai</th>
                                    <th class="text-left px-6 py-3">Lokasi</th>
                                    <th class="text-left px-6 py-3">Open Remote</th>
                                    <th class="text-left px-6 py-3">Jenis Magang</th>
                                    <th class="text-left px-6 py-3">Bidang Industri</th>
                                    <th class="text-left px-6 py-3">Pekerjaan</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white text-black">
                                <tr>
                                    <td class="px-6 py-3">\( \sqrt{\sum_{j=1}^{n} x_j^2} \)</td>
                                    <td class="px-6 py-3 text-right">
                                        {{ number_format($normalisasiEuclidean['lokasi_magang'] ?? 0, 6) }}</td>
                                    <td class="px-6 py-3 text-right">
                                        {{ number_format($normalisasiEuclidean['open_remote'] ?? 0, 6) }}</td>
                                    <td class="px-6 py-3 text-right">
                                        {{ number_format($normalisasiEuclidean['jenis_magang'] ?? 0, 6) }}</td>
                                    <td class="px-6 py-3 text-right">
                                        {{ number_format($normalisasiEuclidean['bidang_industri'] ?? 0, 6) }}</td>
                                    <td class="px-6 py-3 text-right">
                                        {{ number_format($normalisasiEuclidean['pekerjaan'] ?? 0, 6) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div>
                        <div class="font-bold text-lg mt-5">
                            <h2>Normalisasi Vektor</h2>
                        </div>

                        <table class="table-auto w-fit">
                            <thead class="bg-white text-black">
                                <tr class="border-b bg-green-400">
                                    <th class="text-left px-6 py-3">ID Lowongan</th>
                                    <th class="text-left px-6 py-3">Pekerjaan</th>
                                    <th class="text-center px-6 py-3">Nama Perusahaan</th>
                                    <th class="text-center px-6 py-3">Lokasi</th>
                                    <th class="text-center px-6 py-3">Open Remote</th>
                                    <th class="text-center px-6 py-3">Jenis Magang</th>
                                    <th class="text-center px-6 py-3">Bidang Industri</th>
                                    <th class="text-center px-6 py-3">Pekerjaan</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white text-black">
                                @foreach ($normalisasiVektor as $item)
                                    <tr>
                                        <td class="px-6 py-3">
                                            {{ $item->lowonganMagang->id ?? 'N/A' }}
                                        </td>
                                        <td class="px-6 py-3 text-left">
                                            {{ $item->lowonganMagang->pekerjaan->nama ?? 'N/A' }}
                                        </td>
                                        <td class="px-6 py-3 text-left">
                                            {{ $item->lowonganMagang->perusahaan->nama ?? 'N/A' }}
                                        </td>
                                        <td class="px-6 py-3 text-center">
                                            {{ number_format($item->lokasi_magang ?? 0, 10) }}
                                        </td>
                                        <td class="px-6 py-3 text-center">
                                            {{ number_format($item->open_remote ?? 0, 10) }}
                                        </td>
                                        <td class="px-6 py-3 text-center">
                                            {{ number_format($item->jenis_magang ?? 0, 10) }}
                                        </td>
                                        <td class="px-6 py-3 text-center">
                                            {{ number_format($item->bidang_industri ?? 0, 10) }}
                                        </td>
                                        <td class="px-6 py-3 text-center">
                                            {{ number_format($item->pekerjaan ?? 0, 10) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>


        <div class="p-3 pb-4">
            <div class="collapse border-base-300 border-2 overflow-x-auto">
                <input type="checkbox" />
                <div class="collapse-title font-semibold">Tabel Perangkingan</div>
                <div class="collapse-content text-sm">
                    <div>
                        <div class="font-bold text-lg mt-5">
                            <h2>Tabel Hasil Metode Ratio System (RS)</h2>
                        </div>
                        <div>
                            <table class="table-auto w-full  ">
                                <thead class="bg-white text-black">
                                    <tr class="border-b bg-green-400">
                                        <th class="text-center px-6 py-3">ID Lowongan</th>
                                        <th class="text-center px-6 py-3">Alternatif (Pekerjaan)</th>
                                        <th class="text-center px-6 py-3">Nama Perusahaan</th>
                                        <th class="text-center px-6 py-3">Nilai</th>
                                        <th class="text-center px-6 py-3">Rank</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white text-black">
                                    @forelse($rankingRS->sortBy('rank') as $item)
                                        <tr>
                                            <td class="px-6 py-3">
                                                {{ $item->lowonganMagang->id ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-3">
                                                {{ $item->lowonganMagang->pekerjaan->nama ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-3">
                                                {{ $item->lowonganMagang->perusahaan->nama ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-3 text-right">
                                                {{ number_format($item->score ?? 0, 10) }}
                                            </td>
                                            <td class="px-6 py-3 text-right">
                                                {{ $item->rank ?? '-' }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="px-6 py-8 text-center text-gray-500">
                                                <div class="flex flex-col items-center">
                                                    <svg class="w-12 h-12 mb-4 text-gray-300" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 00-2-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v6a2 2 0 00-2 2H9z" />
                                                    </svg>
                                                    <p class="text-lg font-medium">Tidak ada data ranking RS</p>
                                                    <p class="text-sm">Pastikan proses perhitungan Ratio System sudah
                                                        dijalankan</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div>
                        <div class="font-bold text-lg mt-5">
                            <h2>Tabel Hasil Metode Reference Point (RP)</h2>
                        </div>
                        <div>
                            <table class="table-fixed w-fit">
                                <thead class="bg-white text-black">
                                    <tr class="border-b bg-green-400">
                                        <th class="text-left px-6 py-3">ID Lowongan</th>
                                        <th class="text-left px-6 py-3">Alternatif (Pekerjaan)</th>
                                        <th class="text-center px-6 py-3">Nama Perusahaan</th>
                                        <th class="text-center px-6 py-3">Open Remote</th>
                                        <th class="text-center px-6 py-3">Jenis Magang</th>
                                        <th class="text-center px-6 py-3">Bidang Industri</th>
                                        <th class="text-center px-6 py-3">Lokasi Magang</th>
                                        <th class="text-center px-6 py-3">Max Score</th>
                                        <th class="text-center px-6 py-3">Rank</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white text-black">
                                    @forelse($rankingRP->sortBy('rank') as $item)
                                        <tr>
                                            <td class="px-6 py-3">
                                                {{ $item->lowonganMagang->id ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-3">
                                                {{ $item->lowonganMagang->pekerjaan->nama ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-3">
                                                {{ $item->lowonganMagang->perusahaan->nama ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-3 text-right">
                                                {{ $item->open_remote ?? '-' }}
                                            </td>
                                            <td class="px-6 py-3 text-right">
                                                {{ $item->jenis_magang ?? '-' }}
                                            </td>
                                            <td class="px-6 py-3 text-right">
                                                {{ $item->bidang_industri ?? '-' }}
                                            </td>
                                            <td class="px-6 py-3 text-right">
                                                {{ $item->lokasi_magang ?? '-' }}
                                            </td>
                                            <td class="px-6 py-3 text-right">
                                                {{ number_format($item->max_score ?? 0, 10) }}
                                            </td>
                                            <td class="px-6 py-3 text-right">
                                                {{ $item->rank ?? '-' }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="px-6 py-8 text-center text-gray-500">
                                                <div class="flex flex-col items-center">
                                                    <svg class="w-12 h-12 mb-4 text-gray-300" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 00-2-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v6a2 2 0 00-2 2H9z" />
                                                    </svg>
                                                    <p class="text-lg font-medium">Tidak ada data ranking RP</p>
                                                    <p class="text-sm">Pastikan proses perhitungan Reference Point
                                                        sudah
                                                        dijalankan</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div>
                        <div class="font-bold text-lg mt-5">
                            <h2>Tabel Hasil Metode Full Multiplicative Form (FMF)</h2>
                        </div>
                        <div>
                            <table class="table-auto w-full">
                                <thead class="bg-white text-black">
                                    <tr class="border-b bg-green-400">
                                        <th class="text-left px-6 py-3">ID Lowongan</th>
                                        <th class="text-left px-6 py-3">Alternatif (Pekerjaan)</th>
                                        <th class="text-center px-6 py-3">Nama Perusahaan</th>
                                        <th class="text-center px-6 py-3">Score</th>
                                        <th class="text-center px-6 py-3">Rank</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white text-black">
                                    @forelse($rankingFMF->sortBy('rank') as $item)
                                        <tr>
                                            <td class="px-6 py-3">
                                                {{ $item->lowonganMagang->id ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-3">
                                                {{ $item->lowonganMagang->pekerjaan->nama ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-3 text-left">
                                                {{ $item->lowonganMagang->perusahaan->nama ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-3 text-left">
                                                {{ number_format($item->score ?? 0, 10) }}
                                            </td>
                                            <td class="px-6 py-3 text-left">
                                                {{ $item->rank ?? '-' }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="px-6 py-8 text-center text-gray-500">
                                                <div class="flex flex-col items-center">
                                                    <svg class="w-12 h-12 mb-4 text-gray-300" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 00-2-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v6a2 2 0 00-2 2H9z" />
                                                    </svg>
                                                    <p class="text-lg font-medium">Tidak ada data ranking RP</p>
                                                    <p class="text-sm">Pastikan proses perhitungan Reference Point
                                                        sudah
                                                        dijalankan</p>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="font-bold text-lg mt-5">
                            <h2>Tabel Hasil Perankingan Global</h2>
                        </div>
                        <div>
                            <table class="table-auto w-fit">
                                <thead class="bg-white text-black">
                                    <tr class="border-b bg-orange-400">
                                        <th class="text-left px-6 py-3">ID Lowongan</th>
                                        <th class="text-left px-6 py-3">Alternatif</th>
                                        <th class="text-left px-6 py-3">Perusahaan</th>
                                        <th class="text-center px-6 py-3">RS</th>
                                        <th class="text-center px-6 py-3">RP</th>
                                        <th class="text-center px-6 py-3">FMF</th>
                                        <th class="text-center px-6 py-3">Rata-Rata Ranking</th>
                                        <th class="text-center px-6 py-3">Final Ranking</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white text-black">
                                    @php
                                        $mahasiswaLogin = Auth::guard('mahasiswa')->user();
                                        // Pre-index the stage rankings to avoid O(n^2) collection scans per row.
                                        $rankingRSIndex = $rankingRS->keyBy(fn ($r) => $r->mahasiswa_id.'-'.$r->lowongan_magang_id);
                                        $rankingRPIndex = $rankingRP->keyBy(fn ($r) => $r->mahasiswa_id.'-'.$r->lowongan_magang_id);
                                        $rankingFMFIndex = $rankingFMF->keyBy(fn ($r) => $r->mahasiswa_id.'-'.$r->lowongan_magang_id);
                                    @endphp

                                    @foreach ($finalRanking as $ranking)
                                        @if ($ranking->mahasiswa_id === $mahasiswaLogin->id)
                                            {{-- Baris Tabel --}}
                                            <tr class="border-b hover:bg-gray-50">
                                                <td class="px-6 py-4">
                                                    {{ $ranking->lowonganMagang->id ?? 'N/A' }}
                                                </td>
                                                <td class="px-6 py-4">
                                                    {{ $ranking->lowonganMagang->pekerjaan->nama ?? 'N/A' }}
                                                </td>
                                                <td class="px-6 py-4">
                                                    {{ $ranking->lowonganMagang->perusahaan->nama ?? '-' }}
                                                </td>
                                                <td class="text-center px-6 py-4">
                                                    {{ $rankingRSIndex[$ranking->mahasiswa_id.'-'.$ranking->lowongan_magang_id]->rank ?? '-' }}
                                                </td>
                                                <td class="text-center px-6 py-4">
                                                    {{ $rankingRPIndex[$ranking->mahasiswa_id.'-'.$ranking->lowongan_magang_id]->rank ?? '-' }}
                                                </td>
                                                <td class="text-center px-6 py-4">
                                                    {{ $rankingFMFIndex[$ranking->mahasiswa_id.'-'.$ranking->lowongan_magang_id]->rank ?? '-' }}
                                                </td>
                                                <td class="text-center px-6 py-4">
                                                    {{ number_format($ranking->avg_rank, 6) }}
                                                </td>
                                                <td class="text-center px-6 py-4 font-bold text-green-600">
                                                    {{ $ranking->rank }}
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach

                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pt-7">
                <h2 class="text-base font-semibold">Top 10 Perusahaan Hasil Rekomendasi Magang</h2>
                <div class="pb-3">
                    <p>Pemilihan Rekomendasi menggunakan metode Multimoora</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="table-auto w-fit">
                        <thead class="bg-white text-black">
                            <tr class="border-b bg-yellow-200">
                                <th class="text-center px-6 py-3">ID Lowongan</th>
                                <th class="text-center px-6 py-3">Pekerjaan</th>
                                <th class="text-center px-6 py-3">Perusahaan</th>
                                <th class="text-center px-6 py-3">Rata-Rata Ranking</th>
                                <th class="text-center px-6 py-3">Rank</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white text-black">
                            @foreach ($rekomendasi as $item)
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="px-6 py-3">
                                        <div class="font-medium">{{ $item->lowonganMagang->id ?? 'N/A' }}</div>
                                    </td>
                                    <td class="px-6 py-3">
                                        <div class="font-medium">{{ $item->lowonganMagang->pekerjaan->nama ?? 'N/A' }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-3">
                                        <div class="font-medium">
                                            {{ $item->lowonganMagang->perusahaan->nama ?? 'N/A' }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-3 text-center">
                                        {{ number_format($item->avg_rank, 9) }}
                                    </td>
                                    <td class="px-6 py-3 text-center font-medium">{{ $item->display_rank }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
