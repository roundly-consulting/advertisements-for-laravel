<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Enums;

/**
 * Lifecycle state of an advertisement. The matching `status` column is the
 * stored source of truth, written by the lifecycle actions; the model's
 * `status()` accessor overlays time-based expiry so a published ad past its
 * `expires_at` reads as Expired without needing a re-save.
 */
enum AdvertisementStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Expired = 'expired';
    case Archived = 'archived';
}
