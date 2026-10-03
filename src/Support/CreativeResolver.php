<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Support;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use RoundlyConsulting\Advertisements\Contracts\CreativeRenderer;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\Placement;
use RoundlyConsulting\MediaLibrary\Models\Media;

/**
 * Resolves what to render for an advertisement in a placement: the placement's responsive
 * image creative when one exists, otherwise a text ad built from the ad's own fields and
 * rendered through the publishable `advertisements::text-ad` Blade view.
 *
 * Bound to the {@see CreativeRenderer} contract — bind your own implementation to swap the chain.
 */
final class CreativeResolver implements CreativeRenderer
{
    public function __construct(
        private readonly ViewFactory $views,
        private readonly PlacementResolver $placements,
    ) {}

    /**
     * @param  array<string, string>  $attributes
     */
    public function render(Advertisement $advertisement, Placement|int|string $placement, array $attributes = []): HtmlString
    {
        $media = $advertisement->creativeFor($placement);

        if ($media instanceof Media && $media->isImage()) {
            return new HtmlString($this->renderImage($advertisement, $media, $attributes));
        }

        return $this->renderTextAd($advertisement, $this->placements->resolve($placement), $attributes);
    }

    /**
     * @param  array<string, string>  $attributes
     */
    private function renderImage(Advertisement $advertisement, Media $media, array $attributes): string
    {
        $attributes['alt'] ??= (string) ($advertisement->name ?? '');

        // Hydrate the inverse relation so URL generation does not lazily reload the owner.
        $media->setRelation('model', $advertisement);

        return $media->responsiveImage($advertisement->displayVariant(), $attributes);
    }

    /**
     * @param  array<string, string>  $attributes
     */
    private function renderTextAd(Advertisement $advertisement, ?Placement $placement, array $attributes): HtmlString
    {
        $view = $this->textAdView();
        $this->assertViewExists($view);

        $html = $this->views->make($view, [
            'advertisement' => $advertisement,
            'placement' => $placement,
            'attributes' => $attributes,
        ])->render();

        return new HtmlString(trim($html));
    }

    private function textAdView(): string
    {
        return AdvertisementsConfig::textAdView();
    }

    /**
     * The text-ad view is a config value, so it is checked before rendering rather than
     * trusted: a typo fails with the same "not found" the factory would raise, and the check
     * is what narrows the name to a view-string for static analysis.
     *
     * @phpstan-assert view-string $view
     */
    private function assertViewExists(string $view): void
    {
        if (! $this->views->exists($view)) {
            throw new InvalidArgumentException("View [{$view}] not found.");
        }
    }
}
