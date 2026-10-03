<?php

declare(strict_types=1);

/**
 * C — the config-key contract, pinned in both directions.
 *
 * This replaces a hand-rolled pair of regex scans over raw file text. The regex was honest
 * work, but media #27 is exactly why it goes: a regex over raw text is satisfied by a
 * **docblock mention** of a key, and stayed green there with the fix reverted. This
 * expectation scrapes reads from source **tokens**, so a comment is a comment and never a
 * read. The old file also scanned only for `config('advertisements.…')`, so every key read
 * through the ModelResolver seam was invisible to it.
 *
 *  - forward — every key the code reads is shipped. Shops #18 is the case: a whole
 *    store-credit feature read `shops.payments.*` while the file shipped `payment.*`, and 330
 *    tests stayed green because the suite set the same wrong key the code read.
 *  - reverse — every shipped leaf is read (alerts #24; media #27's `max_file_size` cap that
 *    never applied — an upload endpoint with no size limit). The old file had NO reverse
 *    direction at all, so a dead key here was undetectable.
 */
it('ships exactly the config keys it reads', function (): void {
    expect(__DIR__.'/../../config/advertisements.php')->toSatisfyConfigContract([__DIR__.'/../../src', __DIR__.'/../../database'], [
        // Read through Support\AdvertisementsConfig's own strict readers (a non-blank string,
        // an optional string), each naming its key as a literal argument the scraper does not
        // follow into the reader. Named exactly rather than a blanket `'advertisements.'`, which
        // would count ANY literal under the prefix as a read — translation keys and bucket
        // names included (the trap alerts hit with its `alerts.health` route-name default).
        'extraReadPrefixes' => [
            'advertisements.default_currency',
            'advertisements.fallback_locale',
            'advertisements.media.creative_bucket_prefix',
            'advertisements.media.disk',
            'advertisements.media.display_variant',
            'advertisements.media.fallback_bucket',
            'advertisements.media.text_ad_view',
            'advertisements.tracking.connection',
            'advertisements.tracking.queue',
        ],

        // Deliberately NO `excludeFromReverse` for the provider. The testing README's own
        // example excludes the service provider on the grounds that "a render is not a read"
        // — but this provider's `aboutSection()` reads `advertisements.default_currency`,
        // `.geo.stamp_events`, `.register_facade_alias` and the tracking/creative keys for
        // real, and the toolkit's `bindFromConfig()` reads more still. Excluding it would
        // discard the only reader of several bound keys and weaken the reverse direction for
        // nothing.
    ]);
});
