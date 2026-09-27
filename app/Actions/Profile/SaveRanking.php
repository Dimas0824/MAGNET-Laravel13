<?php

namespace App\Actions\Profile;

use App\Actions\Action;
use App\Models\Mahasiswa;
use App\Services\MahasiswaPreferenceService;

/**
 * W4-T01 — persist criteria ranking priorities from the profile page.
 *
 * Thin Action wrapper around App\Services\MahasiswaPreferenceService::saveRanking(),
 * the W0-T05b extraction that already reproduces the page's `$saveRanking`
 * behavior exactly: each key at index i gets rank i+1 and its ROC weight, writes
 * via firstOrNew()->forceFill()->save() inside BaseKriteriaModel::withoutEvents(),
 * then `refresh()` and EXACTLY ONE MahasiswaPreferenceUpdated event.
 */
class SaveRanking implements Action
{
    public function __construct(
        private readonly MahasiswaPreferenceService $preferences = new MahasiswaPreferenceService,
    ) {}

    /**
     * @param  array<int, string>  $orderedKeys  criteria keys in priority order
     */
    public function handle(?Mahasiswa $mahasiswa = null, array $orderedKeys = []): mixed
    {
        if (! $mahasiswa) {
            return null;
        }

        $this->preferences->saveRanking($mahasiswa, $orderedKeys);

        return null;
    }
}
