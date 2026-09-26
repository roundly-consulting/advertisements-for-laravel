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

    $style = implode(';', $styles);
    $description = $advertisement->description;
@endphp
<div class="advertisement-text-ad"@if ($style !== '') style="{{ $style }}"@endif data-advertisement="{{ $advertisement->getKey() }}">
    <span class="advertisement-text-ad__name">{{ $advertisement->name }}</span>
    @if (filled($description))
        <span class="advertisement-text-ad__description">{{ $description }}</span>
    @endif
    @if ($advertisement->price !== null)
        <span class="advertisement-text-ad__price">{{ $advertisement->price->format() }}</span>
    @endif
</div>
