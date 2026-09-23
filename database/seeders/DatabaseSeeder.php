<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Uses the deterministic DemoSeeder so all records are explicitly linked
     * (mahasiswa -> preferences -> contract -> logs/reviews/chat), instead of
     * independent random factories whose foreign keys do not connect.
     */
    public function run(): void
    {
        $this->call(DemoSeeder::class);
    }
}
