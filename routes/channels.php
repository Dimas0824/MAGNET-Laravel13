<?php

use App\Models\DosenPembimbing;
use App\Models\KontrakMagang;
use App\Models\Mahasiswa;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| The app runs three independent session guards (mahasiswa, dosen, admin)
| and the default guard is `mahasiswa`. Without an explicit `guards` option
| the broadcaster resolves the subscriber from the default guard only, so a
| logged-in dosen is treated as a guest and /broadcasting/auth returns 403 —
| the dosen subscribes to nothing and chat never updates live. Declaring the
| guards here makes the framework resolve whichever guard is authenticated.
|
*/

Broadcast::channel('chat.{kontrakMagangId}', function ($user, int $kontrakMagangId) {
    $kontrak = KontrakMagang::find($kontrakMagangId);

    if (! $kontrak) {
        return false;
    }

    if ($user instanceof Mahasiswa) {
        return $user->id === $kontrak->mahasiswa_id
            ? ['id' => $user->id, 'role' => 'mahasiswa']
            : false;
    }

    if ($user instanceof DosenPembimbing) {
        return $user->id === $kontrak->dosen_id
            ? ['id' => $user->id, 'role' => 'dosen']
            : false;
    }

    // Admins are authenticated but are not participants of a chat, so they
    // are not granted access to its private channel.
    return false;
}, ['guards' => ['mahasiswa', 'dosen', 'admin']]);
