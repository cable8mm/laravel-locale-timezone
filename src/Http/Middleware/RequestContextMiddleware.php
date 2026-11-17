<?php

namespace Cable8mm\RequestContext\Http\Middleware;

use Cable8mm\RequestContext\RequestContext;
use Closure;
use Illuminate\Http\Request;

/**
 * Resolves the client's context (locale and timezone) for every request.
 *
 * Resolution rules:
 *
 *  Locale
 *   1. Accept-Language header, negotiated against config('request-context.locales').
 *      Symfony returns the first supported locale when nothing matches.
 *   2. config('request-context.fallback_locale') when 'locales' is empty.
 *
 *  Timezone
 *   1. X-Timezone header (name configurable), validated against the IANA list
 *   2. The authenticated user's timezone, when the user model carries a valid one
 *   3. config('request-context.default_timezone')
 *
 * The middleware never calls date_default_timezone_set() and never mutates
 * config('app.timezone'). Instead, the resolved values are exposed through
 *   - $request->setLocale($locale)         (Symfony Request locale)
 *   - $request->timezone()                  (macro, or RequestContext in the container)
 *   - App::setLocale($locale)               (so __() and trans() use the request locale)
 * and through two GETTER macros:
 *   - $request->timezone()
 *   - $request->now()                       (Carbon immutable instance in the request timezone)
 */
class RequestContextMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $locale = $this->resolveLocale($request);
        $timezone = $this->resolveTimezone($request);

        $request->setLocale($locale);
        app()->setLocale($locale);

        app()->instance(RequestContext::class, new RequestContext($locale, $timezone));

        $response = $next($request);

        if (config('request-context.set_content_language', true)) {
            $response->headers->set('Content-Language', $locale);
        }

        return $response;
    }

    /**
     * Negotiate the locale from the Accept-Language header against the
     * supported list. Uses Symfony's negotiator; invalid values fall back
     * to the configured fallback locale.
     */
    private function resolveLocale(Request $request): string
    {
        $supported = (array) config('request-context.locales', ['ko', 'en']);
        $fallback = (string) config('request-context.fallback_locale', 'ko');

        if ($supported === []) {
            return $fallback;
        }

        // Symfony's getPreferredLanguage() parses q-values and returns the
        // best match from $supported, or null when nothing matches.
        return $request->getPreferredLanguage($supported) ?? $fallback;
    }

    /**
     * Resolve the client's timezone. Invalid or missing headers fall back
     * to the authenticated user's stored timezone, then to the configured
     * default. Never throws, never 4xx-es the request.
     */
    private function resolveTimezone(Request $request): string
    {
        $default = (string) config('request-context.default_timezone', 'Asia/Seoul');

        $header = $request->header(config('request-context.timezone_header', 'X-Timezone'));

        if (is_string($header) && $this->isValidTimezone($header)) {
            return $header;
        }

        $attribute = config('request-context.user_timezone_attribute', 'timezone');

        if (is_string($attribute) && $attribute !== '') {
            $userTimezone = $request->user()?->getAttribute($attribute);

            if (is_string($userTimezone) && $this->isValidTimezone($userTimezone)) {
                return $userTimezone;
            }
        }

        return $default;
    }

    private function isValidTimezone(string $timezone): bool
    {
        return in_array($timezone, timezone_identifiers_list(), true);
    }
}
