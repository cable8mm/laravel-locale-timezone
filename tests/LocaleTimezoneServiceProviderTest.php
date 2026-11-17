<?php

namespace Cable8mm\LocaleTimezone\Tests;

use Cable8mm\LocaleTimezone\LocaleTimezone;
use Illuminate\Http\Request;

class LocaleTimezoneServiceProviderTest extends TestCase
{
    public function test_it_merges_the_package_configuration(): void
    {
        $this->assertSame(['ko', 'en'], config('locale-timezone.locales'));
        $this->assertSame('ko', config('locale-timezone.fallback_locale'));
        $this->assertSame('X-Locale', config('locale-timezone.locale_header'));
        $this->assertSame('locale', config('locale-timezone.locale_cookie'));
        $this->assertSame('X-Timezone', config('locale-timezone.timezone_header'));
        $this->assertSame('tz', config('locale-timezone.timezone_cookie'));
        $this->assertSame('Asia/Seoul', config('locale-timezone.default_timezone'));
        $this->assertSame('timezone', config('locale-timezone.user_timezone_attribute'));
        $this->assertSame('locale', config('locale-timezone.user_locale_attribute'));
        $this->assertTrue(config('locale-timezone.set_content_language'));
    }

    public function test_it_registers_a_scoped_fallback_context(): void
    {
        $context = $this->app->make(LocaleTimezone::class);

        $this->assertSame('ko', $context->locale);
        $this->assertSame('Asia/Seoul', $context->timezone);
    }

    public function test_it_registers_a_timezone_macro(): void
    {
        $request = Request::create('/', 'GET');

        $this->assertSame('Asia/Seoul', $request->timezone());
    }

    public function test_it_registers_a_now_macro_with_the_configured_timezone(): void
    {
        $request = Request::create('/', 'GET');

        $this->assertSame('Asia/Seoul', $request->now()->timezoneName);
    }

    public function test_the_now_macro_accepts_an_explicit_timezone(): void
    {
        $request = Request::create('/', 'GET');

        $this->assertSame('America/New_York', $request->now('America/New_York')->timezoneName);
    }
}
