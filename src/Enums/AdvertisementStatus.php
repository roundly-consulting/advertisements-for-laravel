<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Enums;

use RoundlyConsulting\Enums\Helpers;

/**
 * Lifecycle state of an advertisement. The matching `status` column is the
 * stored source of truth, written by the lifecycle actions; the model's
 * `status()` accessor overlays time-based expiry so a published ad past its
 * `expires_at` reads as Expired without needing a re-save.
 *
 * Uses the shared {@see Helpers} trait from `enums-for-laravel`, exposing
 * `labels()`, `options()`, `validationRule()`, `label()`, and friends.
 */
enum AdvertisementStatus: string
{
    use Helpers;

    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Expired = 'expired';
    case Archived = 'archived';
}
