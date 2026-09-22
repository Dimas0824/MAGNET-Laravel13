<?php

use App\Http\Middleware\CheckRole;
use App\Models\Admin;
use App\Models\DosenPembimbing;
use App\Models\Mahasiswa;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    seedMasterData();
});

function runCheckRole(array $roles, array $authenticated = []): Response|string
{
    foreach ($authenticated as $guard => $user) {
        test()->actingAs($user, $guard);
    }

    $middleware = new CheckRole();
    $request = Request::create('/protected');

    return $middleware->handle($request, fn () => new Response('OK'), ...$roles);
}

it('allows access when one of the allowed guards is authenticated', function () {
    $admin = Admin::factory()->create();
    $response = runCheckRole(['admin'], ['admin' => $admin]);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->getContent())->toBe('OK');
});

it('allows access when any listed guard is authenticated', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $response = runCheckRole(['admin', 'mahasiswa', 'dosen'], ['mahasiswa' => $mahasiswa]);

    expect($response)->toBeInstanceOf(Response::class);
});

it('redirects to login when no allowed guard is authenticated', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $response = runCheckRole(['admin'], ['mahasiswa' => $mahasiswa]);

    expect($response)->toBeInstanceOf(Illuminate\Http\RedirectResponse::class)
        ->and($response->getTargetUrl())->toContain('/login');
});

it('redirects guests to login', function () {
    $response = runCheckRole(['admin', 'dosen', 'mahasiswa']);

    expect($response)->toBeInstanceOf(Illuminate\Http\RedirectResponse::class)
        ->and($response->getTargetUrl())->toContain('/login');
});
