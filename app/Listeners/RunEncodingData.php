<?php

namespace App\Listeners;

use App\Events\MahasiswaPreferenceUpdated;
use App\Helpers\DecisionMaking\DataPreprocessing;

class RunEncodingData
{
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
    public function handle(MahasiswaPreferenceUpdated $event): void
    {
        DataPreprocessing::dataEncoding($event->mahasiswa);
    }
}
