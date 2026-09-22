<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests boot the full application and run against a real MySQL
| database (db_magnet_test), refreshing the schema before each test.
| Unit tests run in isolation without a database unless they opt in.
|
*/

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * Authenticate as a mahasiswa for feature tests.
 */
function actingAsMahasiswa(\App\Models\Mahasiswa $mahasiswa): \App\Models\Mahasiswa
{
    test()->actingAs($mahasiswa, 'mahasiswa');

    return $mahasiswa;
}

/**
 * Authenticate as a dosen for feature tests.
 */
function actingAsDosen(\App\Models\DosenPembimbing $dosen): \App\Models\DosenPembimbing
{
    test()->actingAs($dosen, 'dosen');

    return $dosen;
}

/**
 * Authenticate as an admin for feature tests.
 */
function actingAsAdmin(\App\Models\Admin $admin): \App\Models\Admin
{
    test()->actingAs($admin, 'admin');

    return $admin;
}
