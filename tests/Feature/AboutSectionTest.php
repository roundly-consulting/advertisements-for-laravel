<?php

declare(strict_types=1);

/**
 * A — the secret-safe `about` capture.
 *
 * Purchases #13 is the bug this exists for: the fleet's most credential-heavy `about` section
 * was guarded by negative assertions against `app(Kernel::class)->output()`, which returns
 * `''`, so every "does not leak" check was vacuous.
 *
 * The provider's own docblock states the intent — an ad server's config points at the host's
 * storage topology (the creative disk) and queue topology (the tracking connection/queue), so
 * those render as presence only. This pins that intent instead of trusting the comment.
 *
 * `mustRender` is asserted BEFORE any secret check runs and throws at call time if empty, so
 * this cannot silently degrade into the purchases shape.
 */
it('renders the advertisements section without leaking the host storage or queue topology', function (): void {
    config()->set('advertisements.media.disk', 'acme-private-creatives-s3');
    config()->set('advertisements.tracking.buffered', true);
    config()->set('advertisements.tracking.connection', 'acme-redis-tracking');
    config()->set('advertisements.tracking.queue', 'acme-impressions-hot');
    config()->set('advertisements.default_currency', 'EUR');
    config()->set('advertisements.geo.stamp_events', true);

    expect('advertisements')->toLeakNoSecrets(
        secrets: [
            // The host's own storage and queue topology — infrastructure, not advertisements'
            // business, and a map of where the creative assets actually live.
            'acme-private-creatives-s3',
            'acme-redis-tracking',
            'acme-impressions-hot',
        ],
        mustRender: [
            // The four model names are the positive proof the section reports rather than
            // being silently empty — the whole purchases lesson.
            'Advertisement model',
            'Placement model',
            'Category model',
            'Event model',
            'EUR',
            'BUFFERED',
            'ON',
        ],
    );
});

/**
 * The switches render as switches. Kept separate: it is a rendering pin, not a leak pin, and
 * folding it into the case above would need the opposite config.
 */
it('reports the configured models and switches in the about section', function (): void {
    config()->set('advertisements.geo.stamp_events', false);
    config()->set('advertisements.register_facade_alias', false);

    expect('advertisements')->toLeakNoSecrets(
        secrets: ['acme-private-creatives-s3'],
        mustRender: ['Advertisement', 'Placement', 'Category', 'AdvertisementEvent', 'OFF'],
    );
});
