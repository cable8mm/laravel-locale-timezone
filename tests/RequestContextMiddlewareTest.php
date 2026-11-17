<?php

namespace Cable8mm\RequestContext\Tests;

use Cable8mm\RequestContext\Http\Middleware\RequestContextMiddleware;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class RequestContextMiddlewareTest extends TestCase
{
    private const URL = '/test/request-context';

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(RequestContextMiddleware::class)
            ->get(self::URL, function (Request $request) {
                return response()->json([
                    'request_locale' => $request->getLocale(),
                    'app_locale' => app()->getLocale(),
                    'timezone' => $request->timezone(),
                    'now_timezone_name' => $request->now()->timezoneName,
                    'today' => $request->now()->toIso8601String(),
                ]);
            });
    }

    public function test_it_negotiates_the_locale_from_accept_language(): void
    {
        $response = $this->getJson(self::URL, ['Accept-Language' => 'ko-KR,ko;q=0.9,en-US;q=0.8']);

        $response->assertOk()
            ->assertJsonPath('request_locale', 'ko')
            ->assertJsonPath('app_locale', 'ko')
            ->assertHeader('Content-Language', 'ko');
    }

    public function test_it_prefers_higher_quality_values(): void
    {
        $response = $this->getJson(self::URL, ['Accept-Language' => 'en;q=0.8,ko;q=0.9']);

        $response->assertOk()->assertJsonPath('request_locale', 'ko');
    }

    public function test_it_falls_back_when_nothing_matches(): void
    {
        $response = $this->getJson(self::URL, ['Accept-Language' => 'fr-FR,fr;q=0.9']);

        $response->assertOk()->assertJsonPath('request_locale', 'ko');
    }

    public function test_it_falls_back_when_header_is_missing(): void
    {
        // testbench sends "en-us,en;q=0.5" by default; send an empty header
        // to exercise the "no Accept-Language" path.
        $response = $this->getJson(self::URL, ['Accept-Language' => '']);

        $response->assertOk()->assertJsonPath('request_locale', 'ko');
    }

    public function test_it_reads_the_timezone_header(): void
    {
        $response = $this->getJson(self::URL, ['X-Timezone' => 'Asia/Seoul']);

        $response->assertOk()
            ->assertJsonPath('timezone', 'Asia/Seoul')
            ->assertJsonPath('now_timezone_name', 'Asia/Seoul');
    }

    public function test_it_rejects_invalid_timezone_headers(): void
    {
        $response = $this->getJson(self::URL, ['X-Timezone' => 'Mars/Phobos']);

        $response->assertOk()->assertJsonPath('timezone', 'Asia/Seoul');
    }

    public function test_it_rejects_empty_timezone_headers(): void
    {
        $response = $this->getJson(self::URL, ['X-Timezone' => '']);

        $response->assertOk()->assertJsonPath('timezone', 'Asia/Seoul');
    }

    public function test_it_accepts_other_valid_iana_timezones(): void
    {
        $response = $this->getJson(self::URL, ['X-Timezone' => 'America/New_York']);

        $response->assertOk()
            ->assertJsonPath('timezone', 'America/New_York')
            ->assertJsonPath('now_timezone_name', 'America/New_York');
    }

    public function test_it_prefers_the_x_locale_header_over_accept_language(): void
    {
        $response = $this->getJson(self::URL, ['X-Locale' => 'en', 'Accept-Language' => 'ko-KR']);

        $response->assertOk()->assertJsonPath('request_locale', 'en');
    }

    public function test_it_rejects_an_unsupported_x_locale_header(): void
    {
        $response = $this->getJson(self::URL, ['X-Locale' => 'fr', 'Accept-Language' => 'ko-KR,ko;q=0.9']);

        $response->assertOk()->assertJsonPath('request_locale', 'ko');
    }

    public function test_the_locale_cookie_is_used_when_the_header_is_missing(): void
    {
        $response = $this->withUnencryptedCookies(['locale' => 'en'])
            ->withCredentials()
            ->getJson(self::URL, ['Accept-Language' => 'ko-KR']);

        $response->assertOk()->assertJsonPath('request_locale', 'en');
    }

    public function test_an_unsupported_locale_cookie_is_ignored(): void
    {
        $response = $this->withUnencryptedCookies(['locale' => 'fr'])
            ->withCredentials()
            ->getJson(self::URL, ['Accept-Language' => 'ko-KR']);

        $response->assertOk()->assertJsonPath('request_locale', 'ko');
    }

    public function test_the_x_locale_header_wins_over_the_cookie(): void
    {
        $response = $this->withUnencryptedCookies(['locale' => 'ko'])
            ->withCredentials()
            ->getJson(self::URL, ['X-Locale' => 'en']);

        $response->assertOk()->assertJsonPath('request_locale', 'en');
    }

    public function test_the_user_locale_attribute_is_used_when_nothing_else_is_set(): void
    {
        $user = $this->makeUser('Europe/London', 'en');

        $response = $this->actingAs($user)->getJson(self::URL, ['Accept-Language' => 'ko-KR']);

        $response->assertOk()->assertJsonPath('request_locale', 'en');
    }

    public function test_an_invalid_user_locale_attribute_is_ignored(): void
    {
        $user = $this->makeUser('Europe/London', 'fr');

        $response = $this->actingAs($user)->getJson(self::URL, ['Accept-Language' => 'ko-KR']);

        $response->assertOk()->assertJsonPath('request_locale', 'ko');
    }

    public function test_the_timezone_cookie_is_used_when_the_header_is_missing(): void
    {
        $response = $this->withUnencryptedCookies(['tz' => 'America/New_York'])
            ->withCredentials()
            ->getJson(self::URL, []);

        $response->assertOk()
            ->assertJsonPath('timezone', 'America/New_York')
            ->assertJsonPath('now_timezone_name', 'America/New_York');
    }

    public function test_an_invalid_timezone_cookie_is_ignored(): void
    {
        $response = $this->withUnencryptedCookies(['tz' => 'Mars/Phobos'])
            ->withCredentials()
            ->getJson(self::URL, []);

        $response->assertOk()->assertJsonPath('timezone', 'Asia/Seoul');
    }

    public function test_the_x_timezone_header_wins_over_the_cookie(): void
    {
        $response = $this->withUnencryptedCookies(['tz' => 'Europe/London'])
            ->withCredentials()
            ->getJson(self::URL, ['X-Timezone' => 'Asia/Seoul']);

        $response->assertOk()->assertJsonPath('timezone', 'Asia/Seoul');
    }

    public function test_it_uses_the_authenticated_users_timezone_when_the_header_is_missing(): void
    {
        $user = $this->makeUser('Europe/London');

        $response = $this->actingAs($user)->getJson(self::URL, []);

        $response->assertOk()->assertJsonPath('timezone', 'Europe/London');
    }

    public function test_it_ignores_an_invalid_user_timezone(): void
    {
        $user = $this->makeUser('not-a-timezone');

        $response = $this->actingAs($user)->getJson(self::URL, []);

        $response->assertOk()->assertJsonPath('timezone', 'Asia/Seoul');
    }

    public function test_the_header_wins_over_the_user_timezone(): void
    {
        $user = $this->makeUser('Europe/London');

        $response = $this->actingAs($user)->getJson(self::URL, ['X-Timezone' => 'Asia/Seoul']);

        $response->assertOk()->assertJsonPath('timezone', 'Asia/Seoul');
    }

    public function test_the_now_macro_uses_the_resolved_timezone(): void
    {
        $response = $this->getJson(self::URL, ['X-Timezone' => 'Asia/Seoul']);

        // +09:00 offset must be reflected in the serialized instant.
        $response->assertOk();
        $today = $response->json('today');
        $this->assertMatchesRegularExpression('/\+09:00$/', $today);
    }

    private function makeUser(string $timezone, ?string $locale = null): User
    {
        $user = new class extends User
        {
            protected $guarded = [];
        };

        $user->forceFill(array_filter([
            'name' => 'Test User',
            'timezone' => $timezone,
            'locale' => $locale,
        ], fn ($value) => $value !== null));

        return $user;
    }
}
