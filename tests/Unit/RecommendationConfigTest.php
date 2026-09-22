<?php

/*
|--------------------------------------------------------------------------
| Config / test-infrastructure hygiene
|--------------------------------------------------------------------------
*/

it('removed the empty multimoora config block', function () {
    expect(config('recommendation-system.multimoora'))->toBeNull();
});

it('keeps the roc total_criteria config', function () {
    expect(config('recommendation-system.roc.total_criteria'))->toBe(5);
});

it('documents MAILGUN and POSTMARK env keys in .env.example', function () {
    $env = file_get_contents(base_path('.env.example'));

    expect($env)->toContain('MAILGUN_DOMAIN')
        ->and($env)->toContain('MAILGUN_SECRET')
        ->and($env)->toContain('POSTMARK_TOKEN');
});

it('targets the phpunit 12 schema', function () {
    $xml = file_get_contents(base_path('phpunit.xml'));

    expect($xml)->toContain('schema.phpunit.de/12.0/phpunit.xsd');
});

it('does not implement ShouldBroadcast on the domain events', function () {
    expect(class_implements(App\Events\LowonganMagangCreatedOrUpdated::class))
        ->not->toHaveKey(Illuminate\Contracts\Broadcasting\ShouldBroadcast::class)
        ->and(class_implements(App\Events\MahasiswaPreferenceUpdated::class))
        ->not->toHaveKey(Illuminate\Contracts\Broadcasting\ShouldBroadcast::class);
});
