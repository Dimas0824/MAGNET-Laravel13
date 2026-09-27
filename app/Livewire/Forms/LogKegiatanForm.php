<?php

namespace App\Livewire\Forms;

use Livewire\Attributes\Validate;
use Livewire\Form;

/**
 * W0-T06b — daily-log ("log kegiatan magang") validation extracted from the
 * Volt log pages.
 *
 * The rules below are a 1:1 copy/merge of `rules([...])` in:
 *   - resources/views/pages/mahasiswa/tambah-log.blade.php L27-32  (create)
 *   - resources/views/pages/mahasiswa/log-magang.blade.php  L27-32  (edit)
 *
 * Keep them in sync until the pages are rewired to consume this form object
 * (later task).
 *
 * This object ONLY validates and holds data — persistence (LogMagang::create /
 * update) stays in a later Action task.
 */
class LogKegiatanForm extends Form
{
    // tanggal: required|date|before_or_equal:today  (tambah-log L28) / required|date (log-magang L28)
    #[Validate(['required', 'date', 'before_or_equal:today'])]
    public string $tanggal = '';

    // jam_masuk: required (tambah-log L29) / required|date_format:H:i (log-magang L29)
    #[Validate(['required', 'date_format:H:i'])]
    public string $jam_masuk = '';

    // jam_keluar: required|after:jam_masuk (tambah-log L30) / required|date_format:H:i|after:editData.jam_masuk (log-magang L30)
    #[Validate(['required', 'date_format:H:i', 'after:jam_masuk'])]
    public string $jam_keluar = '';

    // kegiatan: required|min:10|max:1000 (tambah-log L31) / required|string|min:10|max:1000 (log-magang L31)
    #[Validate(['required', 'string', 'min:10', 'max:1000'])]
    public string $kegiatan = '';

    /**
     * Hydrate the form from a plain array (e.g. a request payload or the
     * page's state). Unknown keys are ignored on purpose.
     */
    public function fillFrom(array $data): void
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }
}
