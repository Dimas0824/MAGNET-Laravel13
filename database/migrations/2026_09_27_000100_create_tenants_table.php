<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant registry — single tenant today, multi-tenant-ready.
 *
 * A ULID `public_id` is the externally-exposed identifier (safe to serialize);
 * the integer `id` stays the internal FK target (see ADR 03/05).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 60)->unique();
            $table->char('public_id', 26)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
