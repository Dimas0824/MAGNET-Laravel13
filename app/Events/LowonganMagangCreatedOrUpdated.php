<?php

namespace App\Events;

use App\Models\LowonganMagang;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LowonganMagangCreatedOrUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public LowonganMagang $lowonganMagang;

    /**
     * Create a new event instance.
     */
    public function __construct(LowonganMagang $lowonganMagang)
    {
        $this->lowonganMagang = $lowonganMagang;
    }
}
