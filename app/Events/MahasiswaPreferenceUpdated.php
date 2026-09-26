<?php

namespace App\Events;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Mahasiswa;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MahasiswaPreferenceUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Mahasiswa $mahasiswa;

    /**
     * The tenant the change was made under. Captured at dispatch time so the
     * queued pipeline listener can restore it on a worker where no request
     * (and therefore no tenant) is available. Falls back to the mahasiswa's
     * own tenant when the container has none bound.
     */
    public ?int $tenantId;

    /**
     * Create a new event instance.
     */
    public function __construct(Mahasiswa $mahasiswa, ?int $tenantId = null)
    {
        $this->mahasiswa = $mahasiswa;
        $this->tenantId = $tenantId
            ?? $mahasiswa->tenant_id
            ?? BelongsToTenant::currentTenant()?->id;
    }
}
