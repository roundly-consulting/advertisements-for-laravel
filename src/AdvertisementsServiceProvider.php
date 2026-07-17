<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements;

use RoundlyConsulting\Advertisements\Contracts\CreativeRenderer;
use RoundlyConsulting\Advertisements\Facades\Advertisements;
use RoundlyConsulting\Advertisements\Support\AdvertisementModel;
use RoundlyConsulting\Advertisements\Support\CategoryModel;
use RoundlyConsulting\Advertisements\Support\CreativeResolver;
use RoundlyConsulting\Advertisements\Support\EventModel;
use RoundlyConsulting\Advertisements\Support\PlacementModel;
use RoundlyConsulting\PackageToolkit\Concerns\RegistersBlueprintMacros;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;

final class AdvertisementsServiceProvider extends PackageServiceProvider
{
    use RegistersBlueprintMacros;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('advertisements')
            ->hasConfigFile()
            ->hasMigrations()
            ->hasViews()
            ->hasFacadeAlias(Advertisements::class, 'advertisements.register_facade_alias')
            ->contributesToAbout(fn (): array => $this->aboutSection());
    }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(AdvertisementManager::class);
        $this->app->bind(CreativeRenderer::class, CreativeResolver::class);
    }

    public function boot(): void
    {
        parent::boot();

        // The migration's key-type-aware author morph is a macro, so it must exist
        // before a host runs `php artisan migrate`.
        $this->registerBlueprintMacros();
    }

    /**
     * An ad server's config points at the host's own storage topology (the creative
     * disk) and queue topology (the tracking connection/queue), so those render as
     * presence only. The models render by base name; everything else is a switch or
     * a bound.
     *
     * @return array<string, string>
     */
    private function aboutSection(): array
    {
        return [
            'Advertisement model' => class_basename(AdvertisementModel::class()),
            'Placement model' => class_basename(PlacementModel::class()),
            'Category model' => class_basename(CategoryModel::class()),
            'Event model' => class_basename(EventModel::class()),
            'Tracking' => $this->tracking(),
            'Default currency' => (string) config('advertisements.default_currency', 'EUR'),
            'Creatives' => $this->creatives(),
            'Geo targeting' => $this->geoTargeting(),
            'Geo stamping' => config('advertisements.geo.stamp_events') ? 'ON' : 'OFF',
            'Facade alias' => config('advertisements.register_facade_alias') === false ? 'OFF' : 'ON',
        ];
    }

    private function tracking(): string
    {
        if (! config('advertisements.tracking.buffered')) {
            return 'INLINE';
        }

        return sprintf(
            'BUFFERED (connection %s, queue %s)',
            config('advertisements.tracking.connection') !== null ? 'SET' : 'DEFAULT',
            config('advertisements.tracking.queue') !== null ? 'SET' : 'DEFAULT',
        );
    }

    /**
     * The creative disk is the host's storage topology (routinely a private bucket),
     * so it renders as presence only, never by name.
     */
    private function creatives(): string
    {
        $widths = config('advertisements.media.responsive_widths');

        return sprintf(
            'disk %s, %s fallback bucket, %s responsive widths',
            config('advertisements.media.disk') !== null ? 'SET' : 'MEDIA DEFAULT',
            config('advertisements.media.use_fallback_bucket') ? 'with' : 'no',
            is_array($widths) ? (string) count($widths) : 'MEDIA DEFAULT',
        );
    }

    private function geoTargeting(): string
    {
        if (! config('advertisements.geo.targeting_enabled')) {
            return 'OFF';
        }

        return sprintf(
            'ON (untargeted ads %s, unknown viewer sees %s)',
            config('advertisements.geo.untargeted_match') ? 'match' : 'excluded',
            config('advertisements.geo.match_when_unknown') === 'all' ? 'all' : 'untargeted only',
        );
    }
}
