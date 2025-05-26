<?php

namespace Trinavo\PaymentGateway\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Trinavo\PaymentGateway\Models\PaymentMethod;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Providers\PaymentGatewayServiceProvider;
use Trinavo\PaymentGateway\Services\PaymentGatewayService;

class IgnoredPluginsTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [PaymentGatewayServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Run migrations
        $this->artisan('migrate', ['--database' => 'testing']);

        // Create test payment methods
        PaymentMethod::create([
            'name' => 'stripe',
            'plugin_class' => 'App\PaymentPlugins\StripePaymentPlugin',
            'display_name' => 'Credit Card (Stripe)',
            'enabled' => true,
            'sort_order' => 1,
        ]);

        PaymentMethod::create([
            'name' => 'paypal',
            'plugin_class' => 'App\PaymentPlugins\PayPalPaymentPlugin',
            'display_name' => 'PayPal',
            'enabled' => true,
            'sort_order' => 2,
        ]);

        PaymentMethod::create([
            'name' => 'dummy',
            'plugin_class' => 'Trinavo\PaymentGateway\Plugins\Dummy\DummyPaymentPlugin',
            'display_name' => 'Dummy Payment',
            'enabled' => true,
            'sort_order' => 3,
        ]);
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    public function test_payment_order_can_have_ignored_plugins()
    {
        $paymentOrder = PaymentOrder::create([
            'amount' => 100.00,
            'currency' => 'USD',
            'customer_name' => 'John Doe',
            'ignored_plugins' => ['stripe', 'paypal'],
        ]);

        $this->assertEquals(['stripe', 'paypal'], $paymentOrder->getIgnoredPlugins());
        $this->assertTrue($paymentOrder->isPluginIgnored('stripe'));
        $this->assertTrue($paymentOrder->isPluginIgnored('paypal'));
        $this->assertFalse($paymentOrder->isPluginIgnored('dummy'));
    }

    public function test_payment_order_without_ignored_plugins()
    {
        $paymentOrder = PaymentOrder::create([
            'amount' => 100.00,
            'currency' => 'USD',
            'customer_name' => 'John Doe',
        ]);

        $this->assertEquals([], $paymentOrder->getIgnoredPlugins());
        $this->assertFalse($paymentOrder->isPluginIgnored('stripe'));
        $this->assertFalse($paymentOrder->isPluginIgnored('paypal'));
        $this->assertFalse($paymentOrder->isPluginIgnored('dummy'));
    }

    public function test_set_ignored_plugins_after_creation()
    {
        $paymentOrder = PaymentOrder::create([
            'amount' => 100.00,
            'currency' => 'USD',
            'customer_name' => 'John Doe',
        ]);

        $paymentOrder->setIgnoredPlugins(['stripe']);

        $this->assertEquals(['stripe'], $paymentOrder->fresh()->getIgnoredPlugins());
        $this->assertTrue($paymentOrder->fresh()->isPluginIgnored('stripe'));
    }

    public function test_service_filters_ignored_plugins()
    {
        $service = new PaymentGatewayService;

        // Create order with ignored plugins
        $paymentOrder = PaymentOrder::create([
            'amount' => 100.00,
            'currency' => 'USD',
            'customer_name' => 'John Doe',
            'ignored_plugins' => ['stripe', 'paypal'],
        ]);

        // Get all available methods (should return all 3)
        $allMethods = $service->getAvailablePaymentMethods();
        $this->assertCount(3, $allMethods);

        // Get methods for this specific order (should filter out ignored ones)
        $filteredMethods = $service->getAvailablePaymentMethodsForOrder($paymentOrder);
        $this->assertCount(1, $filteredMethods);
        $this->assertEquals('dummy', $filteredMethods->first()->name);
    }

    public function test_service_filters_by_plugin_class()
    {
        $service = new PaymentGatewayService;

        // Create order ignoring by plugin class
        $paymentOrder = PaymentOrder::create([
            'amount' => 100.00,
            'currency' => 'USD',
            'customer_name' => 'John Doe',
            'ignored_plugins' => ['App\PaymentPlugins\StripePaymentPlugin'],
        ]);

        $filteredMethods = $service->getAvailablePaymentMethodsForOrder($paymentOrder);
        $this->assertCount(2, $filteredMethods);

        // Should not contain stripe
        $methodNames = $filteredMethods->pluck('name')->toArray();
        $this->assertNotContains('stripe', $methodNames);
        $this->assertContains('paypal', $methodNames);
        $this->assertContains('dummy', $methodNames);
    }

    public function test_service_create_payment_order_with_ignored_plugins()
    {
        $service = new PaymentGatewayService;

        $paymentOrder = $service->createPaymentOrder(
            amount: 100.00,
            currency: 'USD',
            customerName: 'John Doe',
            customerEmail: 'john@example.com',
            ignoredPlugins: ['stripe', 'paypal']
        );

        $this->assertEquals(['stripe', 'paypal'], $paymentOrder->getIgnoredPlugins());
        $this->assertTrue($paymentOrder->isPluginIgnored('stripe'));
        $this->assertTrue($paymentOrder->isPluginIgnored('paypal'));
    }
}
