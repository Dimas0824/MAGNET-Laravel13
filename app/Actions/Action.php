<?php

namespace App\Actions;

/**
 * Marker interface for single-purpose Action classes (verb-noun names, one
 * public handle() method). Actions coordinate a business operation and may
 * call Services; they never accept HTTP Request objects.
 */
interface Action
{
    public function handle(): mixed;
}
