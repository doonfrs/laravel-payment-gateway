<?php

namespace Trinavo\PaymentGateway\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Providers\PaymentGatewayServiceProvider;

class LocalizedRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app)
    {
        return [
            PaymentGatewayServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
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
        $app['config']->set('payment-gateway.routes.prefix', 'payment-gateway');
        $app['config']->set('payment-gateway.routes.middleware', ['web']);
    }

    /** @test */
    public function non_localized_checkout_route_exists()
    {
        $order = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        $response = $this->get("/payment-gateway/checkout/{$order->order_code}");

        $this->assertNotEquals(404, $response->status());
    }

    /** @test */
    public function localized_checkout_route_can_generate_url_for_arabic()
    {
        $order = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        $url = route('localized.payment-gateway.checkout', ['locale' => 'ar', 'order' => $order->order_code]);

        $this->assertStringContainsString('/ar/', $url);
        $this->assertStringContainsString('payment-gateway/checkout', $url);
    }

    /** @test */
    public function localized_checkout_route_can_generate_url_for_french()
    {
        $order = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        $url = route('localized.payment-gateway.checkout', ['locale' => 'fr', 'order' => $order->order_code]);

        $this->assertStringContainsString('/fr/', $url);
        $this->assertStringContainsString('payment-gateway/checkout', $url);
    }

    /** @test */
    public function localized_route_does_not_exist_for_invalid_locale()
    {
        $order = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        $response = $this->get("/xx/payment-gateway/checkout/{$order->order_code}");

        $this->assertEquals(404, $response->status());
    }

    /** @test */
    public function localized_success_route_generates_correct_url()
    {
        $order = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'completed',
        ]);

        $url = route('localized.payment-gateway.success', ['locale' => 'ar', 'order' => $order->order_code]);

        $this->assertStringContainsString('/ar/', $url);
        $this->assertStringContainsString('payment-gateway/success', $url);
    }

    /** @test */
    public function localized_failure_route_generates_correct_url()
    {
        $order = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'failed',
        ]);

        $url = route('localized.payment-gateway.failure', ['locale' => 'ar', 'order' => $order->order_code]);

        $this->assertStringContainsString('/ar/', $url);
        $this->assertStringContainsString('payment-gateway/failure', $url);
    }

    /** @test */
    public function localized_status_route_generates_correct_url()
    {
        $order = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        $url = route('localized.payment-gateway.status', ['locale' => 'ar', 'order' => $order->order_code]);

        $this->assertStringContainsString('/ar/', $url);
        $this->assertStringContainsString('payment-gateway/status', $url);
    }

    /** @test */
    public function localized_process_route_accepts_post_requests()
    {
        $order = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        $response = $this->post("/ar/payment-gateway/checkout/{$order->order_code}/process", [
            'payment_method_id' => 1,
        ]);

        // Should not be 404 (route exists)
        // Might be validation error or redirect, but route exists
        $this->assertNotEquals(404, $response->status());
    }

    /** @test */
    public function localized_callback_route_accepts_get_requests()
    {
        $response = $this->get('/ar/payment-gateway/callback/dummy-payment');

        // Should not be 404 (route exists)
        $this->assertNotEquals(404, $response->status());
    }

    /** @test */
    public function localized_callback_route_accepts_post_requests()
    {
        $response = $this->post('/ar/payment-gateway/callback/dummy-payment', [
            'order_code' => 'test123',
        ]);

        // Should not be 404 (route exists)
        $this->assertNotEquals(404, $response->status());
    }

    /** @test */
    public function localized_dummy_action_route_generates_url_for_success()
    {
        $order = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        $url = route('localized.payment-gateway.dummy-action', [
            'locale' => 'ar',
            'order' => $order->order_code,
            'action' => 'success',
        ]);

        $this->assertStringContainsString('/ar/', $url);
        $this->assertStringContainsString('dummy', $url);
    }

    /** @test */
    public function localized_dummy_action_route_generates_url_for_failure()
    {
        $order = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        $url = route('localized.payment-gateway.dummy-action', [
            'locale' => 'ar',
            'order' => $order->order_code,
            'action' => 'failure',
        ]);

        $this->assertStringContainsString('/ar/', $url);
        $this->assertStringContainsString('dummy', $url);
    }

    /** @test */
    public function localized_dummy_action_route_generates_url_for_callback()
    {
        $order = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        $url = route('localized.payment-gateway.dummy-action', [
            'locale' => 'ar',
            'order' => $order->order_code,
            'action' => 'callback',
        ]);

        $this->assertStringContainsString('/ar/', $url);
        $this->assertStringContainsString('dummy', $url);
    }

    /** @test */
    public function localized_dummy_action_route_does_not_exist_for_invalid_action()
    {
        $order = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        $response = $this->get("/ar/payment-gateway/dummy/{$order->order_code}/invalid");

        $this->assertEquals(404, $response->status());
    }

    /** @test */
    public function localized_offline_confirm_route_generates_correct_url()
    {
        $order = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        $url = route('localized.payment-gateway.offline-confirm', [
            'locale' => 'ar',
            'order' => $order->order_code,
        ]);

        $this->assertStringContainsString('/ar/', $url);
        $this->assertStringContainsString('offline', $url);
    }

    /** @test */
    public function locale_parameter_is_optional_in_localized_routes()
    {
        $order = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        // Without locale prefix
        $response = $this->get("/payment-gateway/checkout/{$order->order_code}");
        $this->assertNotEquals(404, $response->status());
    }

    /** @test */
    public function both_localized_and_non_localized_routes_can_generate_urls()
    {
        $order = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        // Non-localized route
        $url1 = route('payment-gateway.checkout', ['order' => $order->order_code]);
        $this->assertStringNotContainsString('/ar/', $url1);

        // Localized route
        $url2 = route('localized.payment-gateway.checkout', ['locale' => 'ar', 'order' => $order->order_code]);
        $this->assertStringContainsString('/ar/', $url2);

        // Both should generate different URLs
        $this->assertNotEquals($url1, $url2);
    }

    /** @test */
    public function localized_routes_generate_urls_for_all_available_locales()
    {
        $order = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        $availableLocales = config('payment-gateway.locale.available_locales');

        foreach ($availableLocales as $locale) {
            $url = route('localized.payment-gateway.checkout', [
                'locale' => $locale,
                'order' => $order->order_code,
            ]);

            $this->assertStringContainsString("/{$locale}/", $url, "URL generation failed for locale: {$locale}");
            $this->assertStringContainsString('payment-gateway/checkout', $url);
        }
    }

    /** @test */
    public function localized_route_names_are_prefixed_with_localized()
    {
        $this->assertTrue(
            \Illuminate\Support\Facades\Route::has('localized.payment-gateway.checkout'),
            'Localized checkout route does not exist'
        );

        $this->assertTrue(
            \Illuminate\Support\Facades\Route::has('localized.payment-gateway.success'),
            'Localized success route does not exist'
        );

        $this->assertTrue(
            \Illuminate\Support\Facades\Route::has('localized.payment-gateway.failure'),
            'Localized failure route does not exist'
        );

        $this->assertTrue(
            \Illuminate\Support\Facades\Route::has('localized.payment-gateway.status'),
            'Localized status route does not exist'
        );

        $this->assertTrue(
            \Illuminate\Support\Facades\Route::has('localized.payment-gateway.process'),
            'Localized process route does not exist'
        );

        $this->assertTrue(
            \Illuminate\Support\Facades\Route::has('localized.payment-gateway.callback'),
            'Localized callback route does not exist'
        );

        $this->assertTrue(
            \Illuminate\Support\Facades\Route::has('localized.payment-gateway.dummy-action'),
            'Localized dummy-action route does not exist'
        );

        $this->assertTrue(
            \Illuminate\Support\Facades\Route::has('localized.payment-gateway.offline-confirm'),
            'Localized offline-confirm route does not exist'
        );
    }

    /** @test */
    public function non_localized_route_names_exist()
    {
        $this->assertTrue(
            \Illuminate\Support\Facades\Route::has('payment-gateway.checkout'),
            'Non-localized checkout route does not exist'
        );

        $this->assertTrue(
            \Illuminate\Support\Facades\Route::has('payment-gateway.success'),
            'Non-localized success route does not exist'
        );

        $this->assertTrue(
            \Illuminate\Support\Facades\Route::has('payment-gateway.failure'),
            'Non-localized failure route does not exist'
        );
    }

    /** @test */
    public function localized_route_accepts_two_letter_locale_code()
    {
        $order = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        // Two letter code should work
        $url = route('localized.payment-gateway.checkout', ['locale' => 'ar', 'order' => $order->order_code]);
        $this->assertStringContainsString('/ar/', $url);

        // Three letter code should not match route pattern (based on regex [a-z]{2})
        $response = $this->get("/ara/payment-gateway/checkout/{$order->order_code}");
        $this->assertEquals(404, $response->status());
    }

    /** @test */
    public function localized_route_does_not_accept_uppercase_locale()
    {
        $order = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        // Uppercase should not match (based on regex [a-z]{2})
        $response = $this->get("/AR/payment-gateway/checkout/{$order->order_code}");
        $this->assertEquals(404, $response->status());
    }

    /** @test */
    public function localized_route_does_not_accept_numeric_locale()
    {
        $order = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        // Numeric should not match (based on regex [a-z]{2})
        $response = $this->get("/12/payment-gateway/checkout/{$order->order_code}");
        $this->assertEquals(404, $response->status());
    }

    /** @test */
    public function localized_routes_include_configured_prefix()
    {
        $order = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        $prefix = config('payment-gateway.routes.prefix');

        $url = route('localized.payment-gateway.checkout', ['locale' => 'ar', 'order' => $order->order_code]);
        $this->assertStringContainsString('/ar/', $url);
        $this->assertStringContainsString($prefix, $url);
    }

    /** @test */
    public function localized_and_non_localized_routes_can_generate_urls()
    {
        $order = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        // Non-localized URL
        $nonLocalizedUrl = route('payment-gateway.checkout', ['order' => $order->order_code]);
        $this->assertIsString($nonLocalizedUrl);
        $this->assertStringContainsString('payment-gateway/checkout', $nonLocalizedUrl);

        // Localized URL
        $localizedUrl = route('localized.payment-gateway.checkout', [
            'locale' => 'ar',
            'order' => $order->order_code,
        ]);
        $this->assertIsString($localizedUrl);
        $this->assertStringContainsString('/ar/', $localizedUrl);
        $this->assertStringContainsString('payment-gateway/checkout', $localizedUrl);
    }

    /** @test */
    public function all_localized_routes_follow_same_url_pattern()
    {
        $order = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        $routeNames = [
            'localized.payment-gateway.checkout',
            'localized.payment-gateway.success',
            'localized.payment-gateway.failure',
            'localized.payment-gateway.status',
        ];

        foreach ($routeNames as $routeName) {
            $url = route($routeName, ['locale' => 'ar', 'order' => $order->order_code]);
            $this->assertStringContainsString('/ar/', $url, "URL pattern failed for route: {$routeName}");
            $this->assertStringContainsString('payment-gateway', $url);
        }
    }

    /** @test */
    public function localized_routes_generate_urls_for_multiple_locales()
    {
        $order = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        // Test multiple locales in sequence
        $url1 = route('localized.payment-gateway.checkout', ['locale' => 'ar', 'order' => $order->order_code]);
        $this->assertStringContainsString('/ar/', $url1);

        $url2 = route('localized.payment-gateway.checkout', ['locale' => 'fr', 'order' => $order->order_code]);
        $this->assertStringContainsString('/fr/', $url2);

        $url3 = route('payment-gateway.checkout', ['order' => $order->order_code]);
        $this->assertStringNotContainsString('/fr/', $url3);
        $this->assertStringNotContainsString('/ar/', $url3);
    }
}
