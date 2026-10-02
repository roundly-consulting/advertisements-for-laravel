@php
    /** @var \RoundlyConsulting\Advertisements\Models\Advertisement $advertisement */
    /** @var \RoundlyConsulting\Advertisements\Models\Placement|null $placement */
    /** @var array<string, string> $attributes */

    $styles = [];

    if ($placement?->width !== null) {
        $styles[] = 'width:'.(int) $placement->width.'px';
    }

    if ($placement?->height !== null) {
        $styles[] = 'height:'.(int) $placement->height.'px';
    }

    // The caller's attributes (Advertisements::render($ad, $placement, $attributes)) merged
    // over the defaults: classes and styles are appended, anything else wins; all escaped.
    $defaults = array_filter([
        'class' => 'advertisement-text-ad',
        'style' => $styles === [] ? null : implode(';', $styles).';',
        'data-advertisement' => (string) $advertisement->getKey(),
    ], static fn (?string $value): bool => $value !== null);

    $attributeBag = (new \Illuminate\View\ComponentAttributeBag(array_map(e(...), $attributes)))->merge($defaults);
    $description = $advertisement->description;
@endphp
<div {{ $attributeBag }}>
    <span class="advertisement-text-ad__name">{{ $advertisement->name }}</span>
    @if (filled($description))
        <span class="advertisement-text-ad__description">{{ $description }}</span>
    @endif
    @if ($advertisement->price !== null)
        <span class="advertisement-text-ad__price">{{ $advertisement->price->format() }}</span>
    @endif
</div>
