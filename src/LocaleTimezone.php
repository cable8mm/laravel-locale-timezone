<?php

namespace Cable8mm\LocaleTimezone;

/**
 * Immutable per-request context holding the negotiated locale and timezone.
 *
 * The application's global `app.timezone` and `app.locale` are deliberately
 * left untouched. This object is bound to the container for the lifecycle of
 * the request and represents the *client's* context, not the application's.
 */
final class LocaleTimezone
{
    public function __construct(
        public readonly string $locale,
        public readonly string $timezone,
    ) {}
}
