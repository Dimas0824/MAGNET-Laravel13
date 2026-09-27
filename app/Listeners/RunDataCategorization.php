<?php

namespace App\Listeners;

use App\Events\LowonganMagangCreatedOrUpdated;
use App\Helpers\DecisionMaking\DataPreprocessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class RunDataCategorization implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(LowonganMagangCreatedOrUpdated $event): void
    {
        DataPreprocessing::dataCategorization($event->lowonganMagang);
    }
}
