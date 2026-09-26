<?php

namespace Tests;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The tenant scope memoizes the default tenant per request lifecycle.
        // Tests refresh the schema between runs, so the memo must not leak
        // across tests: a stale tenant id would hide every freshly-seeded row.
        BelongsToTenant::forgetResolvedTenant();
    }
}
