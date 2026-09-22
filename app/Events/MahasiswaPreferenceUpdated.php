<?php

namespace App\Events;

use App\Models\Mahasiswa;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MahasiswaPreferenceUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Mahasiswa $mahasiswa;

    /**
     * Create a new event instance.
     */
    public function __construct(Mahasiswa $mahasiswa)
    {
        $this->mahasiswa = $mahasiswa;
    }
}
