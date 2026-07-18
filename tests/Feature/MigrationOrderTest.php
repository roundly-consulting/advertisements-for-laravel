<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Advertisements\AdvertisementsServiceProvider;
use RoundlyConsulting\Advertisements\Models\Category;
use RoundlyConsulting\Advertisements\Models\Placement;
use RoundlyConsulting\PackageToolkit\Enums\DatabaseDriver;
use RoundlyConsulting\Testing\Database\DriverMatrix;

/**
 * Advertisements ships seven migrations wired by six foreign keys, and publishing preserves
 * source order — so that order has to be runnable end to end from an empty database.
 *
 * This file replaces ~170 lines of hand-rolled machinery: a temp-directory publisher, a
 * second SQLite connection, and a bespoke order check. That was honest work, but its
 * `migrate` assertions could never fail on a broken order — SQLite creates a table pointing
 * at a missing parent and only complains at insert time, which is the mechanism behind five
 * packages shipping uninstallable migration orders under green suites. The old file's own
 * docblock says the real proof was run by hand against a PostgreSQL server; that proof is now
 * committed, gated and repeatable rather than a claim in a comment.
 */
$migrations = __DIR__.'/../../database/migrations';

/**
 * M — the structural pin, and the only one of these that catches a broken order on SQLite.
 *
 * `foreignKeys: 6` is what stops it passing over an empty parse: the count is pinned, so a
 * parser that silently understood nothing fails instead of reporting success over zero edges.
 * The six: categories.parent_id -> categories (SELF-REFERENCING, which must sort with its own
 * migration rather than before it — advertisements #33), advertisements.category_id,
 * advertisement_placement.advertisement_id + .placement_id, advertisement_events.advertisement_id
 * + .placement_id.
 *
 * It also pins the OTHER half of assertRunnable(), which is not FK-related at all: both
 * `Schema::table()` ALTERs (0006 targeting adds to advertisements, 0007 country_code to
 * advertisement_events) must sort at or after the CREATE of the table they alter — approvals
 * #2.
 */
it('creates every foreign key target before the table that references it', function () use ($migrations): void {
    expect($migrations)->toHaveRunnableMigrationOrder(foreignKeys: 6);
});

/**
 * P — the publish-only guards. The fleet publishes migrations timestamped rather than
 * auto-loading them; doing both runs both copies and dies on a duplicate table (bug #5, on
 * three packages). `7` pins the file count so neither check can pass over an empty or
 * relocated directory.
 */
it('never auto-loads its migrations — the host publishes them', function (): void {
    expect(AdvertisementsServiceProvider::class)->toNotAutoLoadMigrations();
});

it('publishes its migrations timestamp-injected into the host', function (): void {
    expect(AdvertisementsServiceProvider::class)->toPublishMigrationsTimestamped('advertisements-migrations', 7);
});

/**
 * R — the real-engine proof. The old version of this file migrated the published files into a
 * second SQLite database, which is not a proof: SQLite accepts a dangling foreign key at DDL
 * time. Postgres rejects it, so this is the assertion that actually watches the shipped order
 * install.
 */
it('applies its migrations on postgres', function () use ($migrations): void {
    expect($migrations)->toApplyOnConnection('pgsql', migrations: 7);
})->skip(fn (): bool => ! test()->connectionAvailable('pgsql'), 'no postgres connection available');

/**
 * R's negative control — the half that makes the one above mean something. A green FK test
 * proves nothing until you have watched the engine *reject* the broken order (forms #28).
 *
 * Advertisements is the ONLY row in this batch that can adopt it: it needs real FK edges, and
 * the other five rows have none, so Postgres would accept their reversed list and the
 * assertion would fail by design. Reversing these seven puts advertisement_events before
 * advertisements, so Postgres must refuse with `relation "advertisements" does not exist`.
 *
 * Verified non-vacuous: pointed at the sqlite connection it does not quietly pass but FAILS
 * loudly — "the engine ACCEPTED a deliberately broken migration order" — which is why it is
 * gated on a real connection rather than the default one.
 */
it('rejects a child-before-parent order on postgres', function () use ($migrations): void {
    expect($migrations)->toRejectBrokenOrderOnConnection(
        fn (array $files): array => array_reverse($files),
        'pgsql',
    );
})->skip(fn (): bool => ! test()->connectionAvailable('pgsql'), 'no postgres connection available');

/**
 * The driver-truth pin. It compares the env-DECLARED driver against what the connection
 * itself answers, so a "pgsql" leg that quietly stayed on SQLite — a decapitated
 * `defineEnvironment()`, a missing `TESTING_DB_DRIVER` — goes red here rather than passing as
 * a postgres run. It fires automatically, unlike reading a skip count by hand.
 */
it('runs on the driver the environment declares', function (): void {
    expect(DatabaseDriver::current())->toBe(DatabaseDriver::from(DriverMatrix::driver()));
});

/**
 * The eleven jsonb columns are what the drivers render differently, and this row is the one
 * where that matters most: jsonb sorts object keys by (length, bytes) AND canonicalises
 * whitespace, so `name` and `meta` come back reordered on Postgres and not on SQLite.
 *
 * `meta` is asserted key-by-key rather than as a whole array for exactly that reason — a
 * whole-array `toBe` would pin a storage order Postgres never promised. Each key keeps a
 * STRICT `toBe` rather than relaxing to `toEqual`: this test exists to prove the driver
 * renders the column faithfully, and `toEqual` (which is `==`) would let the int 2 come back
 * as the string "2".
 */
it('round-trips the jsonb translation and meta columns on the configured engine', function (): void {
    $category = Category::query()->create([
        'name' => ['en' => 'Homepage', 'sk' => 'Domov'],
        'slug' => 'homepage',
        'meta' => ['region' => 'eu', 'tier' => 2],
    ]);

    $placement = Placement::query()->create([
        'name' => ['en' => 'Sidebar'],
        'slug' => 'sidebar',
        'width' => 300,
        'height' => 250,
    ]);

    $fresh = $category->fresh();

    expect($fresh->getTranslations('name'))->toEqual(['en' => 'Homepage', 'sk' => 'Domov'])
        ->and($fresh->meta['region'] ?? null)->toBe('eu')
        ->and($fresh->meta['tier'] ?? null)->toBe(2)
        ->and($placement->fresh()->width)->toBe(300)
        ->and(DB::connection()->getDriverName())->toBe(DriverMatrix::driver());
});

/**
 * The self-referencing category key (advertisements #33) is a real FK edge on a real engine,
 * so a parent must exist before a child can point at it. Pinning the round-trip proves the
 * column is usable rather than merely creatable — and on SQLite it is only enforced at all
 * because PackageTestCase turns `PRAGMA foreign_keys` ON, which this suite never did before
 * this row.
 */
it('enforces the self-referencing category key on the configured engine', function (): void {
    $parent = Category::query()->create(['name' => ['en' => 'Root'], 'slug' => 'root']);
    $child = Category::query()->create([
        'name' => ['en' => 'Child'],
        'slug' => 'child',
        'parent_id' => $parent->getKey(),
    ]);

    expect($child->fresh()->parent_id)->toBe($parent->getKey());

    // A category pointing at a category that does not exist must be refused — the whole point
    // of the key, and invisible with the pragma off.
    expect(fn () => Category::query()->create([
        'name' => ['en' => 'Orphan'],
        'slug' => 'orphan',
        'parent_id' => 999999,
    ]))->toThrow(QueryException::class);
});
