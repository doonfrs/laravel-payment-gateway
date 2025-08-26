<?php

namespace Trinavo\PaymentGateway\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Trinavo\PaymentGateway\Models\PaymentMethod;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Plugins\Tabby\TabbyPaymentPlugin;
use Trinavo\PaymentGateway\Providers\PaymentGatewayServiceProvider;

class TabbyPaymentPluginTest extends TestCase
{
    protected PaymentMethod $paymentMethod;

    protected TabbyPaymentPlugin $plugin;

    protected function getPackageProviders($app)
    {
        return [PaymentGatewayServiceProvider::class];
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentMethod = PaymentMethod::create([
            'name' => 'tabby',
            'plugin_class' => TabbyPaymentPlugin::class,
            'display_name' => 'Tabby Payment',
            'enabled' => true,
        ]);

        $this->paymentMethod->setSetting('public_key_sandbox', 'pk_test_123', true);
        $this->paymentMethod->setSetting('secret_key_sandbox', 'sk_test_123', true);
        $this->paymentMethod->setSetting('sandbox_mode', true, false);
        $this->paymentMethod->setSetting('payment_product', 'installments', false);
        $this->paymentMethod->setSetting('supported_currency', 'AED', false);

        $this->plugin = new TabbyPaymentPlugin($this->paymentMethod);
    }

    public function test_plugin_returns_correct_name()
    {
        $this->assertEquals('Tabby Payment Plugin', $this->plugin->getName());
    }

    public function test_plugin_returns_correct_description()
    {
        $description = $this->plugin->getDescription();
        $this->assertStringContainsString('Tabby', $description);
        $this->assertStringContainsString('BNPL', $description);
    }

    public function test_plugin_validates_configuration_in_sandbox_mode()
    {
        $this->assertTrue($this->plugin->validateConfiguration());
    }

    public function test_plugin_fails_validation_without_required_keys()
    {
        // Clear the required keys to test validation failure
        $this->paymentMethod->setSetting('public_key_sandbox', '', false);
        $this->paymentMethod->setSetting('secret_key_sandbox', '', false);
        $this->paymentMethod->setSetting('sandbox_mode', true, false);

        $plugin = new TabbyPaymentPlugin($this->paymentMethod);
        $this->assertFalse($plugin->validateConfiguration());
    }

    public function test_plugin_returns_configuration_fields()
    {
        $fields = $this->plugin->getConfigurationFields();

        $this->assertIsArray($fields);
        $this->assertNotEmpty($fields);

        // Check that required fields are present
        $fieldNames = array_map(fn ($field) => $field->getName(), $fields);
        $this->assertContains('public_key_sandbox', $fieldNames);
        $this->assertContains('secret_key_sandbox', $fieldNames);
        $this->assertContains('sandbox_mode', $fieldNames);
        $this->assertContains('supported_currency', $fieldNames);
        $this->assertContains('merchant_code', $fieldNames);
    }

    public function test_plugin_handles_successful_callback()
    {
        $callbackData = [
            'status' => 'AUTHORIZED',
            'order_code' => 'PO-123',
            'payment_id' => 'pay_test_123',
            'tabby_id' => 'tabby_test_123',
            'message' => 'Payment authorized successfully',
        ];

        $result = $this->plugin->handleCallback($callbackData);

        $this->assertTrue($result->success);
        $this->assertEquals('PO-123', $result->orderCode);
        $this->assertEquals('tabby_test_123', $result->transactionId);
    }

    public function test_plugin_handles_failed_callback()
    {
        $callbackData = [
            'status' => 'REJECTED',
            'order_code' => 'PO-123',
            'payment_id' => 'pay_test_123',
            'message' => 'Payment was rejected',
        ];

        $result = $this->plugin->handleCallback($callbackData);

        $this->assertFalse($result->success);
        $this->assertEquals('PO-123', $result->orderCode);
    }

    public function test_plugin_requires_order_code_in_callback()
    {
        $callbackData = [
            'status' => 'AUTHORIZED',
            'payment_id' => 'pay_test_123',
        ];

        $result = $this->plugin->handleCallback($callbackData);

        $this->assertFalse($result->success);
        $this->assertEquals('unknown', $result->orderCode);
        $this->assertStringContainsString('required', $result->message);
    }

    public function test_plugin_processes_payment_returns_view()
    {
        $paymentOrder = PaymentOrder::create([
            'amount' => 100.00,
            'currency' => 'AED',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'customer_phone' => '+971501234567',
        ]);

        $result = $this->plugin->processPayment($paymentOrder);

        $this->assertInstanceOf(\Illuminate\View\View::class, $result);
        $this->assertEquals('payment-gateway::plugins.tabby-payment-error', $result->name());

        // Check that required variables are passed to the view
        $viewData = $result->getData();
        $this->assertArrayHasKey('paymentOrder', $viewData);
        $this->assertArrayHasKey('paymentMethod', $viewData);
        $this->assertArrayHasKey('errorMessage', $viewData);
        $this->assertArrayHasKey('failureUrl', $viewData);
    }
}
