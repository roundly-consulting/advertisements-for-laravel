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
        // The four model keys are read through the toolkit's `ModelResolver::for('advertisements.…')`
        // seam rather than a `config()` call. They are real reads — they drive the whole swap
        // — but they are not `config(` tokens, so a prefix is what makes them visible.
        //
        // The keys are named exactly rather than using a blanket `'advertisements.'`, which
        // would count ANY string literal under the prefix as a read wherever it appeared —
        // including translation keys and bucket names that are not config keys at all (the
        // trap alerts hit with its `alerts.health` route-name default).
        'extraReadPrefixes' => [
            'advertisements.model',
            'advertisements.placement_model',
            'advertisements.category_model',
            'advertisements.event_model',
            // Read through `KeyType::fromConfig('advertisements.key_type')` in the
            // migration (database/ is scanned above) — a real read that decides the author
            // morph column type, but not a `config(` token, so the exact key prefix is
            // what makes it visible.
            'advertisements.key_type',
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
