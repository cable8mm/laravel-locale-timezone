<?php

namespace Cable8mm\LocaleTimezone;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class LocaleTimezoneServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/locale-timezone.php',
            'locale-timezone'
        );

        // Fallback context for contexts without the middleware (CLI, tests).
        $this->app->scoped(LocaleTimezone::class, fn () => new LocaleTimezone(
            (string) config('locale-timezone.fallback_locale', 'ko'),
            (string) config('locale-timezone.default_timezone', 'Asia/Seoul')
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/locale-timezone.php' => config_path('locale-timezone.php'),
        ], 'config');

        $this->registerMacros();
    }

    /**
     * Attach the timezone convenience macros to the Request object, so
     * call sites read naturally: $request->timezone() and $request->now().
     *
     * The macros never throw; outside a request with the middleware applied
     * they fall back to the configured defaults.
     */
    private function registerMacros(): void
    {
        Request::macro('timezone', function () {
            return app(LocaleTimezone::class)->timezone;
        });

        Request::macro('now', function (?string $timezone = null) {
            $tz = $timezone ?? $this->timezone();

            return Carbon::now($tz);
        });
    }
}
