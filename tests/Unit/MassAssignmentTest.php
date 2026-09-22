<?php

use App\Models\Admin;
use App\Models\DosenPembimbing;
use App\Models\FormPengajuanMagang;
use App\Models\FullMultiplicativeForm;
use App\Models\KriteriaBidangIndustri;
use App\Models\KriteriaJenisMagang;
use App\Models\KriteriaLokasiMagang;
use App\Models\KriteriaOpenRemote;
use App\Models\KriteriaPekerjaan;
use App\Models\LowonganMagang;
use App\Models\Mahasiswa;
use App\Models\Perusahaan;
use App\Models\RatioSystem;
use App\Models\ReferencePoint;
use App\Models\FinalRankRecommendation;

/**
 * Mass-assignment hardening: sensitive fields must NOT be fillable.
 * Each assertion fills the field through the guarded API and expects it
 * to be silently discarded (value stays default/null).
 */
dataset('sensitive fills', [
    'Mahasiswa.password' => [fn () => new Mahasiswa(['password' => 'hacked']), 'password'],
    'Mahasiswa.status_magang' => [fn () => new Mahasiswa(['status_magang' => 'selesai magang']), 'status_magang'],
    'Admin.password' => [fn () => new Admin(['password' => 'hacked']), 'password'],
    'DosenPembimbing.password' => [fn () => new DosenPembimbing(['password' => 'hacked']), 'password'],
    'FormPengajuanMagang.status' => [fn () => new FormPengajuanMagang(['status' => 'diterima']), 'status'],
    'LowonganMagang.status' => [fn () => new LowonganMagang(['status' => 'tutup']), 'status'],
    'RatioSystem.score' => [fn () => new RatioSystem(['score' => 999]), 'score'],
    'RatioSystem.rank' => [fn () => new RatioSystem(['rank' => 999]), 'rank'],
    'ReferencePoint.rank' => [fn () => new ReferencePoint(['rank' => 999]), 'rank'],
    'FullMultiplicativeForm.score' => [fn () => new FullMultiplicativeForm(['score' => 999]), 'score'],
    'FinalRankRecommendation.rank' => [fn () => new FinalRankRecommendation(['rank' => 999]), 'rank'],
    'FinalRankRecommendation.avg_rank' => [fn () => new FinalRankRecommendation(['avg_rank' => 999]), 'avg_rank'],
    'Perusahaan.rating' => [fn () => new Perusahaan(['rating' => 5]), 'rating'],
    'KriteriaPekerjaan.bobot' => [fn () => new KriteriaPekerjaan(['bobot' => 999]), 'bobot'],
    'KriteriaPekerjaan.rank' => [fn () => new KriteriaPekerjaan(['rank' => 999]), 'rank'],
    'KriteriaBidangIndustri.bobot' => [fn () => new KriteriaBidangIndustri(['bobot' => 999]), 'bobot'],
    'KriteriaJenisMagang.bobot' => [fn () => new KriteriaJenisMagang(['bobot' => 999]), 'bobot'],
    'KriteriaLokasiMagang.bobot' => [fn () => new KriteriaLokasiMagang(['bobot' => 999]), 'bobot'],
    'KriteriaOpenRemote.bobot' => [fn () => new KriteriaOpenRemote(['bobot' => 999]), 'bobot'],
]);

it('discards the sensitive field on mass assignment', function (Closure $factory, string $field) {
    $model = $factory();

    expect($model->getAttribute($field))->toBeNull();
})->with('sensitive fills');
