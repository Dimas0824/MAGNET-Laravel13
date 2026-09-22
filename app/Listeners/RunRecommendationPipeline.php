<?php

namespace App\Listeners;

use App\Events\MahasiswaPreferenceUpdated;
use App\Helpers\DecisionMaking\DataPreprocessing;
use App\Helpers\DecisionMaking\MultiMOORA;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Ordered orchestrator for the recommendation pipeline.
 *
 * Encoding MUST run before MULTIMOORA consumes the encoded alternatives.
 * Registering the two steps as separate queued listeners gives no ordering
 * guarantee, so a single queued listener performs both steps in sequence.
 */
class RunRecommendationPipeline implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    /**
     * The number of times the queued listener may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the queued listener may run.
     */
    public int $timeout = 120;

    /**
     * The queue the listener should be dispatched to.
     */
    public string $queue = 'default';

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

        (new MultiMOORA($event->mahasiswa))->computeMultiMOORA();
    }
}
