<?php

use App\Models\Admin;
use App\Models\DosenPembimbing;
use App\Models\Mahasiswa;

beforeEach(function () {
    seedMasterData();
});

/*
|--------------------------------------------------------------------------
| Guest routes
|--------------------------------------------------------------------------
*/

it('serves the landing page to guests', function () {
    $this->get('/')->assertOk();
});

it('redirects unauthenticated users from dashboard to login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('redirects unauthenticated users from mahasiswa routes to login', function () {
    $this->get(route('mahasiswa.riwayat-rekomendasi'))->assertRedirect(route('login'));
});

it('redirects unauthenticated users from admin routes to login', function () {
    $this->get(route('admin.data-mahasiswa'))->assertRedirect(route('login'));
});

it('redirects unauthenticated users from dosen routes to login', function () {
    $this->get(route('dosen.mahasiswa-bimbingan'))->assertRedirect(route('login'));
});

/*
|--------------------------------------------------------------------------
| Multi-guard authentication
|--------------------------------------------------------------------------
*/

it('authenticates a mahasiswa via the mahasiswa guard', function () {
    $mahasiswa = Mahasiswa::factory()->create();

    $this->actingAs($mahasiswa, 'mahasiswa');

    expect(auth('mahasiswa')->check())->toBeTrue();
    $this->get(route('dashboard'))->assertOk();
});

it('authenticates a dosen via the dosen guard', function () {
    $dosen = DosenPembimbing::factory()->create();

    $this->actingAs($dosen, 'dosen');

    expect(auth('dosen')->check())->toBeTrue();
    $this->get(route('dashboard'))->assertOk();
});

it('authenticates an admin via the admin guard', function () {
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin');

    expect(auth('admin')->check())->toBeTrue();
    $this->get(route('dashboard'))->assertOk();
});

/*
|--------------------------------------------------------------------------
| Role enforcement (CheckRole middleware)
|--------------------------------------------------------------------------
*/

it('forbids a mahasiswa from admin-only routes', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $this->actingAs($mahasiswa, 'mahasiswa');

    // CheckRole redirects to login when no allowed guard is authenticated.
    $this->get(route('admin.data-mahasiswa'))->assertRedirect(route('login'));
});

it('forbids a mahasiswa from dosen-only routes', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $this->actingAs($mahasiswa, 'mahasiswa');

    $this->get(route('dosen.mahasiswa-bimbingan'))->assertRedirect(route('login'));
});

it('forbids a dosen from admin-only routes', function () {
    $dosen = DosenPembimbing::factory()->create();
    $this->actingAs($dosen, 'dosen');

    $this->get(route('admin.data-mahasiswa'))->assertRedirect(route('login'));
});

it('allows an admin into admin routes', function () {
    $admin = Admin::factory()->create();
    $this->actingAs($admin, 'admin');

    $this->get(route('admin.data-mahasiswa'))->assertOk();
});

it('allows a dosen into dosen routes', function () {
    $dosen = DosenPembimbing::factory()->create();
    $this->actingAs($dosen, 'dosen');

    $this->get(route('dosen.mahasiswa-bimbingan'))->assertOk();
});

it('allows a mahasiswa into mahasiswa routes', function () {
    $mahasiswa = Mahasiswa::factory()->create();
    $this->actingAs($mahasiswa, 'mahasiswa');

    $this->get(route('mahasiswa.hasil-pencarian'))->assertOk();
});
