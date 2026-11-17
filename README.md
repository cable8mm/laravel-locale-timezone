# Laravel Request Context

[![Latest Stable Version](https://poser.pugx.org/cable8mm/laravel-request-context/v)](https://packagist.org/packages/cable8mm/laravel-request-context)
[![Total Downloads](https://poser.pugx.org/cable8mm/laravel-request-context/downloads)](https://packagist.org/packages/cable8mm/laravel-request-context)
[![License](https://poser.pugx.org/cable8mm/laravel-request-context/license)](https://packagist.org/packages/cable8mm/laravel-request-context)

Thin per-request **locale** and **timezone** for Laravel. One middleware, two headers, sane fallbacks — without the machinery of a full route-localization package.

```php
$request->getLocale();  // "ko"  — the locale negotiated for this request (Accept-Language)
App::getLocale();       // "ko"  — what __() and trans() use (same value, set by the middleware)
$request->timezone();   // "Asia/Seoul" — the client's timezone (X-Timezone header)
$request->now();        // CarbonImmutable in the request timezone
```

## Why this package exists

Laravel decides three things globally and leaves the rest to you:

- `config('app.timezone')` becomes the PHP default timezone (`date_default_timezone_set()`) at bootstrap. There is no per-request timezone in the framework.
- `__()` and `trans()` follow `App::getLocale()` — the translator's locale. No per-request translator exists. You must call `App::setLocale()` yourself (conventionally, in a middleware).
- The `Request` object itself knows nothing about timezones. Symfony's request ships a `getLocale()`/`setLocale()` pair, but nothing in Laravel wires it to useful behavior — and `Accept-Language` is not consumed anywhere in the framework either.

This package is the missing glue: a middleware that negotiates the client's `Accept-Language` and reads the client's `X-Timezone` header, and makes both available consistently on the request, on the container and on the translator. It is intentionally thin and Laravel-idiomatic.

It is **not** [mcamara/laravel-localization](https://github.com/mcamara/laravel-localization): that package builds localized routes (`/ko/about`, `/en/about`) and ships a translation repository. If you don't need route localization, this package is all you need.

## Design decisions

1. **`app.timezone` stays fixed — the application's canonical timezone.** A user's preference never mutates the application's configuration. The client's timezone lives in a **request-scoped context** bound in the container (`Cable8mm\RequestContext\RequestContext`) for the duration of the request only.
2. **The browser does not send its timezone.** There is no standard HTTP header carrying a timezone. Clients must send one explicitly (e.g. our `X-Timezone` convention — see Client setup). The middleware validates it against the IANA list and falls back silently; it never rejects the request.
3. **The header always wins; the user profile is the fallback; a configured default is the last resort.** When the request is authenticated, the stored `timezone` attribute of the user model is used if the header is missing or invalid. This is the correct behavior for members who travel.
4. **`App::getLocale()` and `Request::getLocale()` are kept consistent on purpose.** `Request::getLocale()` describes what the client asked for; `App::getLocale()` drives `__()`/`trans()`. The middleware sets both to the negotiated value so no code path ever sees "the client asked for Korean but the page rendered in English".
5. **No global state is mutated for the timezone.** `date_default_timezone_set()` is never called. Everything Laravel does by default (`Carbon::now()`, `today()`, timestamps on model instances) continues to use `config('app.timezone')`. User-facing time math should take the request timezone explicitly via `$request->now()` or `app(RequestContext::class)->timezone`.

## Installation

```bash
composer require cable8mm/laravel-request-context
```

Requires PHP 8.2+ and Laravel 10–13.

### Register the middleware

```php
// app/Http/Kernel.php (Laravel 10)
protected $middleware = [
    // ...
    \Cable8mm\RequestContext\Http\Middleware\RequestContextMiddleware::class,
];
```

On Laravel 11+ (no `Http/Kernel.php`), register it in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->append(\Cable8mm\RequestContext\Http\Middleware\RequestContextMiddleware::class);
})
```

### Publish the configuration (optional)

```bash
php artisan vendor:publish --tag=config
```

## Usage

### Locale

```php
$locale = app()->getLocale();        // "ko" — what __() uses
$locale = $request->getLocale();     // "ko" — same value on the request
$html   = __('welcome_message');     // localized according to the request
```

### Timezone

```php
$tz  = $request->timezone();         // "Asia/Seoul"
$now = $request->now();              // CarbonImmutable in Asia/Seoul

// Explicit time math for the client:
$badge = SomeBadge::make($started, $finished, now: $request->now());
```

### Outside HTTP

In CLI (Horizon workers, schedules, tinker) there is no middleware, so the scoped binding resolves to the configured defaults — `fallback_locale` and `default_timezone`. This keeps queued jobs and admin tooling deterministic without any client information.

## Client setup

Browsers do not volunteer a timezone. Ship the following in your frontend bootstrap:

```js
const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone; // e.g. "Asia/Seoul"

// axios
axios.defaults.headers.common['X-Timezone'] = timezone;

// fetch
fetch(url, { headers: { 'X-Timezone': timezone } });
```

On iOS/Android, read the device timezone and add it as a request header in the same way (`TimeZone.current` / `TimeZone.getDefault()`).

## The timezone contract (why storage stays boring)

This package deliberately says **nothing** about your database. Agree on this contract and stop worrying about timezones:

1. **Store everything in UTC** in the database (`DATETIME` columns or `timestamptz`). Never store local wall time.
2. **Keep `config('app.timezone')` as your application-wide canonical timezone** (commonly `UTC` for multi-region services or your local audience, e.g. `Asia/Seoul` — your choice; it is *your* service's preference, not anyone's request).
3. **Always speak ISO 8601 with an explicit offset on the wire** — e.g. `2026-11-17T06:20:00Z` — so clients render in their own timezone without guessing. Do not emit timezone-less timestamps.
4. **Parse with an explicit timezone** and convert to UTC before storing. `Carbon::parse($value, $tz)->setTimezone('UTC')`.
5. **Localize at the edges only** (API serialization for a localized string, e.g. a Korean badge label) using the request timezone from this package.
6. If you use MySQL, avoid `TIMESTAMP` columns — they shift with the session `time_zone`. Use `DATETIME` and store UTC, or set the connection `timezone` in `config/database.php` (`'timezone' => '+00:00'`) so `NOW()` and friends agree.
7. Laravel's `DB::raw('NOW()')`, `CURRENT_TIMESTAMP`, and the scheduler all follow their own clock sources. Keep `config('app.timezone')`, the DB session and your OS in agreement, or avoid them in favor of application time.

### Why not a DB/session that "just handles it"?

PostgreSQL's `timestamptz` normalizes on write, MySQL's `TIMESTAMP` converts via session time_zone — both "work", until someone configures the session differently on one of three environments and a column silently starts shifting by 9 hours. DATETIME + always-UTC + app-level parsing removes the variable entirely.

### Why not `date_default_timezone_set()` in a middleware?

Because it mutates process-global state: unrelated libraries, queued jobs and model callbacks would all observe a different timezone, the leak persists across Octane requests, and correctness would depend on middleware ordering. This package never calls it.

## How `__()` behaves

`__()` reads the translator's locale (`App::getLocale()`); there is no per-request translator in Laravel. This package sets `App::setLocale()` to the negotiated locale in the same middleware call. Consequences, deliberately chosen:

| Context | `__()` language |
| --- | --- |
| HTTP with `Accept-Language: ko` | Korean |
| HTTP with `Accept-Language: fr` (unsupported) | the first supported locale in `locales` |
| HTTP with an empty / missing `Accept-Language` | the first supported locale in `locales` (or `fallback_locale` when `locales` is empty) |
| CLI / Horizon / schedules | `config('app.locale')` — untouched |

If you ever need strict separation — e.g. admin UI always in English but emails in the recipient's language — pass the locale explicitly: `__('key', [], 'ko')`. It's the only place Laravel exposes the third argument.

## How `Request::getLocale()` and `App::getLocale()` differ

- `Request::getLocale()` (Symfony) — **what the client asked for.** Present always; negotiated by this package against `config('request-context.locales')`.
- `App::getLocale()` — **what the translator currently uses.** Set by this package to the same value on HTTP requests; never touched on CLI.

They are equal inside HTTP requests handled by the middleware, and deliberately different on CLI (`en` default on the request, `config('app.locale')` on the translator).

## Configuration

| Key | Default | Meaning |
| --- | --- | --- |
| `locales` | `['ko', 'en']` | Supported locales, negotiated against `Accept-Language` (q-values honored). Unmatched requests get the **first** entry — put your primary locale first. |
| `fallback_locale` | `ko` | Used when `locales` is empty, and to seed the CLI/scoped fallback context |
| `timezone_header` | `X-Timezone` | Header to look for the IANA timezone |
| `default_timezone` | `Asia/Seoul` | Used when the header is missing/invalid and no user timezone exists |
| `user_timezone_attribute` | `timezone` | Authenticatable model attribute to read as the user's timezone. Set to `null` to disable |
| `set_content_language` | `true` | Add a `Content-Language` header to every response (caches vary on it) |

## Octane

The package binds `RequestContext` in the container scoped to the request; Octane flushes scoped bindings per request. No global state is mutated, so there is nothing to leak between requests.

## Failure modes

Nothing here throws or 4xx-es: missing or garbage headers resolve to the configured fallbacks. The middleware never returns a validation error response.

## FAQ

**Does the browser send a timezone automatically?**
No. There is no such header in the Fetch/XHR spec, and no deployed client hint carries one. `Accept-Language` carries locale only, not timezone. That's why this package defines `X-Timezone` as an application convention — your frontend bootstrap sets it from `Intl.DateTimeFormat().resolvedOptions().timeZone`.

**Why not use [whitecube/laravel-timezones](https://github.com/whitecube/laravel-timezones)?**
Use it when you want UTC-storage/normalization in model casts (`TimezonedDatetime`) as well. It is a conversion layer, not a negotiation layer; you still need middleware for `Accept-Language` and the timezone header. If all you need is per-request resolution, this package is thinner. They combine fine — this package for resolution, whitecube for casts.

**Why not mcamara/laravel-localization?**
It ships route localization (`/ko/about`, `/en/about`), translation repositories and a lot of configuration surface. This package only resolves the client's locale and timezone per request. Pick it if you want localized routes; otherwise this.

**Why not `mcamara` for locale negotiation?**
Seems redundant. `Symfony\Component\HttpFoundation\Request::getPreferredLanguage()` (used here) already parses q-values correctly, and `App::setLocale()` is all you need if you don't need URL segments per locale.

## Development

```bash
composer install
composer test
composer lint
```

## Credits

- [Samgu Lee](https://github.com/cable8mm)

## License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).
