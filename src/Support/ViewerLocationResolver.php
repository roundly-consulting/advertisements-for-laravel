<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Support;

use Illuminate\Http\Request;
use RoundlyConsulting\Geolocation\DataTransferObjects\Location;
use RoundlyConsulting\Geolocation\Facades\Geolocation;

/**
 * Single resolution point that turns a request / IP into a geolocation {@see Location},
 * shared by serve-time targeting and impression stamping. Results are memoized per IP for
 * the lifetime of the resolver so the two never look the same viewer up twice.
 */
final class ViewerLocationResolver
{
    /** @var array<string, ?Location> */
    private array $memo = [];

    /**
     * Resolve a viewer to a Location. Accepts an already-resolved Location (returned as-is),
     * a raw IP string, an Illuminate Request, or null (the current request). Returns null when
     * no location can be resolved — callers treat that as "unknown viewer".
     */
    public function resolve(Request|Location|string|null $viewer = null): ?Location
    {
        if ($viewer instanceof Location) {
            return $viewer;
        }

        $ip = $this->ipFor($viewer);

        if ($ip === null || $ip === '') {
            return null;
        }

        return $this->memo[$ip] ??= Geolocation::locateIp($ip);
    }

    private function ipFor(Request|string|null $viewer): ?string
    {
        if (is_string($viewer)) {
            return $viewer;
        }

        return ($viewer ?? request())->ip();
    }
}
