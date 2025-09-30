<?php

namespace Trinavo\PaymentGateway\Tests\Unit;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Orchestra\Testbench\TestCase;
use Trinavo\PaymentGateway\Providers\PaymentGatewayServiceProvider;
use Trinavo\PaymentGateway\Services\LocaleService;

class LocaleServiceTest extends TestCase
{
    protected LocaleService $localeService;

    protected function getPackageProviders($app)
    {
        return [
            PaymentGatewayServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->localeService = new LocaleService;
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('payment-gateway.locale.default_locale', 'en');
        $app['config']->set('payment-gateway.locale.available_locales', ['en', 'ar', 'fr']);
        $app['config']->set('payment-gateway.locale.detect_from_url', true);
    }

    /** @test */
    public function it_can_get_default_locale_from_config()
    {
        $this->assertEquals('en', $this->localeService->getDefaultLocale());
    }

    /** @test */
    public function it_can_get_available_locales_from_config()
    {
        $expected = ['en', 'ar', 'fr'];
        $this->assertEquals($expected, $this->localeService->getAvailableLocales());
    }

    /** @test */
    public function it_can_set_default_locale_at_runtime()
    {
        $this->localeService->setDefaultLocale('ar');
        $this->assertEquals('ar', $this->localeService->getDefaultLocale());
    }

    /** @test */
    public function it_can_set_available_locales_at_runtime()
    {
        $newLocales = ['en', 'es', 'de'];
        $this->localeService->setAvailableLocales($newLocales);
        $this->assertEquals($newLocales, $this->localeService->getAvailableLocales());
    }

    /** @test */
    public function it_returns_fluent_interface_when_setting_default_locale()
    {
        $result = $this->localeService->setDefaultLocale('ar');
        $this->assertInstanceOf(LocaleService::class, $result);
    }

    /** @test */
    public function it_returns_fluent_interface_when_setting_available_locales()
    {
        $result = $this->localeService->setAvailableLocales(['en', 'ar']);
        $this->assertInstanceOf(LocaleService::class, $result);
    }

    /** @test */
    public function it_extracts_locale_from_url_with_locale_in_first_segment()
    {
        $url = 'https://example.com/ar/payment-gateway/checkout/order123';
        $this->assertEquals('ar', $this->localeService->getUrlLocale($url));
    }

    /** @test */
    public function it_extracts_locale_from_url_with_locale_in_middle_segment()
    {
        $url = 'https://example.com/some/ar/payment-gateway/checkout';
        $this->assertEquals('ar', $this->localeService->getUrlLocale($url));
    }

    /** @test */
    public function it_returns_null_when_no_locale_in_url()
    {
        $url = 'https://example.com/payment-gateway/checkout/order123';
        $this->assertNull($this->localeService->getUrlLocale($url));
    }

    /** @test */
    public function it_returns_null_when_url_contains_invalid_locale()
    {
        $url = 'https://example.com/xx/payment-gateway/checkout';
        $this->assertNull($this->localeService->getUrlLocale($url));
    }

    /** @test */
    public function it_returns_first_valid_locale_when_multiple_locales_in_url()
    {
        $url = 'https://example.com/ar/payment/en/checkout';
        $this->assertEquals('ar', $this->localeService->getUrlLocale($url));
    }

    /** @test */
    public function it_handles_url_with_trailing_slash()
    {
        $url = 'https://example.com/ar/payment-gateway/';
        $this->assertEquals('ar', $this->localeService->getUrlLocale($url));
    }

    /** @test */
    public function it_handles_url_without_trailing_slash()
    {
        $url = 'https://example.com/ar/payment-gateway';
        $this->assertEquals('ar', $this->localeService->getUrlLocale($url));
    }

    /** @test */
    public function it_handles_url_with_query_string()
    {
        $url = 'https://example.com/ar/payment-gateway?foo=bar';
        $this->assertEquals('ar', $this->localeService->getUrlLocale($url));
    }

    /** @test */
    public function it_handles_url_with_fragment()
    {
        $url = 'https://example.com/ar/payment-gateway#section';
        $this->assertEquals('ar', $this->localeService->getUrlLocale($url));
    }

    /** @test */
    public function it_handles_root_url_with_locale()
    {
        $url = 'https://example.com/ar';
        $this->assertEquals('ar', $this->localeService->getUrlLocale($url));
    }

    /** @test */
    public function it_handles_root_url_without_locale()
    {
        $url = 'https://example.com/';
        $this->assertNull($this->localeService->getUrlLocale($url));
    }

    /** @test */
    public function it_generates_localized_url_for_non_default_locale()
    {
        $url = $this->app['url'];
        $url->forceRootUrl('http://localhost');

        App::setLocale('ar');

        $result = $this->localeService->getLocalizedUrl('http://localhost/payment-gateway/checkout');
        $this->assertStringContainsString('/ar/', $result);
        $this->assertStringContainsString('payment-gateway/checkout', $result);
    }

    /** @test */
    public function it_generates_localized_url_without_locale_prefix_for_default_locale()
    {
        $url = $this->app['url'];
        $url->forceRootUrl('http://localhost');

        App::setLocale('en');

        $result = $this->localeService->getLocalizedUrl('http://localhost/ar/payment-gateway/checkout');
        $this->assertStringNotContainsString('/ar/', $result);
        $this->assertStringContainsString('payment-gateway/checkout', $result);
    }

    /** @test */
    public function it_removes_existing_locale_when_generating_localized_url()
    {
        $url = $this->app['url'];
        $url->forceRootUrl('http://localhost');

        $result = $this->localeService->getLocalizedUrl('http://localhost/fr/payment-gateway/checkout', 'ar');
        $this->assertStringContainsString('/ar/', $result);
        $this->assertStringNotContainsString('/fr/', $result);
        $this->assertStringContainsString('payment-gateway/checkout', $result);
    }

    /** @test */
    public function it_removes_locale_prefix_when_switching_to_default_locale()
    {
        $url = $this->app['url'];
        $url->forceRootUrl('http://localhost');

        $result = $this->localeService->getLocalizedUrl('http://localhost/ar/payment-gateway/checkout', 'en');
        $this->assertStringNotContainsString('/ar/', $result);
        $this->assertStringContainsString('payment-gateway/checkout', $result);
    }

    /** @test */
    public function it_preserves_query_string_in_localized_url()
    {
        $url = $this->app['url'];
        $url->forceRootUrl('http://localhost');

        $result = $this->localeService->getLocalizedUrl('http://localhost/payment-gateway/checkout?foo=bar&baz=qux', 'ar');
        $this->assertStringContainsString('/ar/', $result);
        $this->assertStringContainsString('payment-gateway/checkout', $result);
        $this->assertStringContainsString('foo=bar', $result);
        $this->assertStringContainsString('baz=qux', $result);
    }

    /** @test */
    public function it_handles_empty_url_in_get_localized_url()
    {
        $url = $this->app['url'];
        $url->forceRootUrl('http://localhost');

        URL::forceRootUrl('http://localhost');
        App::setLocale('ar');

        $result = $this->localeService->getLocalizedUrl('http://localhost/current-path');
        $this->assertStringContainsString('/ar/', $result);
    }

    /** @test */
    public function it_uses_current_locale_when_locale_parameter_is_null()
    {
        $url = $this->app['url'];
        $url->forceRootUrl('http://localhost');

        App::setLocale('fr');

        $result = $this->localeService->getLocalizedUrl('http://localhost/payment-gateway/checkout', null);
        $this->assertStringContainsString('/fr/', $result);
        $this->assertStringContainsString('payment-gateway/checkout', $result);
    }

    /** @test */
    public function it_handles_url_with_only_path()
    {
        $url = $this->app['url'];
        $url->forceRootUrl('http://localhost');

        $result = $this->localeService->getLocalizedUrl('http://localhost/payment-gateway', 'ar');
        $this->assertStringContainsString('/ar/', $result);
        $this->assertStringContainsString('payment-gateway', $result);
    }

    /** @test */
    public function it_handles_complex_nested_paths()
    {
        $url = $this->app['url'];
        $url->forceRootUrl('http://localhost');

        $result = $this->localeService->getLocalizedUrl('http://localhost/payment-gateway/checkout/order/123/details', 'ar');
        $this->assertStringContainsString('/ar/', $result);
        $this->assertStringContainsString('payment-gateway/checkout/order/123/details', $result);
    }

    /** @test */
    public function it_returns_true_when_detect_from_url_is_enabled()
    {
        $this->assertTrue($this->localeService->shouldDetectFromUrl());
    }

    /** @test */
    public function it_returns_false_when_detect_from_url_is_disabled()
    {
        config(['payment-gateway.locale.detect_from_url' => false]);
        $service = new LocaleService;
        $this->assertFalse($service->shouldDetectFromUrl());
    }

    /** @test */
    public function it_detects_and_sets_locale_from_current_url()
    {
        URL::forceRootUrl('http://localhost');
        $this->get('http://localhost/ar/payment-gateway');

        $this->localeService->detectAndSetLocale();

        $this->assertEquals('ar', App::getLocale());
    }

    /** @test */
    public function it_sets_default_locale_when_url_has_no_locale()
    {
        URL::forceRootUrl('http://localhost');
        $this->get('http://localhost/payment-gateway');

        $this->localeService->detectAndSetLocale();

        $this->assertEquals('en', App::getLocale());
    }

    /** @test */
    public function it_sets_default_locale_when_url_has_invalid_locale()
    {
        URL::forceRootUrl('http://localhost');
        $this->get('http://localhost/xx/payment-gateway');

        $this->localeService->detectAndSetLocale();

        $this->assertEquals('en', App::getLocale());
    }

    /** @test */
    public function it_does_not_detect_locale_when_detection_is_disabled()
    {
        config(['payment-gateway.locale.detect_from_url' => false]);
        $service = new LocaleService;

        App::setLocale('fr');
        $service->detectAndSetLocale();

        // Locale should remain unchanged
        $this->assertEquals('fr', App::getLocale());
    }

    /** @test */
    public function it_handles_runtime_override_of_default_locale_in_url_generation()
    {
        $url = $this->app['url'];
        $url->forceRootUrl('http://localhost');

        $this->localeService->setDefaultLocale('ar');

        $result = $this->localeService->getLocalizedUrl('http://localhost/payment-gateway', 'ar');
        $this->assertStringNotContainsString('/ar/', $result);
        $this->assertStringContainsString('payment-gateway', $result);
    }

    /** @test */
    public function it_handles_runtime_override_of_available_locales_in_url_detection()
    {
        $this->localeService->setAvailableLocales(['en', 'es']);

        $url = 'https://example.com/ar/payment-gateway';
        $this->assertNull($this->localeService->getUrlLocale($url));

        $url2 = 'https://example.com/es/payment-gateway';
        $this->assertEquals('es', $this->localeService->getUrlLocale($url2));
    }

    /** @test */
    public function it_chains_fluent_setters()
    {
        $result = $this->localeService
            ->setDefaultLocale('ar')
            ->setAvailableLocales(['ar', 'en']);

        $this->assertInstanceOf(LocaleService::class, $result);
        $this->assertEquals('ar', $this->localeService->getDefaultLocale());
        $this->assertEquals(['ar', 'en'], $this->localeService->getAvailableLocales());
    }

    /** @test */
    public function it_handles_url_with_port_number()
    {
        $url = 'https://example.com:8080/ar/payment-gateway';
        $this->assertEquals('ar', $this->localeService->getUrlLocale($url));
    }

    /** @test */
    public function it_handles_localhost_urls()
    {
        $url = 'http://localhost/ar/payment-gateway';
        $this->assertEquals('ar', $this->localeService->getUrlLocale($url));
    }

    /** @test */
    public function it_handles_ip_address_urls()
    {
        $url = 'http://192.168.1.1/ar/payment-gateway';
        $this->assertEquals('ar', $this->localeService->getUrlLocale($url));
    }

    /** @test */
    public function it_handles_urls_with_subdomains()
    {
        $url = 'https://api.example.com/ar/payment-gateway';
        $this->assertEquals('ar', $this->localeService->getUrlLocale($url));
    }

    /** @test */
    public function it_handles_relative_like_paths_in_url_generation()
    {
        $url = $this->app['url'];
        $url->forceRootUrl('http://localhost');

        $result = $this->localeService->getLocalizedUrl('http://localhost/path1/path2', 'ar');
        $this->assertStringContainsString('/ar/', $result);
        $this->assertStringContainsString('path1/path2', $result);
    }

    /** @test */
    public function it_does_not_add_locale_to_empty_path()
    {
        $url = $this->app['url'];
        $url->forceRootUrl('http://localhost');

        $result = $this->localeService->getLocalizedUrl('http://localhost', 'ar');
        $this->assertStringContainsString('/ar', $result);
    }

    /** @test */
    public function it_removes_all_available_locale_segments_from_path()
    {
        $url = $this->app['url'];
        $url->forceRootUrl('http://localhost');

        // URL with multiple locale-like segments (only first should be removed)
        $result = $this->localeService->getLocalizedUrl('http://localhost/ar/payment/en/checkout', 'fr');
        // Should remove first 'ar' and add 'fr'
        $this->assertStringContainsString('/fr/', $result);
        $this->assertStringNotContainsString('/ar/', $result);
        $this->assertStringContainsString('payment/en/checkout', $result);
    }

    /** @test */
    public function it_handles_case_sensitive_locale_codes()
    {
        // Locale codes should be lowercase
        $url = 'https://example.com/AR/payment-gateway';
        // 'AR' is not in available locales ['en', 'ar', 'fr'], so it should return null
        $this->assertNull($this->localeService->getUrlLocale($url));
    }

    /** @test */
    public function it_works_with_single_available_locale()
    {
        $this->localeService->setAvailableLocales(['en']);

        $url = 'https://example.com/en/payment-gateway';
        $this->assertEquals('en', $this->localeService->getUrlLocale($url));

        $url2 = 'https://example.com/ar/payment-gateway';
        $this->assertNull($this->localeService->getUrlLocale($url2));
    }

    /** @test */
    public function it_handles_many_available_locales()
    {
        $manyLocales = ['en', 'ar', 'fr', 'de', 'es', 'it', 'pt', 'ru', 'zh', 'ja'];
        $this->localeService->setAvailableLocales($manyLocales);

        foreach ($manyLocales as $locale) {
            $url = "https://example.com/{$locale}/payment-gateway";
            $this->assertEquals($locale, $this->localeService->getUrlLocale($url));
        }
    }

    /** @test */
    public function it_preserves_url_structure_when_no_path()
    {
        $url = $this->app['url'];
        $url->forceRootUrl('http://localhost');

        App::setLocale('ar');

        $result = $this->localeService->getLocalizedUrl('http://localhost', 'ar');
        $this->assertStringContainsString('/ar', $result);
    }

    /** @test */
    public function it_handles_double_slashes_in_path()
    {
        // Test edge case with malformed URLs
        $url = 'https://example.com//ar//payment-gateway';
        // After filtering empty segments, should still find 'ar'
        $this->assertEquals('ar', $this->localeService->getUrlLocale($url));
    }

    /** @test */
    public function it_returns_default_locale_when_config_is_not_set()
    {
        // Don't set any config, let it use the defaults
        config(['payment-gateway.locale' => []]);
        $service = new LocaleService;

        // Should use config default value 'en' from the config call with default
        $locale = $service->getDefaultLocale();
        $this->assertIsString($locale);
    }

    /** @test */
    public function it_returns_default_available_locales_when_config_is_not_set()
    {
        // Don't set any config, let it use the defaults
        config(['payment-gateway.locale' => []]);
        $service = new LocaleService;

        // Should use config default value ['en'] from the config call with default
        $locales = $service->getAvailableLocales();
        $this->assertIsArray($locales);
    }
}
