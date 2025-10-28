<?php

namespace Trinavo\PaymentGateway\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use Trinavo\PaymentGateway\Models\PaymentMethod;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Providers\PaymentGatewayServiceProvider;

class CheckoutAutoSelectionTest extends TestCase
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
        $this->withoutVite();
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
    public function checkout_auto_selects_when_only_one_payment_method_exists()
    {
        // Create a payment order
        $order = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        // Create only one enabled payment method
        $paymentMethod = PaymentMethod::create([
            'name' => 'dummy_payment',
            'plugin_class' => \Trinavo\PaymentGateway\Plugins\Dummy\DummyPaymentPlugin::class,
            'display_name' => 'Dummy Payment',
            'enabled' => true,
            'sort_order' => 1,
        ]);

        // Visit checkout page
        $response = $this->get("/payment-gateway/checkout/{$order->order_code}");

        // Should not see the checkout view, but instead be redirected to the payment processing
        // The response should contain the dummy payment plugin view
        $response->assertStatus(200);
        $response->assertSee('Dummy Payment'); // From the plugin view
    }

    /** @test */
    public function checkout_shows_selection_page_when_multiple_payment_methods_exist()
    {
        // Create a payment order
        $order = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        // Create multiple enabled payment methods
        PaymentMethod::create([
            'name' => 'dummy_payment',
            'plugin_class' => \Trinavo\PaymentGateway\Plugins\Dummy\DummyPaymentPlugin::class,
            'display_name' => 'Dummy Payment',
            'enabled' => true,
            'sort_order' => 1,
        ]);

        PaymentMethod::create([
            'name' => 'offline_payment',
            'plugin_class' => \Trinavo\PaymentGateway\Plugins\Offline\OfflinePaymentPlugin::class,
            'display_name' => 'Offline Payment',
            'enabled' => true,
            'sort_order' => 2,
        ]);

        // Visit checkout page
        $response = $this->get("/payment-gateway/checkout/{$order->order_code}");

        // Should see the checkout selection view
        $response->assertStatus(200);
        $response->assertSee('Dummy Payment');
        $response->assertSee('Offline Payment');
        $response->assertSee('Proceed to Payment'); // Button from checkout view
    }

    /** @test */
    public function checkout_shows_selection_page_when_no_payment_methods_exist()
    {
        // Create a payment order
        $order = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        // No payment methods created

        // Visit checkout page
        $response = $this->get("/payment-gateway/checkout/{$order->order_code}");

        // Should see the checkout view (even though there are no methods)
        $response->assertStatus(200);
    }

    /** @test */
    public function checkout_does_not_auto_select_disabled_payment_method()
    {
        // Create a payment order
        $order = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        // Create only one payment method but disabled
        $paymentMethod = PaymentMethod::create([
            'name' => 'dummy_payment',
            'plugin_class' => \Trinavo\PaymentGateway\Plugins\Dummy\DummyPaymentPlugin::class,
            'display_name' => 'Dummy Payment',
            'enabled' => false, // Disabled
            'sort_order' => 1,
        ]);

        // Visit checkout page
        $response = $this->get("/payment-gateway/checkout/{$order->order_code}");

        // Should see the checkout view with no available methods
        $response->assertStatus(200);
        // Should not auto-process since the method is disabled
    }

    /** @test */
    public function checkout_auto_selects_only_enabled_method_when_others_are_disabled()
    {
        // Create a payment order
        $order = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        // Create one enabled and one disabled payment method
        PaymentMethod::create([
            'name' => 'dummy_payment',
            'plugin_class' => \Trinavo\PaymentGateway\Plugins\Dummy\DummyPaymentPlugin::class,
            'display_name' => 'Dummy Payment',
            'enabled' => true,
            'sort_order' => 1,
        ]);

        PaymentMethod::create([
            'name' => 'offline_payment',
            'plugin_class' => \Trinavo\PaymentGateway\Plugins\Offline\OfflinePaymentPlugin::class,
            'display_name' => 'Offline Payment',
            'enabled' => false, // Disabled
            'sort_order' => 2,
        ]);

        // Visit checkout page
        $response = $this->get("/payment-gateway/checkout/{$order->order_code}");

        // Should auto-select the only enabled method
        $response->assertStatus(200);
        $response->assertSee('Dummy Payment');
    }

    /** @test */
    public function checkout_respects_ignored_plugins_when_auto_selecting()
    {
        // Create a payment order with ignored plugin
        $order = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
            'ignored_plugins' => ['dummy_payment'],
        ]);

        // Create two payment methods
        PaymentMethod::create([
            'name' => 'dummy_payment',
            'plugin_class' => \Trinavo\PaymentGateway\Plugins\Dummy\DummyPaymentPlugin::class,
            'display_name' => 'Dummy Payment',
            'enabled' => true,
            'sort_order' => 1,
        ]);

        PaymentMethod::create([
            'name' => 'offline_payment',
            'plugin_class' => \Trinavo\PaymentGateway\Plugins\Offline\OfflinePaymentPlugin::class,
            'display_name' => 'Offline Payment',
            'enabled' => true,
            'sort_order' => 2,
        ]);

        // Visit checkout page
        $response = $this->get("/payment-gateway/checkout/{$order->order_code}");

        // Should auto-select the offline payment (dummy is ignored)
        $response->assertStatus(200);
        $response->assertSee('Offline Payment');
        $response->assertDontSee('Dummy Payment');
    }

    /** @test */
    public function payment_order_remains_pending_when_auto_selected()
    {
        // Create a payment order
        $order = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        // Create only one enabled payment method
        $paymentMethod = PaymentMethod::create([
            'name' => 'dummy_payment',
            'plugin_class' => \Trinavo\PaymentGateway\Plugins\Dummy\DummyPaymentPlugin::class,
            'display_name' => 'Dummy Payment',
            'enabled' => true,
            'sort_order' => 1,
        ]);

        // Visit checkout page (should auto-select)
        $response = $this->get("/payment-gateway/checkout/{$order->order_code}");

        // Refresh the order
        $order->refresh();

        // Should remain pending (not marked as processing until payment is actually submitted)
        $this->assertEquals('pending', $order->status);
        $this->assertEquals($paymentMethod->id, $order->payment_method_id);
    }
}
