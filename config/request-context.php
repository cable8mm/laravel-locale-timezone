<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supported locales
    |--------------------------------------------------------------------------
    |
    | The list of locales your application supports. The middleware negotiates
    | the Accept-Language header against this list (using q-values) and picks
    | the best match. When nothing matches, the fallback locale is used.
    |
    */
    'locales' => ['ko', 'en'],

    /*
    |--------------------------------------------------------------------------
    | Fallback locale
    |--------------------------------------------------------------------------
    |
    | The locale to use when the client does not send an Accept-Language
    | header, or it does not match any supported locale.
    |
    */
    'fallback_locale' => env('REQUEST_CONTEXT_FALLBACK_LOCALE', 'ko'),

    /*
    |--------------------------------------------------------------------------
    | Timezone header
    |--------------------------------------------------------------------------
    |
    | The header clients use to tell the application their IANA timezone.
    | Browsers do not send a timezone header automatically, so clients must
    | be configured to send it, e.g.:
    |
    |     Intl.DateTimeFormat().resolvedOptions().timeZone  // "Asia/Seoul"
    |
    | Note: this is an application-level convention, not a web standard.
    |
    */
    'timezone_header' => env('REQUEST_CONTEXT_TIMEZONE_HEADER', 'X-Timezone'),

    /*
    |--------------------------------------------------------------------------
    | Default timezone
    |--------------------------------------------------------------------------
    |
    | Used when the header is missing or invalid, and there is no (or an
    | invalid) timezone stored on the authenticated user. Usually you want
    | the primary audience of your service here.
    |
    */
    'default_timezone' => env('REQUEST_CONTEXT_DEFAULT_TIMEZONE', 'Asia/Seoul'),

    /*
    |--------------------------------------------------------------------------
    | Resolve the timezone of the authenticated user
    |--------------------------------------------------------------------------
    |
    | When the X-Timezone header is missing or invalid, the authenticated
    | user's timezone is used if this is enabled and the value stored on
    | the user is a valid IANA identifier. Change it to any attribute name,
    | or set it to null to disable this lookup.
    |
    */
    'user_timezone_attribute' => 'timezone',

    /*
    |--------------------------------------------------------------------------
    | Content-Language response header
    |--------------------------------------------------------------------------
    |
    | When enabled, responses will carry a Content-Language header with the
    | negotiated locale, so caches can vary correctly on the language.
    |
    */
    'set_content_language' => true,

];
