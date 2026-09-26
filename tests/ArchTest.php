<?php

declare(strict_types=1);

use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\AdvertisementEvent;
use RoundlyConsulting\Advertisements\Models\Category;
use RoundlyConsulting\Advertisements\Models\Placement;
use RoundlyConsulting\Money\Casts\AsMoney;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Testing\Arch\ArchPresets;

/**
 * The presets replace the hand-written debug-functions and strict-types rules, and REPLACE
 * FOUR PER-NAMESPACE `toBeFinal()` rules with one whole-namespace `finalByDefault`. That is
 * a strengthening, not a like-for-like swap: the old rules finalised Actions, Jobs, Testing
 * and Support and said nothing at all about Models, Events, DataTransferObjects, Casts or
 * ValueObjects — which is precisely where the fleet's 7× fatal lives.
 *
 * The old debug rule was also weaker than it read: Pest's arch layer only sees a symbol that
 * exists, and `acme/ray` is not in the graph by policy, so `ray` was filtered out before
 * the ban ran and could never fail. `noDebuggingLeftovers` reads source tokens instead.
 */
ArchPresets::strictTypes('RoundlyConsulting\Advertisements');

/**
 * The exemptions, each a real extension point rather than an oversight:
 *
 *  - the four models `config/advertisements.php` invites a host to swap — pinned instead by
 *    the preset below, the deliberate tension the two presets exist to hold;
 *  - AdvertisementException, the exception base hosts catch;
 *  - the event classes, left open so a host swapping a model can carry its own payload.
 */
ArchPresets::finalByDefault('RoundlyConsulting\Advertisements', [
    Advertisement::class,
    Placement::class,
    Category::class,
    AdvertisementEvent::class,
    'RoundlyConsulting\Advertisements\Exceptions\AdvertisementException',
    'RoundlyConsulting\Advertisements\Events\AdvertisementCreated',
    'RoundlyConsulting\Advertisements\Events\AdvertisementUpdated',
    'RoundlyConsulting\Advertisements\Events\AdvertisementDeleted',
    'RoundlyConsulting\Advertisements\Events\AdvertisementPublished',
    'RoundlyConsulting\Advertisements\Events\AdvertisementUnpublished',
    'RoundlyConsulting\Advertisements\Events\AdvertisementArchived',
    'RoundlyConsulting\Advertisements\Events\AdvertisementExpired',
    'RoundlyConsulting\Advertisements\Events\ImpressionRecorded',
    'RoundlyConsulting\Advertisements\Events\ClickRecorded',
]);

/**
 * The counter-weight, and the fleet's 7×-shipped fatal — advertisements #23 was one of the
 * seven. `final` on a config-swappable model is a PHP fatal the moment a host uses the seam
 * the config documents. All four are correctly non-final today; verified, not assumed. The
 * preset also pins that each key really defaults to the packaged model, so the seam cannot
 * rot in the other direction.
 */
ArchPresets::swappableModelsAreNotFinal([
    Advertisement::class => 'advertisements.model',
    Placement::class => 'advertisements.placement_model',
    Category::class => 'advertisements.category_model',
    AdvertisementEvent::class => 'advertisements.event_model',
]);

/**
 * Advertisements does no cryptography. The ban is a standing guard against a click-tracking
 * signature or an impression nonce being hand-rolled here rather than in crypto-for-laravel —
 * a realistic temptation for an ad server, whose whole revenue model rests on those being
 * unforgeable.
 */
ArchPresets::noLocalCryptoPrimitives('RoundlyConsulting\Advertisements');

/**
 * `modelsResolveThroughSeam` is REJECTED here, with cause — and the cause is a preset
 * limitation rather than anything about advertisements.
 *
 * Its late-static-binding half is a FILE-LEVEL token ban: if a file is an Eloquent model and
 * contains `static::query()` anywhere, the file is flagged. It cannot distinguish the bug it
 * was built for — `static::query()` in a STATIC helper, where `static::` binds to the CALLED
 * class and so ignores the configured one (permissions #34) — from `static::query()` in an
 * INSTANCE method, where `static::` binds to the concrete instance's class and is therefore
 * exactly right.
 *
 * Advertisement has one of the latter and none of the former:
 *   - prunable() — Laravel calls it on the host's own configured instance.
 * There, `static::` resolves to the HOST subclass when a host has swapped the model. (Its
 * route binding and slug probing, the other two instance-method uses, moved into
 * sluggable-for-laravel's HasSlug, which likewise runs on the bound instance.)
 * Routing them through the seam instead would be neutral at best, and would replace correct
 * late static binding with an indirection written to satisfy a test — the fleet's own lesson
 * being that a test harness does not get to dictate shipped code (the `down()` pin went 30/30
 * red against a complying fleet before it was deleted).
 *
 * The preset registers an `it()` case and takes no `$ignoring` parameter, so there is no
 * narrower escape than not calling it. This is the SECOND package to hit this after jwt,
 * which is the plan's own ">1 = testing-package defect" threshold for this check — reported
 * upstream rather than worked around per row. The fix belongs in ModelSeam (bind the ban to
 * static-context resolution, or accept an $ignoring list), not here.
 *
 * The preset's OTHER half — stray swap literals outside the seam — is real and applies, so it
 * is kept as the bespoke rule at the bottom of this file rather than lost with the preset.
 * Same shape as alerts, which kept its bespoke rule for the three seams the preset could not
 * see.
 */

/**
 * The Dependency Policy as a test. No `alsoAllow`: advertisements' `require` ships only
 * php/ext-bcmath/illuminate/roundly, and the workflow installs test tooling with `--dev`. If it goes red
 * the graph is wrong — never widen the allow-list to quiet it.
 */
/**
 * The morph-key seam, guarded. The advertisement author column migrated off raw
 * `$table->morphs()` onto `morphKey($name, KeyType::fromConfig(...))` so a uuid/ulid host
 * can flip its whole graph coherently — a hardcoded bigint id breaks those hosts on
 * Postgres, and SQLite type affinity hides it. This pin reds if a future migration
 * reintroduces a raw morph.
 */
ArchPresets::morphColumnsUseTheSeam(__DIR__.'/../database/migrations');

ArchPresets::runtimeRequireIsWhitelisted(__DIR__.'/../composer.json');

ArchPresets::noDebuggingLeftovers();

/**
 * Money's public API only. money-for-laravel marks its engine (`MoneyCast`, `IntegerString`,
 * `Calculator`, `Schema`, …) `@internal`: those may change shape at any time. Advertisements
 * builds on the documented seams — `Money`, `Currency`, `AsMoney` — and this pins it there.
 * Every money class named in `src/` or `database/` (import or inline FQCN) is checked, and the
 * two seams the price column rests on must be among them, so the rule cannot pass over an
 * empty scan.
 */
it('references no @internal money class, only its public api', function (): void {
    $referenced = [];

    foreach ([__DIR__.'/../src', __DIR__.'/../database'] as $root) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            preg_match_all('/RoundlyConsulting\\\\Money\\\\[A-Za-z0-9_\\\\]+/', (string) file_get_contents($file->getPathname()), $matches);

            foreach ($matches[0] as $class) {
                $referenced[] = ltrim($class, '\\');
            }
        }
    }

    $referenced = array_values(array_unique($referenced));

    expect($referenced)->toContain(Money::class, AsMoney::class);

    foreach ($referenced as $class) {
        expect(class_exists($class) || interface_exists($class) || enum_exists($class))->toBeTrue("{$class} does not exist")
            ->and(str_contains((string) (new ReflectionClass($class))->getDocComment(), '@internal'))
            ->toBeFalse("{$class} is @internal to money-for-laravel");
    }
});

/**
 * Bespoke and kept: no preset expresses "this namespace holds traits". The presets replace
 * the generic rules, not bespoke ones with no equivalent.
 */
arch('keeps concerns as traits')
    ->expect('RoundlyConsulting\Advertisements\Concerns')
    ->toBeTraits();

/**
 * Bespoke and kept — this is `modelsResolveThroughSeam`'s stray-literal half, which is real
 * and applies, preserved after the preset itself was rejected above for its file-level
 * late-static-binding ban.
 *
 * Every one of the four swap keys must be read through the Support seam, never inline: a
 * `config('advertisements.model')` anywhere else is a second source of truth that a host's
 * swap can drift away from (media #28, shops #3).
 */
it('reads every model config key through a resolver, never inline', function (): void {
    $stray = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(__DIR__.'/../src', FilesystemIterator::SKIP_DOTS),
    );

    /** @var SplFileInfo $file */
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php' || str_contains($file->getPathname(), '/Support/')) {
            continue;
        }

        if (preg_match(
            "/(?:config|ModelResolver::for)\(\s*'advertisements\.(model|placement_model|category_model|event_model)'/",
            (string) file_get_contents($file->getPathname()),
            $match,
        ) === 1) {
            $stray[] = basename($file->getPathname()).": 'advertisements.{$match[1]}'";
        }
    }

    // Guard the guard: the seams really are read somewhere, so an empty scan of a relocated
    // src/ cannot make this pass vacuously.
    expect(glob(__DIR__.'/../src/Support/*Model.php'))->toHaveCount(4);

    expect($stray)->toBe([], 'These files read a model swap key outside the Support seam: '.implode(', ', $stray));
});
