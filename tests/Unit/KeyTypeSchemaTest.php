<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\PackageToolkit\Enums\DatabaseDriver;
use RoundlyConsulting\Testing\Database\DriverMatrix;

function dropAdvertisementsTable(): void
{
    // advertisement_placement and advertisement_events carry a FK to advertisements, so a
    // plain drop is refused on a strict engine — cascade past them.
    DriverMatrix::driver() === 'pgsql'
        ? DB::statement('drop table if exists advertisements cascade')
        : Schema::dropIfExists('advertisements');
}

function runAdvertisementsMigration(): void
{
    $migration = require __DIR__.'/../../database/migrations/0003_create_advertisements_table.php';
    $migration->up();
}

function advertisementsCreateTable(string $table): string
{
    /** @var list<object{sql: string|null}> $rows */
    $rows = DB::select('select sql from sqlite_master where type = ? and name = ?', ['table', $table]);

    return (string) ($rows[0]->sql ?? '');
}

/** @return array{type: string, nullable: string} */
function advertisementsPgColumn(string $table, string $column): array
{
    /** @var list<object{data_type: string, character_maximum_length: int|null, is_nullable: string}> $rows */
    $rows = DB::select(
        'select data_type, character_maximum_length, is_nullable from information_schema.columns where table_name = ? and column_name = ?',
        [$table, $column],
    );

    $row = $rows[0] ?? null;

    if ($row === null) {
        return ['type' => 'MISSING', 'nullable' => 'MISSING'];
    }

    $type = $row->character_maximum_length === null
        ? $row->data_type
        : $row->data_type.'('.$row->character_maximum_length.')';

    return ['type' => $type, 'nullable' => $row->is_nullable];
}

$sqliteOnly = fn (): bool => DriverMatrix::driver() !== 'sqlite';
$pgsqlOnly = fn (): bool => DriverMatrix::driver() !== 'pgsql';

it('creates the polymorphic author column', function (): void {
    expect(Schema::hasColumns('advertisements', ['author_type', 'author_id']))->toBeTrue();
});

/**
 * The core P1 safety property: `morphKey($n, BigInt, nullable: true)` IS `nullableMorphs($n)`.
 */
it('emits a bigint author morph byte-identical to raw nullableMorphs()', function (): void {
    Schema::dropIfExists('author_raw_ref');
    Schema::create('author_raw_ref', function (Blueprint $table): void {
        $table->id();
        $table->nullableMorphs('author');
    });

    expect(advertisementsCreateTable('advertisements'))->toContain('"author_type" varchar, "author_id" integer')
        ->and(advertisementsCreateTable('author_raw_ref'))->toContain('"author_type" varchar, "author_id" integer');

    Schema::dropIfExists('author_raw_ref');
})->skip($sqliteOnly, 'sqlite_master is the sqlite catalog');

/**
 * The headline of P1: a uuid/ulid host gets uuid/ulid author columns; bigint stays bigint.
 * Postgres tells the three apart; the author morph keeps its `nullableMorphs()` nullability.
 */
it('renders each configured key type as a distinct real column type', function (string $keyType, string $expected): void {
    config()->set('advertisements.key_type', $keyType);

    dropAdvertisementsTable();
    runAdvertisementsMigration();

    expect(advertisementsPgColumn('advertisements', 'author_id'))->toBe(['type' => $expected, 'nullable' => 'YES'])
        ->and(advertisementsPgColumn('advertisements', 'author_type')['type'])->toBe('character varying(255)');
})->with([
    'bigint' => ['bigint', 'bigint'],
    'uuid' => ['uuid', 'uuid'],
    'ulid' => ['ulid', 'character(26)'],
])->skip($pgsqlOnly, 'needs the postgres catalog to tell the key types apart');

it('falls back to the bigint schema for an unrecognized key type', function (): void {
    config()->set('advertisements.key_type', 'nonsense');

    dropAdvertisementsTable();
    runAdvertisementsMigration();

    expect(Schema::hasColumn('advertisements', 'author_id'))->toBeTrue()
        ->and(DatabaseDriver::current()->isPgsql() ? advertisementsPgColumn('advertisements', 'author_id')['type'] : 'bigint')
        ->toBe('bigint');
});
