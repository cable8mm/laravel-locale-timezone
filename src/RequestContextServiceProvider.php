<?php

namespace Cable8mm\RequestContext;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class RequestContextServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/request-context.php',
            'request-context'
        );

        // Fallback context for contexts without the middleware (CLI, tests).
        $this->app->scoped(RequestContext::class, fn () => new RequestContext(
            (string) config('request-context.fallback_locale', 'ko'),
            (string) config('request-context.default_timezone', 'Asia/Seoul')
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/request-context.php' => config_path('request-context.php'),
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
            return app(RequestContext::class)->timezone;
        });

        Request::macro('now', function (?string $timezone = null) {
            $tz = $timezone ?? $this->timezone();

            return Carbon::now($tz);
        });
    }
}
