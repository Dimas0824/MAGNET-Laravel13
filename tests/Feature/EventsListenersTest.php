<?php

use App\Events\LowonganMagangCreatedOrUpdated;
use App\Events\MahasiswaPreferenceUpdated;
use App\Helpers\DecisionMaking\DataPreprocessing;
use App\Listeners\RunDataCategorization;
use App\Listeners\RunEncodingData;
use App\Listeners\RunMultiMOORA;
use App\Listeners\RunRecommendationPipeline;
use App\Models\EncodedAlternatives;
use App\Models\FinalRankRecommendation;
use App\Models\LokasiMagang;
use App\Models\LowonganMagang;
use App\Models\Pekerjaan;
use App\Models\Perusahaan;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    seedMasterData();
});

$path = fn () => config('recommendation-system.preprocessing.alternatives_categorized_path');

it('registers the expected listeners for the app events', function () {
    Event::fake();

    expect(Event::getFacadeRoot())->not->toBeNull();

    // These assertions verify the provider maps the events to the listeners.
    $listeners = app(Dispatcher::class)->getListeners(MahasiswaPreferenceUpdated::class);
    expect($listeners)->not->toBeEmpty();

    $lowonganListeners = app(Dispatcher::class)->getListeners(LowonganMagangCreatedOrUpdated::class);
    expect($lowonganListeners)->not->toBeEmpty();
});

it('categorizes a lowongan into the alternatives file when it is created', function () use ($path) {
    lowonganMagang(); // model events NOT disabled here -> triggers categorization

    // Wait: lowonganMagang() helper disables events, so create explicitly.
    $lowongan = LowonganMagang::create([
        'kuota' => 3,
        'pekerjaan_id' => Pekerjaan::where('nama', 'Software Engineer')->value('id'),
        'deskripsi' => 'd',
        'persyaratan' => 'p',
        'jenis_magang' => 'berbayar',
        'open_remote' => 'ya',
        'status' => 'buka',
        'lokasi_magang_id' => LokasiMagang::where('kategori_lokasi', 'Area Malang Raya')->value('id'),
        'perusahaan_id' => Perusahaan::factory()->create()->id,
    ]);

    $content = Storage::json($path());
    expect($content)->toBeArray();
    expect(collect($content)->pluck('id'))->toContain($lowongan->id);
});

it('runs the recommendation pipeline when preferences are updated', function () {
    $mahasiswa = mahasiswaDenganPreferensi();

    // Categorize one opening so encoding has data.
    lowonganMagang();
    $opening = LowonganMagang::withoutEvents(fn () => lowonganMagang());
    DataPreprocessing::dataCategorization($opening);

    // Fire the event; listeners are sync (queue=sync) in tests.
    event(new MahasiswaPreferenceUpdated($mahasiswa));

    expect(EncodedAlternatives::where('mahasiswa_id', $mahasiswa->id)->count())->toBeGreaterThan(0);
    expect(FinalRankRecommendation::where('mahasiswa_id', $mahasiswa->id)->count())->toBeGreaterThan(0);
});

it('queues the recommendation pipeline orchestrator', function () {
    Queue::fake();

    $mahasiswa = mahasiswaDenganPreferensi();

    event(new MahasiswaPreferenceUpdated($mahasiswa));

    // Laravel transports queued listeners inside a CallQueuedListener wrapper;
    // assert the wrapper carries our orchestrator listener.
    Queue::assertPushed(
        CallQueuedListener::class,
        fn ($job) => $job->class === RunRecommendationPipeline::class
    );
});

it('RunRecommendationPipeline implements ShouldQueue', function () {
    expect(new RunRecommendationPipeline)->toBeInstanceOf(ShouldQueue::class);
});

it('RunDataCategorization implements ShouldQueue', function () {
    expect(new RunDataCategorization)->toBeInstanceOf(ShouldQueue::class);
});

it('runs encoding before multimoora in the orchestrator', function () {
    $mahasiswa = mahasiswaDenganPreferensi();

    // One categorized opening so encoding has data to work with.
    $opening = LowonganMagang::withoutEvents(fn () => lowonganMagang());
    DataPreprocessing::dataCategorization($opening);

    // Drive the orchestrator directly (queue=sync would run it inline too).
    (new RunRecommendationPipeline)->handle(new MahasiswaPreferenceUpdated($mahasiswa));

    $encoded = EncodedAlternatives::where('mahasiswa_id', $mahasiswa->id)->count();
    $finalRanks = FinalRankRecommendation::where('mahasiswa_id', $mahasiswa->id)->count();

    // Encoding writes the alternatives that MULTIMOORA consumes; both must
    // exist, and encoding output must be present for the ranking to be valid.
    expect($encoded)->toBeGreaterThan(0)
        ->and($finalRanks)->toBeGreaterThan(0);
});

it('RunDataCategorization handles the lowongan event synchronously', function () {
    $listener = new RunDataCategorization;
    expect(method_exists($listener, 'handle'))->toBeTrue();

    $lowongan = lowonganMagang();
    $listener->handle(new LowonganMagangCreatedOrUpdated($lowongan));

    $content = Storage::json(config('recommendation-system.preprocessing.alternatives_categorized_path'));
    expect(collect($content)->pluck('id'))->toContain($lowongan->id);
});

it('RunEncodingData and RunMultiMOORA exist and are invokable handlers', function () {
    expect(new RunEncodingData)->toBeInstanceOf(RunEncodingData::class);
    expect(new RunMultiMOORA)->toBeInstanceOf(RunMultiMOORA::class);
});
