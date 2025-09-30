<?php

namespace Trinavo\PaymentGateway\Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Orchestra\Testbench\TestCase;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Providers\PaymentGatewayServiceProvider;
use Trinavo\PaymentGateway\Services\LocaleService;
use Trinavo\PaymentGateway\Services\PaymentGatewayService;

class PaymentGatewayServiceLocaleTest extends TestCase
{
    use RefreshDatabase;

    protected PaymentGatewayService $service;

    protected function getPackageProviders($app)
    {
        return [
            PaymentGatewayServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PaymentGatewayService;
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('payment-gateway.locale.default_locale', 'en');
        $app['config']->set('payment-gateway.locale.available_locales', ['en', 'ar', 'fr']);
        $app['config']->set('payment-gateway.locale.detect_from_url', true);
    }

    /** @test */
    public function it_can_set_default_locale()
    {
        $this->service->setDefaultLocale('ar');

        $localeService = app(LocaleService::class);
        $this->assertEquals('ar', $localeService->getDefaultLocale());
    }

    /** @test */
    public function it_returns_fluent_interface_when_setting_default_locale()
    {
        $result = $this->service->setDefaultLocale('ar');
        $this->assertInstanceOf(PaymentGatewayService::class, $result);
    }

    /** @test */
    public function it_can_set_available_locales()
    {
        $locales = ['en', 'es', 'de'];
        $this->service->setAvailableLocales($locales);

        $localeService = app(LocaleService::class);
        $this->assertEquals($locales, $localeService->getAvailableLocales());
    }

    /** @test */
    public function it_returns_fluent_interface_when_setting_available_locales()
    {
        $result = $this->service->setAvailableLocales(['en', 'ar']);
        $this->assertInstanceOf(PaymentGatewayService::class, $result);
    }

    /** @test */
    public function it_can_chain_locale_setters()
    {
        $result = $this->service
            ->setDefaultLocale('ar')
            ->setAvailableLocales(['ar', 'en', 'fr']);

        $this->assertInstanceOf(PaymentGatewayService::class, $result);

        $localeService = app(LocaleService::class);
        $this->assertEquals('ar', $localeService->getDefaultLocale());
        $this->assertEquals(['ar', 'en', 'fr'], $localeService->getAvailableLocales());
    }

    /** @test */
    public function it_delegates_to_locale_service_when_setting_default_locale()
    {
        $localeService = $this->mock(LocaleService::class);
        $localeService->shouldReceive('setDefaultLocale')
            ->once()
            ->with('ar')
            ->andReturnSelf();

        $this->app->instance(LocaleService::class, $localeService);

        $service = new PaymentGatewayService;
        $service->setDefaultLocale('ar');
    }

    /** @test */
    public function it_delegates_to_locale_service_when_setting_available_locales()
    {
        $localeService = $this->mock(LocaleService::class);
        $localeService->shouldReceive('setAvailableLocales')
            ->once()
            ->with(['en', 'ar'])
            ->andReturnSelf();

        $this->app->instance(LocaleService::class, $localeService);

        $service = new PaymentGatewayService;
        $service->setAvailableLocales(['en', 'ar']);
    }

    /** @test */
    public function get_payment_url_returns_non_localized_url_for_default_locale()
    {
        // Set up routes
        Route::get('payment-gateway/checkout/{order}', function () {})->name('payment-gateway.checkout');

        App::setLocale('en'); // Default locale

        $paymentOrder = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        $url = $this->service->getPaymentUrl($paymentOrder);

        $this->assertStringNotContainsString('/en/', $url);
        $this->assertStringContainsString('payment-gateway/checkout', $url);
        $this->assertStringContainsString($paymentOrder->order_code, $url);
    }

    /** @test */
    public function get_payment_url_returns_localized_url_for_non_default_locale()
    {
        // Set up routes
        Route::get('{locale}/payment-gateway/checkout/{order}', function () {})->name('localized.payment-gateway.checkout');

        App::setLocale('ar'); // Non-default locale

        $paymentOrder = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        $url = $this->service->getPaymentUrl($paymentOrder);

        $this->assertStringContainsString('/ar/', $url);
        $this->assertStringContainsString('payment-gateway/checkout', $url);
        $this->assertStringContainsString($paymentOrder->order_code, $url);
    }

    /** @test */
    public function get_payment_url_returns_non_localized_url_for_unavailable_locale()
    {
        // Set up routes
        Route::get('payment-gateway/checkout/{order}', function () {})->name('payment-gateway.checkout');

        // Set locale to something not in available_locales
        App::setLocale('de'); // Not in ['en', 'ar', 'fr']

        $paymentOrder = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        $url = $this->service->getPaymentUrl($paymentOrder);

        $this->assertStringNotContainsString('/de/', $url);
        $this->assertStringContainsString('payment-gateway/checkout', $url);
    }

    /** @test */
    public function get_payment_url_handles_french_locale()
    {
        // Set up routes
        Route::get('{locale}/payment-gateway/checkout/{order}', function () {})->name('localized.payment-gateway.checkout');

        App::setLocale('fr');

        $paymentOrder = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        $url = $this->service->getPaymentUrl($paymentOrder);

        $this->assertStringContainsString('/fr/', $url);
        $this->assertStringContainsString($paymentOrder->order_code, $url);
    }

    /** @test */
    public function get_payment_url_respects_runtime_default_locale_change()
    {
        // Set up routes
        Route::get('payment-gateway/checkout/{order}', function () {})->name('payment-gateway.checkout');
        Route::get('{locale}/payment-gateway/checkout/{order}', function () {})->name('localized.payment-gateway.checkout');

        // Change default locale to Arabic at runtime
        $this->service->setDefaultLocale('ar');
        App::setLocale('ar');

        $paymentOrder = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        $url = $this->service->getPaymentUrl($paymentOrder);

        // Since 'ar' is now the default, should not have locale prefix
        $this->assertStringNotContainsString('/ar/', $url);
    }

    /** @test */
    public function get_payment_url_respects_runtime_available_locales_change()
    {
        // Set up routes
        Route::get('payment-gateway/checkout/{order}', function () {})->name('payment-gateway.checkout');

        // Change available locales to exclude Arabic
        $this->service->setAvailableLocales(['en', 'fr']);
        App::setLocale('ar');

        $paymentOrder = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        $url = $this->service->getPaymentUrl($paymentOrder);

        // Since 'ar' is not in available locales, should use non-localized route
        $this->assertStringNotContainsString('/ar/', $url);
    }

    /** @test */
    public function get_payment_url_generates_different_urls_for_different_locales()
    {
        // Set up routes
        Route::get('payment-gateway/checkout/{order}', function () {})->name('payment-gateway.checkout');
        Route::get('{locale}/payment-gateway/checkout/{order}', function () {})->name('localized.payment-gateway.checkout');

        $paymentOrder = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        // English URL
        App::setLocale('en');
        $enUrl = $this->service->getPaymentUrl($paymentOrder);

        // Arabic URL
        App::setLocale('ar');
        $arUrl = $this->service->getPaymentUrl($paymentOrder);

        // French URL
        App::setLocale('fr');
        $frUrl = $this->service->getPaymentUrl($paymentOrder);

        // All should be different
        $this->assertNotEquals($enUrl, $arUrl);
        $this->assertNotEquals($enUrl, $frUrl);
        $this->assertNotEquals($arUrl, $frUrl);

        // Verify locale prefixes
        $this->assertStringNotContainsString('/en/', $enUrl);
        $this->assertStringContainsString('/ar/', $arUrl);
        $this->assertStringContainsString('/fr/', $frUrl);
    }

    /** @test */
    public function get_payment_url_uses_correct_route_name_for_localized_urls()
    {
        // Set up routes
        Route::get('{locale}/payment-gateway/checkout/{order}', function () {
            return 'localized';
        })->name('localized.payment-gateway.checkout');

        Route::get('payment-gateway/checkout/{order}', function () {
            return 'non-localized';
        })->name('payment-gateway.checkout');

        App::setLocale('ar');

        $paymentOrder = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        $url = $this->service->getPaymentUrl($paymentOrder);

        // Should use localized route
        $this->assertTrue(URL::isValidUrl($url));
    }

    /** @test */
    public function get_payment_url_includes_order_code_in_url()
    {
        Route::get('payment-gateway/checkout/{order}', function () {})->name('payment-gateway.checkout');

        $paymentOrder = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        $url = $this->service->getPaymentUrl($paymentOrder);

        $this->assertStringContainsString($paymentOrder->order_code, $url);
    }

    /** @test */
    public function locale_methods_work_with_service_instance_from_container()
    {
        $service = app(PaymentGatewayService::class);

        $result = $service->setDefaultLocale('ar')->setAvailableLocales(['ar', 'en']);

        $this->assertInstanceOf(PaymentGatewayService::class, $result);

        $localeService = app(LocaleService::class);
        $this->assertEquals('ar', $localeService->getDefaultLocale());
        $this->assertEquals(['ar', 'en'], $localeService->getAvailableLocales());
    }

    /** @test */
    public function get_payment_url_works_with_multiple_payment_orders()
    {
        Route::get('payment-gateway/checkout/{order}', function () {})->name('payment-gateway.checkout');
        Route::get('{locale}/payment-gateway/checkout/{order}', function () {})->name('localized.payment-gateway.checkout');

        $order1 = PaymentOrder::create(['amount' => 100, 'currency' => 'USD', 'status' => 'pending']);
        $order2 = PaymentOrder::create(['amount' => 200, 'currency' => 'USD', 'status' => 'pending']);
        $order3 = PaymentOrder::create(['amount' => 300, 'currency' => 'USD', 'status' => 'pending']);

        App::setLocale('ar');

        $url1 = $this->service->getPaymentUrl($order1);
        $url2 = $this->service->getPaymentUrl($order2);
        $url3 = $this->service->getPaymentUrl($order3);

        $this->assertStringContainsString($order1->order_code, $url1);
        $this->assertStringContainsString($order2->order_code, $url2);
        $this->assertStringContainsString($order3->order_code, $url3);

        $this->assertNotEquals($url1, $url2);
        $this->assertNotEquals($url2, $url3);
    }

    /** @test */
    public function setting_locales_affects_all_subsequent_get_payment_url_calls()
    {
        Route::get('payment-gateway/checkout/{order}', function () {})->name('payment-gateway.checkout');
        Route::get('{locale}/payment-gateway/checkout/{order}', function () {})->name('localized.payment-gateway.checkout');

        $paymentOrder = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        // Initially 'ar' is available
        App::setLocale('ar');
        $url1 = $this->service->getPaymentUrl($paymentOrder);
        $this->assertStringContainsString('/ar/', $url1);

        // Remove 'ar' from available locales
        $this->service->setAvailableLocales(['en', 'fr']);

        // Now 'ar' should not be in URL
        $url2 = $this->service->getPaymentUrl($paymentOrder);
        $this->assertStringNotContainsString('/ar/', $url2);
    }

    /** @test */
    public function get_payment_url_is_consistent_for_same_order_and_locale()
    {
        Route::get('payment-gateway/checkout/{order}', function () {})->name('payment-gateway.checkout');
        Route::get('{locale}/payment-gateway/checkout/{order}', function () {})->name('localized.payment-gateway.checkout');

        $paymentOrder = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        App::setLocale('ar');

        $url1 = $this->service->getPaymentUrl($paymentOrder);
        $url2 = $this->service->getPaymentUrl($paymentOrder);
        $url3 = $this->service->getPaymentUrl($paymentOrder);

        $this->assertEquals($url1, $url2);
        $this->assertEquals($url2, $url3);
    }
}
