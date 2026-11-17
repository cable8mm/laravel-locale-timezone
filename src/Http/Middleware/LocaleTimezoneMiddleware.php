<?php

namespace Cable8mm\LocaleTimezone\Http\Middleware;

use Cable8mm\LocaleTimezone\LocaleTimezone;
use Closure;
use Illuminate\Http\Request;

/**
 * Resolves the client's context (locale and timezone) for every request.
 *
 * Resolution rules — first match wins:
 *
 *  Locale
 *   1. X-Locale header (name configurable), must be a supported locale
 *   2. locale cookie (name configurable), must be a supported locale
 *   3. The authenticated user's locale attribute, when set and supported
 *   4. Accept-Language header, negotiated against config('locale-timezone.locales').
 *      Symfony returns the first supported locale when nothing matches.
 *   5. config('locale-timezone.fallback_locale') when 'locales' is empty
 *
 *  Timezone
 *   1. X-Timezone header (name configurable), validated against the IANA list
 *   2. tz cookie (name configurable), validated against the IANA list
 *   3. The authenticated user's timezone attribute, when set and valid
 *   4. config('locale-timezone.default_timezone')
 *
 * The middleware never calls date_default_timezone_set() and never mutates
 * config('app.timezone'). Instead, the resolved values are exposed through
 *   - $request->setLocale($locale)         (Symfony Request locale)
 *   - App::setLocale($locale)               (so __() and trans() use the request locale)
 *   - two read macros on the Request object: $request->timezone(), $request->now()
 *   - app(\Cable8mm\LocaleTimezone\LocaleTimezone::class)
 */
class LocaleTimezoneMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $locale = $this->resolveLocale($request);
        $timezone = $this->resolveTimezone($request);

        $request->setLocale($locale);
        app()->setLocale($locale);

        app()->instance(LocaleTimezone::class, new LocaleTimezone($locale, $timezone));

        $response = $next($request);

        if (config('locale-timezone.set_content_language', true)) {
            $response->headers->set('Content-Language', $locale);
        }

        return $response;
    }

    /**
     * Negotiate the locale: X-Locale header, then locale cookie, then the
     * authenticated user's locale attribute, then Accept-Language (Symfony
     * negotiates q-values; unmatched values resolve to the first supported
     * locale), then the configured fallback.
     */
    private function resolveLocale(Request $request): string
    {
        $supported = (array) config('locale-timezone.locales', ['ko', 'en']);
        $fallback = (string) config('locale-timezone.fallback_locale', 'ko');

        $header = $request->header(config('locale-timezone.locale_header', 'X-Locale'));

        if (is_string($header) && $this->isSupportedLocale($header, $supported)) {
            return $header;
        }

        $cookie = $request->cookie(config('locale-timezone.locale_cookie', 'locale'));

        if (is_string($cookie) && $this->isSupportedLocale($cookie, $supported)) {
            return $cookie;
        }

        $userLocale = $this->userAttribute($request, 'user_locale_attribute', 'locale');

        if ($userLocale !== null && $this->isSupportedLocale($userLocale, $supported)) {
            return $userLocale;
        }

        if ($supported === []) {
            return $fallback;
        }

        return $request->getPreferredLanguage($supported) ?? $fallback;
    }

    /**
     * Resolve the client's timezone: X-Timezone header, then tz cookie, then
     * the authenticated user's timezone attribute, then the configured
     * default. Never throws, never 4xx-es the request.
     */
    private function resolveTimezone(Request $request): string
    {
        $default = (string) config('locale-timezone.default_timezone', 'Asia/Seoul');

        $header = $request->header(config('locale-timezone.timezone_header', 'X-Timezone'));

        if (is_string($header) && $this->isValidTimezone($header)) {
            return $header;
        }

        $cookie = $request->cookie(config('locale-timezone.timezone_cookie', 'tz'));

        if (is_string($cookie) && $this->isValidTimezone($cookie)) {
            return $cookie;
        }

        $userTimezone = $this->userAttribute($request, 'user_timezone_attribute', 'timezone');

        if ($userTimezone !== null && $this->isValidTimezone($userTimezone)) {
            return $userTimezone;
        }

        return $default;
    }

    /**
     * Read an attribute off the authenticated user model. Returns null when
     * the user is not authenticated, the configured attribute name is empty,
     * or the value is not a string.
     */
    private function userAttribute(Request $request, string $configKey, string $default): ?string
    {
        $attribute = config("locale-timezone.{$configKey}", $default);

        if (! is_string($attribute) || $attribute === '') {
            return null;
        }

        $value = $request->user()?->getAttribute($attribute);

        return is_string($value) ? $value : null;
    }

    private function isSupportedLocale(string $locale, array $supported): bool
    {
        return $supported !== [] && in_array($locale, $supported, true);
    }

    private function isValidTimezone(string $timezone): bool
    {
        return in_array($timezone, timezone_identifiers_list(), true);
    }
}
