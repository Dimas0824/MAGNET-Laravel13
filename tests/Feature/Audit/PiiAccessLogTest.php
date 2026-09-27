<?php

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\BerkasPengajuanMagang;
use App\Models\Mahasiswa;
use Illuminate\Support\Facades\Storage;

/**
 * P6-T3: downloading a PII document (cv/transkrip/portfolio) writes an
 * `accessed` audit row — WHO read WHICH student's document and when. A denied
 * request (403/404) must NOT write an accessed row.
 */
beforeEach(function () {
    seedMasterData();
    Storage::fake('private');
    Storage::fake('public');
});

it('writes an accessed audit row when an authorized user downloads a document', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $berkas = BerkasPengajuanMagang::create([
        'mahasiswa_id' => $mahasiswa->id,
        'cv' => 'cv/test.pdf',
        'transkrip_nilai' => 'tr/test.pdf',
        'portfolio' => null,
    ]);
    Storage::disk('private')->put('cv/test.pdf', 'PDFDATA');

    AuditLog::query()->delete();

    $this->actingAs($mahasiswa, 'mahasiswa')
        ->get(route('berkas.download', ['berkas' => $berkas->id, 'type' => 'cv']))
        ->assertOk();

    $log = AuditLog::where('auditable_type', BerkasPengajuanMagang::class)
        ->where('auditable_id', $berkas->id)
        ->where('event', AuditLog::EVENT_ACCESSED)
        ->first();

    expect($log)->not->toBeNull();
});

it('writes no accessed row when access is denied', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $berkas = BerkasPengajuanMagang::create([
        'mahasiswa_id' => $mahasiswa->id,
        'cv' => 'cv/secret.pdf',
        'transkrip_nilai' => 'tr/secret.pdf',
        'portfolio' => null,
    ]);
    Storage::disk('private')->put('cv/secret.pdf', 'PDFDATA');

    $other = Mahasiswa::factory()->create();

    AuditLog::query()->delete();

    $this->actingAs($other, 'mahasiswa')
        ->get(route('berkas.download', ['berkas' => $berkas->id, 'type' => 'cv']))
        ->assertForbidden();

    expect(AuditLog::where('event', AuditLog::EVENT_ACCESSED)->count())->toBe(0);
});
