<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supported locales
    |--------------------------------------------------------------------------
    |
    | The list of locales your application supports. The middleware negotiates
    | the Accept-Language header against this list (using q-values) and picks
    | the best match. When nothing matches, the first entry is used.
    |
    */
    'locales' => ['ko', 'en'],

    /*
    |--------------------------------------------------------------------------
    | Fallback locale
    |--------------------------------------------------------------------------
    |
    | Used to seed the scoped fallback context (CLI, tests) and when 'locales'
    | is empty. HTTP requests pick the first supported locale instead.
    |
    */
    'fallback_locale' => env('LOCALE_TIMEZONE_FALLBACK_LOCALE', 'ko'),

    /*
    |--------------------------------------------------------------------------
    | Locale header / cookie
    |--------------------------------------------------------------------------
    |
    | Where to look for the client's locale. The header is useful for SPA and
    | mobile clients (they can set it on every request). The cookie is how
    | classic server-rendered (Blade) apps persist an explicit language choice,
    | because document requests cannot carry custom headers.
    |
    | Cookies set by the application (e.g. redirecting back after a language
    | switcher form) round-trip through Laravel's encrypted cookies. Plain
    | cookies set from JavaScript do not — add 'locale' to the $except list
    | in App\Http\Middleware\EncryptCookies if you want the JS-set path.
    |
    */
    'locale_header' => env('LOCALE_TIMEZONE_LOCALE_HEADER', 'X-Locale'),
    'locale_cookie' => env('LOCALE_TIMEZONE_LOCALE_COOKIE', 'locale'),

    /*
    |--------------------------------------------------------------------------
    | Timezone header / cookie
    |--------------------------------------------------------------------------
    |
    | Same idea for the IANA timezone. Browsers do not send either natively,
    | so clients must opt in — e.g.:
    |
    |     Intl.DateTimeFormat().resolvedOptions().timeZone  // "Asia/Seoul"
    |
    */
    'timezone_header' => env('LOCALE_TIMEZONE_TIMEZONE_HEADER', 'X-Timezone'),
    'timezone_cookie' => env('LOCALE_TIMEZONE_TIMEZONE_COOKIE', 'tz'),

    /*
    |--------------------------------------------------------------------------
    | User attributes
    |--------------------------------------------------------------------------
    |
    | When the header (and cookie) are missing or invalid, the authenticated
    | user's stored timezone/locale is used instead, if the values are valid.
    | Set either key to null to disable that lookup.
    |
    */
    'user_timezone_attribute' => 'timezone',
    'user_locale_attribute' => 'locale',

    /*
    |--------------------------------------------------------------------------
    | Default timezone
    |--------------------------------------------------------------------------
    |
    | Last resort when the header, cookie and the user record provide nothing
    | usable. Usually you want the primary audience of your service here.
    |
    */
    'default_timezone' => env('LOCALE_TIMEZONE_DEFAULT_TIMEZONE', 'Asia/Seoul'),

    /*
    |--------------------------------------------------------------------------
    | Content-Language response header
    |--------------------------------------------------------------------------
    |
    | When enabled, responses carry a Content-Language header with the
    | negotiated locale, so caches can vary correctly on the language.
    |
    */
    'set_content_language' => true,

];
