<?php

use App\Models\KontrakMagang;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

Broadcast::channel('chat.{kontrakMagangId}', function ($user, int $kontrakMagangId) {
    $kontrak = KontrakMagang::find($kontrakMagangId);

    if (! $kontrak) {
        return false;
    }

    $mahasiswaId = auth('mahasiswa')->id();
    if ($mahasiswaId && $mahasiswaId === $kontrak->mahasiswa_id) {
        return ['id' => $mahasiswaId, 'role' => 'mahasiswa'];
    }

    $dosenId = auth('dosen')->id();
    if ($dosenId && $dosenId === $kontrak->dosen_id) {
        return ['id' => $dosenId, 'role' => 'dosen'];
    }

    return false;
});
