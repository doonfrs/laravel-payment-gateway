<?php

namespace Trinavo\PaymentGateway\Tests\Feature;

use Orchestra\Testbench\TestCase;
use Trinavo\PaymentGateway\Models\PaymentMethod;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Providers\PaymentGatewayServiceProvider;
use Trinavo\PaymentGateway\Services\PaymentGatewayService;

class PluginConfigurationTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [PaymentGatewayServiceProvider::class];
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    public function test_plugins_are_loaded_from_configuration()
    {
        // Set up a test plugin in config
        config(['payment-gateway.plugins.test' => \Trinavo\PaymentGateway\Plugins\Dummy\DummyPaymentPlugin::class]);

        // Create a payment method using the configured plugin
        $paymentMethod = PaymentMethod::create([
            'name' => 'test',
            'plugin_class' => \Trinavo\PaymentGateway\Plugins\Dummy\DummyPaymentPlugin::class,
            'display_name' => 'Test Payment Method',
            'enabled' => true,
            'sort_order' => 1,
        ]);

        // Create a payment order
        $paymentOrder = PaymentOrder::create([
            'amount' => 100.00,
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        // Test that the callback route can resolve the plugin from config
        $response = $this->post(route('payment-gateway.callback', ['plugin' => 'test']), [
            'action' => 'success',
            'order_code' => $paymentOrder->order_code,
        ]);

        // Should not throw an "Unknown plugin" exception
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    public function test_unknown_plugin_throws_exception()
    {
        // Test that an unknown plugin throws an exception
        $response = $this->post(route('payment-gateway.callback', ['plugin' => 'nonexistent']), [
            'action' => 'success',
            'order_code' => 'test',
        ]);

        $this->assertEquals(500, $response->getStatusCode());
    }

    public function test_default_currency_is_loaded_from_config()
    {
        // Set a custom default currency
        config(['payment-gateway.default_currency' => 'EUR']);

        // Create a payment order without specifying currency
        $paymentOrder = PaymentOrder::create([
            'amount' => 100.00,
            'status' => 'pending',
        ]);

        // Should use the configured default currency
        $this->assertEquals('EUR', $paymentOrder->currency);
    }

    public function test_payment_gateway_service_uses_config_for_currency()
    {
        // Set a custom default currency
        config(['payment-gateway.default_currency' => 'GBP']);

        $service = app(PaymentGatewayService::class);

        // Create payment order without currency
        $paymentOrder = $service->createPaymentOrder(50.00);

        // Should use the configured default currency
        $this->assertEquals('GBP', $paymentOrder->currency);
    }

    public function test_multiple_plugins_can_be_configured()
    {
        // Configure multiple plugins
        config([
            'payment-gateway.plugins' => [
                'dummy' => \Trinavo\PaymentGateway\Plugins\Dummy\DummyPaymentPlugin::class,
                'test1' => \Trinavo\PaymentGateway\Plugins\Dummy\DummyPaymentPlugin::class,
                'test2' => \Trinavo\PaymentGateway\Plugins\Dummy\DummyPaymentPlugin::class,
            ],
        ]);

        // Create payment methods for each plugin
        PaymentMethod::create([
            'name' => 'dummy',
            'plugin_class' => \Trinavo\PaymentGateway\Plugins\Dummy\DummyPaymentPlugin::class,
            'display_name' => 'Dummy Payment',
            'enabled' => true,
            'sort_order' => 1,
        ]);

        PaymentMethod::create([
            'name' => 'test1',
            'plugin_class' => \Trinavo\PaymentGateway\Plugins\Dummy\DummyPaymentPlugin::class,
            'display_name' => 'Test Payment 1',
            'enabled' => true,
            'sort_order' => 2,
        ]);

        PaymentMethod::create([
            'name' => 'test2',
            'plugin_class' => \Trinavo\PaymentGateway\Plugins\Dummy\DummyPaymentPlugin::class,
            'display_name' => 'Test Payment 2',
            'enabled' => true,
            'sort_order' => 3,
        ]);

        $service = app(PaymentGatewayService::class);
        $availableMethods = $service->getAvailablePaymentMethods();

        // Should return all enabled payment methods
        $this->assertCount(3, $availableMethods);
        $this->assertEquals('Dummy Payment', $availableMethods[0]->display_name);
        $this->assertEquals('Test Payment 1', $availableMethods[1]->display_name);
        $this->assertEquals('Test Payment 2', $availableMethods[2]->display_name);
    }
}
