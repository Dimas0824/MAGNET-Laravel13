<?php

/*
|--------------------------------------------------------------------------
| Guest pages smoke tests
|--------------------------------------------------------------------------
*/

it('serves every guest page', function (string $route) {
    $this->get(route($route))->assertOk();
})->with([
    'guest.landing-page',
    'guest.pengembang',
    'guest.tata-tertib',
    'guest.cara-magang',
    'guest.tips-memilih-magang',
]);

it('returns the health check endpoint', function () {
    $this->get('/up')->assertOk();
});

it('returns a 404 for an unknown route', function () {
    $this->get('/this-route-does-not-exist')->assertNotFound();
});
