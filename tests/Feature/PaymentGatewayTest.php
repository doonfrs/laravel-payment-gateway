<?php

namespace Trinavo\PaymentGateway\Tests\Feature;

use Orchestra\Testbench\TestCase;
use Trinavo\PaymentGateway\Facades\PaymentGateway;
use Trinavo\PaymentGateway\Models\PaymentMethod;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Providers\PaymentGatewayServiceProvider;

class PaymentGatewayTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [PaymentGatewayServiceProvider::class];
    }

    protected function getPackageAliases($app)
    {
        return [
            'PaymentGateway' => \Trinavo\PaymentGateway\Facades\PaymentGateway::class,
        ];
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    public function test_can_create_payment_order()
    {
        $paymentOrder = PaymentGateway::createPaymentOrder([
            'amount' => 100.00,
            'currency' => 'USD',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'description' => 'Test payment',
        ]);

        $this->assertInstanceOf(PaymentOrder::class, $paymentOrder);
        $this->assertEquals(100.00, $paymentOrder->amount);
        $this->assertEquals('USD', $paymentOrder->currency);
        $this->assertEquals('John Doe', $paymentOrder->customer_name);
        $this->assertEquals('pending', $paymentOrder->status);
        $this->assertNotEmpty($paymentOrder->order_code);
    }

    public function test_can_get_payment_url()
    {
        $paymentOrder = PaymentGateway::createPaymentOrder([
            'amount' => 50.00,
            'currency' => 'USD',
        ]);

        $paymentUrl = PaymentGateway::getPaymentUrl($paymentOrder);

        $this->assertStringContainsString('/payment-gateway/checkout/', $paymentUrl);
        $this->assertStringContainsString($paymentOrder->order_code, $paymentUrl);
    }

    public function test_can_register_payment_method()
    {
        $paymentMethod = PaymentGateway::registerPaymentMethod([
            'name' => 'test_method',
            'plugin_class' => \Trinavo\PaymentGateway\Plugins\DummyPaymentPlugin::class,
            'display_name' => 'Test Payment Method',
            'enabled' => true,
        ]);

        $this->assertInstanceOf(PaymentMethod::class, $paymentMethod);
        $this->assertEquals('test_method', $paymentMethod->name);
        $this->assertTrue($paymentMethod->enabled);
    }

    public function test_payment_order_status_methods()
    {
        $paymentOrder = PaymentGateway::createPaymentOrder([
            'amount' => 25.00,
            'currency' => 'USD',
        ]);

        $this->assertTrue($paymentOrder->isPending());
        $this->assertFalse($paymentOrder->isCompleted());

        $paymentOrder->markAsCompleted(['transaction_id' => 'test_123']);

        $this->assertTrue($paymentOrder->isCompleted());
        $this->assertFalse($paymentOrder->isPending());
        $this->assertNotNull($paymentOrder->paid_at);
        $this->assertEquals('test_123', $paymentOrder->payment_data['transaction_id']);
    }
}
