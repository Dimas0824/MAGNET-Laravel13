<?php

use App\Livewire\Forms\LogKegiatanForm;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/*
|--------------------------------------------------------------------------
| LogKegiatanForm — validation parity with the daily-log pages
|--------------------------------------------------------------------------
|
| W0-T06b: the daily-log ("log kegiatan magang") validation is being
| extracted from two Volt pages into one Livewire form object:
|
|   - resources/views/pages/mahasiswa/tambah-log.blade.php L27-32  (create)
|   - resources/views/pages/mahasiswa/log-magang.blade.php  L27-32  (edit)
|
| This suite pins the EXACT rules so both pages and the form can never drift
| apart. No database access is required (no unique/exists rules), so this
| stays a pure Unit test.
|
*/

/**
 * A bare Livewire component used purely as the host for the form object.
 * Livewire form objects are validated through their owning component, so a
 * throwaway host lets us exercise LogKegiatanForm in isolation without wiring
 * up the real pages (that is a later task).
 */
class LogKegiatanFormHost extends Component
{
    public LogKegiatanForm $form;

    public function render()
    {
        return <<<'HTML'
        <div></div>
        HTML;
    }
}

/**
 * Return a complete, valid payload mirroring the log page's happy path.
 */
function validLogPayload(): array
{
    return [
        'tanggal' => now()->toDateString(),
        'jam_masuk' => '08:00',
        'jam_keluar' => '17:00',
        'kegiatan' => 'Mengerjakan modul laporan harian magang.',
    ];
}

/**
 * Build the form inside its host component through the Livewire test harness.
 */
function logKegiatanForm(): LogKegiatanForm
{
    return Livewire::test(LogKegiatanFormHost::class)->instance()->form;
}

/*
|--------------------------------------------------------------------------
| Green path
|--------------------------------------------------------------------------
*/

it('passes valid log data', function () {
    $form = logKegiatanForm();
    $form->fillFrom(validLogPayload());

    // Should not throw...
    $validated = $form->validate();

    expect($validated)->toBeArray()
        ->and($validated['jam_masuk'])->toBe('08:00')
        ->and($validated['kegiatan'])->toBe('Mengerjakan modul laporan harian magang.');
});

/*
|--------------------------------------------------------------------------
| Required fields
|--------------------------------------------------------------------------
*/

it('fails when kegiatan is empty', function () {
    $form = logKegiatanForm();
    $form->fillFrom([...validLogPayload(), 'kegiatan' => '']);

    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class);
});

/*
|--------------------------------------------------------------------------
| Full rule parity with the pages
|--------------------------------------------------------------------------
|
| One assertion per rule copied from the page `rules([...])` blocks.
|
*/

it('enforces the same rules as the pages', function () {
    // kegiatan: required|string|min:10|max:1000
    $form = logKegiatanForm();
    $form->fillFrom([...validLogPayload(), 'kegiatan' => 'pendek']);
    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class, null, 'kegiatan min:10 failed');

    $form = logKegiatanForm();
    $form->fillFrom([...validLogPayload(), 'kegiatan' => str_repeat('a', 1001)]);
    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class, null, 'kegiatan max:1000 failed');

    // tanggal: required|date|before_or_equal:today
    $form = logKegiatanForm();
    $form->fillFrom([...validLogPayload(), 'tanggal' => 'bukan-tanggal']);
    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class, null, 'tanggal date failed');

    $form = logKegiatanForm();
    $form->fillFrom([...validLogPayload(), 'tanggal' => now()->addDay()->toDateString()]);
    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class, null, 'tanggal before_or_equal:today failed');

    // jam_masuk: required|date_format:H:i
    $form = logKegiatanForm();
    $form->fillFrom([...validLogPayload(), 'jam_masuk' => '8 pagi']);
    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class, null, 'jam_masuk date_format:H:i failed');

    // jam_keluar: required|date_format:H:i|after:jam_masuk
    $form = logKegiatanForm();
    $form->fillFrom([...validLogPayload(), 'jam_keluar' => '07:00']);
    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class, null, 'jam_keluar after:jam_masuk failed');
});

/*
|--------------------------------------------------------------------------
| Invalid time format (explicit)
|--------------------------------------------------------------------------
*/

it('fails on invalid time format', function () {
    $form = logKegiatanForm();
    $form->fillFrom([...validLogPayload(), 'jam_masuk' => '25:00']);

    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class);
});
