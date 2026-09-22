<?php

use App\Models\EncodedAlternatives;
use App\Models\LowonganMagang;
use App\Models\Pekerjaan;
use App\Models\Perusahaan;
use Illuminate\Database\Eloquent\Relations\HasMany;

/*
|--------------------------------------------------------------------------
| Model naming / relation conventions
|--------------------------------------------------------------------------
*/

it('exposes kriteriaPekerjaan (not the kriteriPekerjaan typo)', function () {
    expect(method_exists(Pekerjaan::class, 'kriteriaPekerjaan'))->toBeTrue()
        ->and(method_exists(Pekerjaan::class, 'kriteriPekerjaan'))->toBeFalse()
        ->and((new Pekerjaan())->kriteriaPekerjaan())->toBeInstanceOf(HasMany::class);
});

it('uses camelCase lokasiMagang relation on LowonganMagang', function () {
    expect(method_exists(LowonganMagang::class, 'lokasiMagang'))->toBeTrue()
        ->and(method_exists(LowonganMagang::class, 'lokasi_magang'))->toBeFalse();
});

it('uses camelCase lowonganMagang relation on Perusahaan', function () {
    expect(method_exists(Perusahaan::class, 'lowonganMagang'))->toBeTrue()
        ->and(method_exists(Perusahaan::class, 'lowongan_magang'))->toBeFalse();
});

it('declares the encoded_alternatives table', function () {
    expect((new EncodedAlternatives())->getTable())->toBe('encoded_alternatives');
});
