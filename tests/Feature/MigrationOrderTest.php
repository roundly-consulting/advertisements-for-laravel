<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use RoundlyConsulting\Advertisements\AdvertisementsServiceProvider;

/**
 * Seven migrations wired together by six real foreign keys: `advertisements` →
 * `categories`, the `advertisement_placement` pivot → both parents, and
 * `advertisement_events` → both parents (plus `categories.parent_id` onto itself).
 * Migrations are publish-only, and publishing preserves the source directory's
 * order — so that order has to be runnable end to end from an empty database.
 *
 * SQLite happily creates a table referencing a missing parent (it only complains at
 * insert time), so the first two tests are the committed pin, not the proof: the
 * order was proved against a real PostgreSQL server, which rejects a dangling
 * foreign key at DDL time — the shipped order applied all seven with all six keys,
 * and a negative control (advertisements before categories) was watched being
 * rejected with `relation "categories" does not exist`.
 *
 * These tests run the *published* files, under their published names, into a
 * database that starts empty — exactly what a host does.
 */
beforeEach(function (): void {
    $this->publishedPath = sys_get_temp_dir().'/advertisements-migration-order-'.bin2hex(random_bytes(6));
    $this->publishedDatabase = $this->publishedPath.'/database.sqlite';

    File::makeDirectory($this->publishedPath, recursive: true);
    File::put($this->publishedDatabase, '');

    $published = ServiceProvider::pathsToPublish(
        AdvertisementsServiceProvider::class,
        'advertisements-migrations',
    );

    foreach ($published as $source => $target) {
        File::copy($source, $this->publishedPath.'/'.basename((string) $target));
    }

    config()->set('database.connections.published', [
        'driver' => 'sqlite',
        'database' => $this->publishedDatabase,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]);
});

afterEach(function (): void {
    File::deleteDirectory($this->publishedPath);
});

it('migrates the published files clean from an empty database', function (): void {
    $schema = Schema::connection('published');

    expect($schema->hasTable('advertisements'))->toBeFalse();

    $this->artisan('migrate', [
        '--database' => 'published',
        '--path' => $this->publishedPath,
        '--realpath' => true,
    ])->assertExitCode(0);

    foreach (['categories', 'placements', 'advertisements', 'advertisement_placement', 'advertisement_events'] as $table) {
        expect($schema->hasTable($table))->toBeTrue();
    }

    // The two ALTERs ran after their CREATEs.
    expect($schema->hasColumn('advertisements', 'targeting'))->toBeTrue()
        ->and($schema->hasColumn('advertisements', 'target_latitude'))->toBeTrue()
        ->and($schema->hasColumn('advertisement_events', 'country_code'))->toBeTrue();
});

it('keeps every foreign key intact in the published schema', function (): void {
    $this->artisan('migrate', [
        '--database' => 'published',
        '--path' => $this->publishedPath,
        '--realpath' => true,
    ])->assertExitCode(0);

    $schema = Schema::connection('published');

    $foreignKeys = static fn (string $table): array => array_map(
        static fn (array $key): string => $key['columns'][0].' → '.$key['foreign_table'],
        $schema->getForeignKeys($table),
    );

    expect($foreignKeys('categories'))->toContain('parent_id → categories')
        ->and($foreignKeys('advertisements'))->toContain('category_id → categories')
        ->and($foreignKeys('advertisement_placement'))
        ->toContain('advertisement_id → advertisements')
        ->toContain('placement_id → placements')
        ->and($foreignKeys('advertisement_events'))
        ->toContain('advertisement_id → advertisements')
        ->toContain('placement_id → placements');
});

/**
 * The structural pin — the one that catches a broken order on SQLite, where the
 * two tests above stay green against a dangling foreign key.
 *
 * Read every `constrained()` out of the migration sources and assert the parent's
 * CREATE really does sort before the child's (a self-reference may sort with it).
 */
it('creates every foreign key target before the table that references it', function (): void {
    $sources = glob(__DIR__.'/../../database/migrations/*.php');
    sort($sources);

    /** @var array<string, int> $createdAt */
    $createdAt = [];
    /** @var list<array{child: string, parent: string, at: int}> $edges */
    $edges = [];

    foreach ($sources as $position => $source) {
        $body = (string) file_get_contents($source);

        // A CREATE registers its table; an ALTER references one already created.
        preg_match("/Schema::(create|table)\('([a-z_]+)'/", $body, $schema);
        expect($schema)->not->toBeEmpty();

        $table = $schema[2];

        if ($schema[1] === 'create') {
            $createdAt[$table] = $position;
        } else {
            // An ALTER may only touch a table an earlier migration created.
            expect(array_key_exists($table, $createdAt))->toBeTrue(
                "{$table} is altered before it is created",
            );
        }

        // Both forms: `->constrained()` (the parent table is derived from the column
        // name) and `->constrained('explicit_table')`.
        preg_match_all(
            "/foreignId\('([a-z_]+)'\).*?->constrained\(\s*(?:'([a-z_]+)')?\s*\)/s",
            $body,
            $matches,
            PREG_SET_ORDER,
        );

        // Guard the guard: every `->constrained(` in the source was actually paired.
        expect($matches)->toHaveCount(substr_count($body, '->constrained('));

        foreach ($matches as $match) {
            $parent = ($match[2] ?? '') !== ''
                ? $match[2]
                : Str::plural(Str::beforeLast($match[1], '_id'));

            $edges[] = ['child' => $table, 'parent' => $parent, 'at' => $position];
        }
    }

    // The package really does emit the six foreign keys this test guards.
    expect($edges)->toHaveCount(6);

    foreach ($edges as $edge) {
        expect($createdAt)->toHaveKey($edge['parent']);

        // A self-referencing key (categories.parent_id) is created by its own file.
        $edge['parent'] === $edge['child']
            ? expect($createdAt[$edge['parent']])->toBe($edge['at'])
            : expect($createdAt[$edge['parent']])->toBeLessThan(
                $edge['at'],
                "{$edge['child']} references {$edge['parent']}, which must be created first",
            );
    }
});
