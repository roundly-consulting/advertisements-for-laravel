<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use RoundlyConsulting\Advertisements\DataTransferObjects\ImpressionData;
use RoundlyConsulting\Advertisements\Facades\Advertisements;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\Placement;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\Config;

/*
 | A switch typo fails loudly. The config file hands each env value through raw and every read
 | goes through the toolkit's strict boolean reader, so `disabled` throws naming the key instead
 | of reading as on (what a `(bool)` cast or a truthiness check makes of any non-empty string).
 */

/**
 * Evaluate the shipped config file with one env variable set, the way a host boots it.
 *
 * @return array<string, mixed>
 */
function advertisementsConfigWithEnv(string $name, string $value): array
{
    $_SERVER[$name] = $_ENV[$name] = $value;
    putenv("{$name}={$value}");

    try {
        /** @var array<string, mixed> */
        return require __DIR__.'/../../config/advertisements.php';
    } finally {
        unset($_SERVER[$name], $_ENV[$name]);
        putenv($name);
    }
}

function advertisementsBooleanError(string $key): string
{
    return "Configuration value [{$key}] must be a boolean (true/false, 1/0, on/off or yes/no), [disabled] given.";
}

it('hands a mistyped facade alias env through raw (strict config)', function (): void {
    expect(advertisementsConfigWithEnv('ADVERTISEMENTS_FACADE_ALIAS', 'disabled')['register_facade_alias'])->toBe('disabled');
});

it('opts out of the facade alias with an env off string', function (string $off): void {
    $config = advertisementsConfigWithEnv('ADVERTISEMENTS_FACADE_ALIAS', $off);

    expect(Config::for($config)->boolean('register_facade_alias', true))->toBeFalse();
})->with(['off', 'no', '0', 'false']);

it('keeps the facade alias on when the env is unset', function (): void {
    expect(advertisementsConfigWithEnv('ADVERTISEMENTS_UNRELATED', 'x')['register_facade_alias'])->toBeTrue();
});

it('refuses a mistyped switch at its read path (strict config)', function (string $key, Closure $read): void {
    config()->set($key, 'disabled');

    expect($read)->toThrow(InvalidConfigurationException::class, advertisementsBooleanError($key));
})->with([
    'tracking.buffered' => ['advertisements.tracking.buffered', function (): void {
        Advertisements::for(Advertisement::factory()->published()->create())->track()->click();
    }],
    'geo.stamp_events' => ['advertisements.geo.stamp_events', function (): void {
        Advertisements::for(Advertisement::factory()->published()->create())->track()->impression(new ImpressionData(ip: '1.2.3.4'));
    }],
    'geo.targeting_enabled' => ['advertisements.geo.targeting_enabled', function (): void {
        Advertisements::targetedIn(Placement::factory()->create(), location('SK'));
    }],
    'geo.untargeted_match' => ['advertisements.geo.untargeted_match', function (): void {
        Advertisement::query()->targetedAt('SK');
    }],
    'slugs.history' => ['advertisements.slugs.history', function (): void {
        Advertisement::factory()->create();
    }],
    'media.use_fallback_bucket' => ['advertisements.media.use_fallback_bucket', function (): void {
        Advertisement::factory()->create()->creativeFor(Placement::factory()->create(['width' => 300, 'height' => 250]));
    }],
]);

it('refuses a mistyped switch in about (strict config)', function (string $key): void {
    config()->set($key, 'disabled');

    expect(fn () => Artisan::call('about', ['--only' => 'advertisements']))
        ->toThrow(InvalidConfigurationException::class, advertisementsBooleanError($key));
})->with([
    'advertisements.tracking.buffered',
    'advertisements.geo.stamp_events',
    'advertisements.geo.targeting_enabled',
    'advertisements.geo.untargeted_match',
    'advertisements.slugs.history',
    'advertisements.media.use_fallback_bucket',
    'advertisements.register_facade_alias',
]);

it('reads env-string switches in about', function (): void {
    config()->set('advertisements.tracking.buffered', 'off');
    config()->set('advertisements.geo.stamp_events', 'no');
    config()->set('advertisements.slugs.history', 'on');
    config()->set('advertisements.register_facade_alias', '0');
    config()->set('advertisements.media.use_fallback_bucket', 'false');

    Artisan::call('about', ['--only' => 'advertisements', '--json' => true]);

    /** @var array<string, array<string, string>> $about */
    $about = json_decode(Artisan::output(), true);

    expect($about['advertisements']['tracking'])->toBe('INLINE')
        ->and($about['advertisements']['geo_stamping'])->toBe('OFF')
        ->and($about['advertisements']['slug_history'])->toBe('ON')
        ->and($about['advertisements']['facade_alias'])->toBe('OFF')
        ->and($about['advertisements']['creatives'])->toContain('no fallback bucket');
});
