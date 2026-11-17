<?php

namespace Cable8mm\RequestContext\Tests;

use Cable8mm\RequestContext\RequestContext;
use Illuminate\Http\Request;

class RequestContextServiceProviderTest extends TestCase
{
    public function test_it_merges_the_package_configuration(): void
    {
        $this->assertSame(['ko', 'en'], config('request-context.locales'));
        $this->assertSame('ko', config('request-context.fallback_locale'));
        $this->assertSame('X-Locale', config('request-context.locale_header'));
        $this->assertSame('locale', config('request-context.locale_cookie'));
        $this->assertSame('X-Timezone', config('request-context.timezone_header'));
        $this->assertSame('tz', config('request-context.timezone_cookie'));
        $this->assertSame('Asia/Seoul', config('request-context.default_timezone'));
        $this->assertSame('timezone', config('request-context.user_timezone_attribute'));
        $this->assertSame('locale', config('request-context.user_locale_attribute'));
        $this->assertTrue(config('request-context.set_content_language'));
    }

    public function test_it_registers_a_scoped_fallback_context(): void
    {
        $context = $this->app->make(RequestContext::class);

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
