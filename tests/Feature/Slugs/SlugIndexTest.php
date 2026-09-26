<?php

declare(strict_types=1);

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\Placement;
use RoundlyConsulting\Testing\Database\DriverMatrix;

/**
 * The per-locale uniqueness is enforced by the database, not only by the probe: a raw insert
 * that bypasses the model (and so every slug hook) must still be refused. On SQLite and
 * Postgres the index is an expression index on `slug->{locale}`; this runs on whichever
 * engine the environment declares, so the pgsql leg proves the jsonb form.
 */
function insertAdvertisementRow(array $slug): void
{
    DB::table('advertisements')->insert([
        'name' => json_encode(['en' => 'Raw']),
        'slug' => json_encode($slug),
        'status' => 'draft',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('refuses a duplicate slug within one locale at the database', function (): void {
    insertAdvertisementRow(['en' => 'raw-row']);

    expect(fn () => insertAdvertisementRow(['en' => 'raw-row']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('refuses a duplicate slug in a secondary supported locale at the database', function (): void {
    insertAdvertisementRow(['en' => 'first', 'de' => 'gleich']);

    expect(fn () => insertAdvertisementRow(['en' => 'second', 'de' => 'gleich']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('accepts the same slug in two different locales at the database', function (): void {
    insertAdvertisementRow(['en' => 'bike']);
    insertAdvertisementRow(['de' => 'bike']);

    expect(DB::table('advertisements')->count())->toBe(2);
});

it('refuses a duplicate placement slug at the database', function (): void {
    Placement::factory()->create(['slug' => 'sidebar']);

    expect(fn () => DB::table('placements')->insert([
        'slug' => 'sidebar',
        'name' => json_encode(['en' => 'Sidebar']),
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('names the indexes the way sluggable manages them', function (): void {
    $indexes = collect(DB::connection()->getSchemaBuilder()->getIndexes('advertisements'))
        ->pluck('name')
        ->all();

    expect($indexes)->toContain('advertisements_slug_en_slug_unique')
        ->and($indexes)->toContain('advertisements_slug_de_slug_unique')
        ->and($indexes)->toContain('advertisements_slug_sk_slug_unique');

    expect(collect(DB::connection()->getSchemaBuilder()->getIndexes('placements'))->pluck('name')->all())
        ->toContain('placements_slug_slug_unique');
})->skip(fn (): bool => DriverMatrix::driver() === 'mysql', 'mysql backs locale-map indexes with generated columns');

it('still generates slugs for rows the model writes after a raw insert took the base', function (): void {
    insertAdvertisementRow(['en' => 'taken']);

    $ad = Advertisement::factory()->create(['name' => 'Taken']);

    expect($ad->getTranslation('slug', 'en'))->toBe('taken-2');
});
