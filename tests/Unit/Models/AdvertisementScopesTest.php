<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Tests\User;

beforeEach(function (): void {
    Carbon::setTestNow('2024-05-01 09:00:00');

    $this->author = User::create();

    $this->draft = Advertisement::factory()->create(['name' => 'draft ad']);
    $this->scheduled = Advertisement::factory()->scheduled()->create(['name' => 'scheduled ad']);
    $this->live = Advertisement::factory()->published()->create([
        'name' => 'live ad',
        'expires_at' => now()->addWeek(),
        'author_type' => $this->author->getMorphClass(),
        'author_id' => $this->author->getKey(),
    ]);
    $this->expired = Advertisement::factory()->expired()->create(['name' => 'expired ad']);
    $this->archived = Advertisement::factory()->published()->archived()->create(['name' => 'archived ad']);
});

afterEach(fn () => Carbon::setTestNow());

it('scopes published advertisements', function (): void {
    $names = Advertisement::query()->published()->pluck('name')->all();

    expect($names)->toContain('live ad', 'expired ad')
        ->not->toContain('draft ad', 'scheduled ad', 'archived ad');
});

it('scopes active advertisements', function (): void {
    $names = Advertisement::query()->active()->pluck('name')->all();

    expect($names)->toBe(['live ad']);
});

it('scopes scheduled advertisements', function (): void {
    $names = Advertisement::query()->scheduled()->pluck('name')->all();

    expect($names)->toBe(['scheduled ad']);
});

it('scopes expired advertisements', function (): void {
    $names = Advertisement::query()->expired()->pluck('name')->all();

    expect($names)->toBe(['expired ad']);
});

it('scopes draft advertisements', function (): void {
    $names = Advertisement::query()->draft()->pluck('name')->all();

    expect($names)->toBe(['draft ad']);
});

it('scopes archived advertisements', function (): void {
    $names = Advertisement::query()->archived()->pluck('name')->all();

    expect($names)->toBe(['archived ad']);
});

it('scopes advertisements for a given author', function (): void {
    $names = Advertisement::query()->forAuthor($this->author)->pluck('name')->all();

    expect($names)->toBe(['live ad']);
});
