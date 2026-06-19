<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Advertisements\Advertisement;

it('merges the package config', function (): void {
    expect(config('advertisements.model'))->toBe(Advertisement::class);
});

it('registers the migrations so the table exists', function (): void {
    expect(Schema::hasTable('advertisements'))->toBeTrue();
});
