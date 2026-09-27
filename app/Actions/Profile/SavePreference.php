<?php

namespace App\Actions\Profile;

use App\Actions\Action;
use App\Models\Mahasiswa;
use App\Services\MahasiswaPreferenceService;

/**
 * W4-T01 — save the five criteria preferences from the profile page.
 *
 * Thin Action wrapper around App\Services\MahasiswaPreferenceService::savePreferences(),
 * the W0-T05b extraction that already reproduces the page's `$saveNewPreference`
 * behavior exactly:
 *  - names/kategori resolved to ids (same lookup + RuntimeException messages),
 *  - writes via firstOrNew()->forceFill()->save() inside
 *    BaseKriteriaModel::withoutEvents() (never a Builder ::update()),
 *  - explicit `updated_at => now()`,
 *  - `refresh()` then EXACTLY ONE MahasiswaPreferenceUpdated event.
 */
class SavePreference implements Action
{
    public function __construct(
        private readonly MahasiswaPreferenceService $preferences = new MahasiswaPreferenceService,
    ) {}

    /**
     * @param  array{pekerjaan: string, bidang_industri: string, lokasi_magang: string, jenis_magang: mixed, open_remote: mixed}  $input
     *
     * @throws \RuntimeException with the same user-facing messages the page threw
     */
    public function handle(?Mahasiswa $mahasiswa = null, array $input = []): mixed
    {
        if (! $mahasiswa) {
            return null;
        }

        $this->preferences->savePreferences($mahasiswa, $input);

        return null;
    }
}
