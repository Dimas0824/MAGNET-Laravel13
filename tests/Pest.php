<?php

use App\Models\Admin;
use App\Models\DosenPembimbing;
use App\Models\Mahasiswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/Helpers.php';

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
function actingAsMahasiswa(Mahasiswa $mahasiswa): Mahasiswa
{
    test()->actingAs($mahasiswa, 'mahasiswa');

    return $mahasiswa;
}

/**
 * Authenticate as a dosen for feature tests.
 */
function actingAsDosen(DosenPembimbing $dosen): DosenPembimbing
{
    test()->actingAs($dosen, 'dosen');

    return $dosen;
}

/**
 * Authenticate as an admin for feature tests.
 */
function actingAsAdmin(Admin $admin): Admin
{
    test()->actingAs($admin, 'admin');

    return $admin;
}
