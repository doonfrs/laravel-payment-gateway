<?php

namespace Trinavo\PaymentGateway\Tests\Unit;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Orchestra\Testbench\TestCase;
use Trinavo\PaymentGateway\Providers\PaymentGatewayServiceProvider;
use Trinavo\PaymentGateway\Services\LocaleService;

class HelpersTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            PaymentGatewayServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('payment-gateway.locale.default_locale', 'en');
        $app['config']->set('payment-gateway.locale.available_locales', ['en', 'ar', 'fr']);
        $app['config']->set('payment-gateway.locale.detect_from_url', true);
    }

    /** @test */
    public function payment_gateway_localized_url_helper_exists()
    {
        $this->assertTrue(function_exists('payment_gateway_localized_url'));
    }

    /** @test */
    public function payment_gateway_url_locale_helper_exists()
    {
        $this->assertTrue(function_exists('payment_gateway_url_locale'));
    }

    /** @test */
    public function payment_gateway_current_locale_helper_exists()
    {
        $this->assertTrue(function_exists('payment_gateway_current_locale'));
    }

    /** @test */
    public function payment_gateway_localized_url_generates_localized_url_for_non_default_locale()
    {
        URL::forceRootUrl('http://localhost');

        $result = payment_gateway_localized_url('http://localhost/payment-gateway/checkout', 'ar');
        $this->assertStringContainsString('/ar/', $result);
        $this->assertStringContainsString('payment-gateway/checkout', $result);
    }

    /** @test */
    public function payment_gateway_localized_url_removes_locale_for_default_locale()
    {
        URL::forceRootUrl('http://localhost');

        $result = payment_gateway_localized_url('http://localhost/ar/payment-gateway/checkout', 'en');
        $this->assertStringNotContainsString('/ar/', $result);
        $this->assertStringContainsString('payment-gateway/checkout', $result);
    }

    /** @test */
    public function payment_gateway_localized_url_uses_current_url_when_url_is_null()
    {
        URL::forceRootUrl('http://localhost');
        $this->get('http://localhost/current-path');
        App::setLocale('ar');

        $result = payment_gateway_localized_url();
        $this->assertStringContainsString('/ar/', $result);
    }

    /** @test */
    public function payment_gateway_localized_url_uses_current_locale_when_locale_is_null()
    {
        URL::forceRootUrl('http://localhost');
        App::setLocale('fr');

        $result = payment_gateway_localized_url('http://localhost/payment-gateway');
        $this->assertStringContainsString('/fr/', $result);
        $this->assertStringContainsString('payment-gateway', $result);
    }

    /** @test */
    public function payment_gateway_localized_url_preserves_query_string()
    {
        URL::forceRootUrl('http://localhost');

        $result = payment_gateway_localized_url('http://localhost/payment-gateway?foo=bar', 'ar');
        $this->assertStringContainsString('/ar/', $result);
        $this->assertStringContainsString('payment-gateway', $result);
        $this->assertStringContainsString('foo=bar', $result);
    }

    /** @test */
    public function payment_gateway_localized_url_replaces_existing_locale()
    {
        URL::forceRootUrl('http://localhost');

        $result = payment_gateway_localized_url('http://localhost/fr/payment-gateway', 'ar');
        $this->assertStringContainsString('/ar/', $result);
        $this->assertStringNotContainsString('/fr/', $result);
        $this->assertStringContainsString('payment-gateway', $result);
    }

    /** @test */
    public function payment_gateway_localized_url_handles_complex_paths()
    {
        URL::forceRootUrl('http://localhost');

        $result = payment_gateway_localized_url('http://localhost/payment-gateway/checkout/order/123', 'ar');
        $this->assertStringContainsString('/ar/', $result);
        $this->assertStringContainsString('payment-gateway/checkout/order/123', $result);
    }

    /** @test */
    public function payment_gateway_url_locale_extracts_locale_from_url()
    {
        $url = 'https://example.com/ar/payment-gateway/checkout';
        $result = payment_gateway_url_locale($url);
        $this->assertEquals('ar', $result);
    }

    /** @test */
    public function payment_gateway_url_locale_returns_null_when_no_locale_in_url()
    {
        $url = 'https://example.com/payment-gateway/checkout';
        $result = payment_gateway_url_locale($url);
        $this->assertNull($result);
    }

    /** @test */
    public function payment_gateway_url_locale_returns_null_for_invalid_locale()
    {
        $url = 'https://example.com/xx/payment-gateway/checkout';
        $result = payment_gateway_url_locale($url);
        $this->assertNull($result);
    }

    /** @test */
    public function payment_gateway_url_locale_finds_locale_in_any_segment()
    {
        $url = 'https://example.com/some/path/ar/payment-gateway';
        $result = payment_gateway_url_locale($url);
        $this->assertEquals('ar', $result);
    }

    /** @test */
    public function payment_gateway_url_locale_handles_url_with_query_string()
    {
        $url = 'https://example.com/ar/payment-gateway?foo=bar';
        $result = payment_gateway_url_locale($url);
        $this->assertEquals('ar', $result);
    }

    /** @test */
    public function payment_gateway_url_locale_handles_root_url_with_locale()
    {
        $url = 'https://example.com/ar';
        $result = payment_gateway_url_locale($url);
        $this->assertEquals('ar', $result);
    }

    /** @test */
    public function payment_gateway_url_locale_handles_trailing_slash()
    {
        $url = 'https://example.com/ar/payment-gateway/';
        $result = payment_gateway_url_locale($url);
        $this->assertEquals('ar', $result);
    }

    /** @test */
    public function payment_gateway_url_locale_handles_localhost()
    {
        $url = 'http://localhost/ar/payment-gateway';
        $result = payment_gateway_url_locale($url);
        $this->assertEquals('ar', $result);
    }

    /** @test */
    public function payment_gateway_url_locale_handles_port_numbers()
    {
        $url = 'https://example.com:8080/ar/payment-gateway';
        $result = payment_gateway_url_locale($url);
        $this->assertEquals('ar', $result);
    }

    /** @test */
    public function payment_gateway_current_locale_returns_application_locale()
    {
        App::setLocale('ar');
        $result = payment_gateway_current_locale();
        $this->assertEquals('ar', $result);
    }

    /** @test */
    public function payment_gateway_current_locale_returns_default_when_not_set()
    {
        // Laravel defaults to 'en' if not set
        $result = payment_gateway_current_locale();
        $this->assertIsString($result);
    }

    /** @test */
    public function payment_gateway_current_locale_reflects_locale_changes()
    {
        App::setLocale('en');
        $this->assertEquals('en', payment_gateway_current_locale());

        App::setLocale('ar');
        $this->assertEquals('ar', payment_gateway_current_locale());

        App::setLocale('fr');
        $this->assertEquals('fr', payment_gateway_current_locale());
    }

    /** @test */
    public function helpers_work_together_to_switch_locales()
    {
        URL::forceRootUrl('http://localhost');

        // Start with English URL
        $englishUrl = 'http://localhost/payment-gateway/checkout';

        // Convert to Arabic
        $arabicUrl = payment_gateway_localized_url($englishUrl, 'ar');
        $this->assertStringContainsString('/ar/', $arabicUrl);

        // Verify we can detect the locale
        $this->assertEquals('ar', payment_gateway_url_locale($arabicUrl));

        // Convert back to English (default)
        $backToEnglish = payment_gateway_localized_url($arabicUrl, 'en');
        $this->assertStringNotContainsString('/ar/', $backToEnglish);

        // Verify no locale in URL
        $this->assertNull(payment_gateway_url_locale($backToEnglish));
    }

    /** @test */
    public function helpers_resolve_service_from_container()
    {
        URL::forceRootUrl('http://localhost');

        $service = $this->app->make(LocaleService::class);
        $this->assertInstanceOf(LocaleService::class, $service);

        // Verify helpers use the same service instance
        $url1 = payment_gateway_localized_url('http://localhost/payment-gateway', 'ar');
        $url2 = $service->getLocalizedUrl('http://localhost/payment-gateway', 'ar');

        $this->assertEquals($url1, $url2);
    }

    /** @test */
    public function helpers_respect_runtime_configuration_changes()
    {
        $service = $this->app->make(LocaleService::class);
        $service->setAvailableLocales(['en', 'es']);

        // 'ar' is no longer available
        $url = 'https://example.com/ar/payment-gateway';
        $this->assertNull(payment_gateway_url_locale($url));

        // 'es' should work
        $url2 = 'https://example.com/es/payment-gateway';
        $this->assertEquals('es', payment_gateway_url_locale($url2));
    }

    /** @test */
    public function payment_gateway_localized_url_handles_empty_string_url()
    {
        URL::forceRootUrl('http://localhost');
        $this->get('http://localhost/current');
        App::setLocale('ar');

        $result = payment_gateway_localized_url('');
        // Should fall back to current URL
        $this->assertStringContainsString('/ar', $result);
    }

    /** @test */
    public function helpers_handle_multiple_consecutive_calls()
    {
        URL::forceRootUrl('http://localhost');

        $url = 'http://localhost/payment-gateway';

        // Chain multiple locale changes
        $ar = payment_gateway_localized_url($url, 'ar');
        $this->assertStringContainsString('/ar/', $ar);

        $fr = payment_gateway_localized_url($ar, 'fr');
        $this->assertStringContainsString('/fr/', $fr);
        $this->assertStringNotContainsString('/ar/', $fr);

        $en = payment_gateway_localized_url($fr, 'en');
        $this->assertStringNotContainsString('/fr/', $en);
        $this->assertStringNotContainsString('/ar/', $en);
    }

    /** @test */
    public function payment_gateway_url_locale_works_with_all_configured_locales()
    {
        $locales = ['en', 'ar', 'fr'];

        foreach ($locales as $locale) {
            $url = "https://example.com/{$locale}/payment-gateway";
            $detected = payment_gateway_url_locale($url);
            $this->assertEquals($locale, $detected, "Failed to detect locale: {$locale}");
        }
    }

    /** @test */
    public function helpers_are_not_redeclared()
    {
        // Helpers should be wrapped in if (!function_exists())
        // Re-require the helpers file
        require_once __DIR__.'/../../src/helpers.php';

        // Should not throw any errors
        $this->assertTrue(function_exists('payment_gateway_localized_url'));
        $this->assertTrue(function_exists('payment_gateway_url_locale'));
        $this->assertTrue(function_exists('payment_gateway_current_locale'));
    }
}
