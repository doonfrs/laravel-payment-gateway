<?php

namespace Trinavo\PaymentGateway\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Trinavo\PaymentGateway\Models\PaymentMethod;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Plugins\Tamara\TamaraPaymentPlugin;
use Trinavo\PaymentGateway\Providers\PaymentGatewayServiceProvider;

class TamaraPaymentPluginTest extends TestCase
{
    protected PaymentMethod $paymentMethod;

    protected TamaraPaymentPlugin $plugin;

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
            'name' => 'tamara',
            'plugin_class' => TamaraPaymentPlugin::class,
            'display_name' => 'Tamara Payment',
            'enabled' => true,
        ]);

        $this->paymentMethod->setSetting('api_token_sandbox', 'api_test_123', true);
        $this->paymentMethod->setSetting('notification_token_sandbox', 'notif_test_123', true);
        $this->paymentMethod->setSetting('public_key_sandbox', 'pk_test_123', true);
        $this->paymentMethod->setSetting('sandbox_mode', true, false);
        $this->paymentMethod->setSetting('payment_type', 'PAY_BY_INSTALMENTS', false);
        $this->paymentMethod->setSetting('supported_currency', 'SAR', false);

        $this->plugin = new TamaraPaymentPlugin($this->paymentMethod);
    }

    public function test_plugin_returns_correct_name()
    {
        $this->assertEquals('Tamara Payment Plugin', $this->plugin->getName());
    }

    public function test_plugin_returns_correct_description()
    {
        $description = $this->plugin->getDescription();
        $this->assertStringContainsString('Tamara', $description);
        $this->assertStringContainsString('BNPL', $description);
    }

    public function test_plugin_validates_configuration_in_sandbox_mode()
    {
        $this->assertTrue($this->plugin->validateConfiguration());
    }

    public function test_plugin_fails_validation_without_required_keys()
    {
        // Clear the required keys to test validation failure
        $this->paymentMethod->setSetting('api_token_sandbox', '', false);
        $this->paymentMethod->setSetting('notification_token_sandbox', '', false);
        $this->paymentMethod->setSetting('sandbox_mode', true, false);

        $plugin = new TamaraPaymentPlugin($this->paymentMethod);
        $this->assertFalse($plugin->validateConfiguration());
    }

    public function test_plugin_validates_production_configuration()
    {
        // Set production configuration
        $this->paymentMethod->setSetting('api_token_production', 'api_prod_123', true);
        $this->paymentMethod->setSetting('notification_token_production', 'notif_prod_123', true);
        $this->paymentMethod->setSetting('sandbox_mode', false, false);

        $plugin = new TamaraPaymentPlugin($this->paymentMethod);
        $this->assertTrue($plugin->validateConfiguration());
    }

    public function test_plugin_returns_configuration_fields()
    {
        $fields = $this->plugin->getConfigurationFields();

        $this->assertIsArray($fields);
        $this->assertNotEmpty($fields);

        // Check that required fields are present
        $fieldNames = array_map(fn ($field) => $field->getName(), $fields);
        $this->assertContains('api_token_sandbox', $fieldNames);
        $this->assertContains('notification_token_sandbox', $fieldNames);
        $this->assertContains('public_key_sandbox', $fieldNames);
        $this->assertContains('api_token_production', $fieldNames);
        $this->assertContains('notification_token_production', $fieldNames);
        $this->assertContains('public_key_production', $fieldNames);
        $this->assertContains('sandbox_mode', $fieldNames);
        $this->assertContains('supported_currency', $fieldNames);
        $this->assertContains('payment_type', $fieldNames);
        $this->assertContains('merchant_code', $fieldNames);
    }

    public function test_plugin_handles_successful_callback()
    {
        $callbackData = [
            'status' => 'APPROVED',
            'order_code' => 'PO-123',
            'order_id' => 'tamara_test_123',
            'payment_status' => 'APPROVED',
            'message' => 'Payment approved successfully',
        ];

        $result = $this->plugin->handleCallback($callbackData);

        $this->assertTrue($result->success);
        $this->assertEquals('PO-123', $result->orderCode);
        $this->assertEquals('tamara_test_123', $result->transactionId);
    }

    public function test_plugin_handles_successful_callback_with_captured_status()
    {
        $callbackData = [
            'status' => 'CAPTURED',
            'order_code' => 'PO-123',
            'order_id' => 'tamara_test_123',
            'payment_status' => 'CAPTURED',
            'message' => 'Payment captured successfully',
        ];

        $result = $this->plugin->handleCallback($callbackData);

        $this->assertTrue($result->success);
        $this->assertEquals('PO-123', $result->orderCode);
        $this->assertEquals('tamara_test_123', $result->transactionId);
    }

    public function test_plugin_handles_successful_callback_with_fully_captured_status()
    {
        $callbackData = [
            'status' => 'FULLY_CAPTURED',
            'order_code' => 'PO-123',
            'order_id' => 'tamara_test_123',
            'payment_status' => 'FULLY_CAPTURED',
            'message' => 'Payment fully captured successfully',
        ];

        $result = $this->plugin->handleCallback($callbackData);

        $this->assertTrue($result->success);
        $this->assertEquals('PO-123', $result->orderCode);
        $this->assertEquals('tamara_test_123', $result->transactionId);
    }

    public function test_plugin_handles_failed_callback()
    {
        $callbackData = [
            'status' => 'DECLINED',
            'order_code' => 'PO-123',
            'order_id' => 'tamara_test_123',
            'message' => 'Payment was declined',
        ];

        $result = $this->plugin->handleCallback($callbackData);

        $this->assertFalse($result->success);
        $this->assertEquals('PO-123', $result->orderCode);
    }

    public function test_plugin_handles_cancelled_callback()
    {
        $callbackData = [
            'status' => 'CANCELLED',
            'order_code' => 'PO-123',
            'order_id' => 'tamara_test_123',
            'message' => 'Payment was cancelled',
        ];

        $result = $this->plugin->handleCallback($callbackData);

        $this->assertFalse($result->success);
        $this->assertEquals('PO-123', $result->orderCode);
    }

    public function test_plugin_requires_order_code_in_callback()
    {
        $callbackData = [
            'status' => 'APPROVED',
            'order_id' => 'tamara_test_123',
        ];

        $result = $this->plugin->handleCallback($callbackData);

        $this->assertFalse($result->success);
        $this->assertEquals('unknown', $result->orderCode);
        $this->assertStringContainsString('required', $result->message);
    }

    public function test_plugin_handles_callback_with_order_reference_id()
    {
        $callbackData = [
            'status' => 'APPROVED',
            'order_reference_id' => 'PO-123',
            'order_id' => 'tamara_test_123',
            'payment_status' => 'APPROVED',
            'message' => 'Payment approved successfully',
        ];

        $result = $this->plugin->handleCallback($callbackData);

        $this->assertTrue($result->success);
        $this->assertEquals('PO-123', $result->orderCode);
        $this->assertEquals('tamara_test_123', $result->transactionId);
    }

    public function test_plugin_processes_payment_returns_view()
    {
        $paymentOrder = PaymentOrder::create([
            'amount' => 100.00,
            'currency' => 'SAR',
            'customer_name' => 'Ahmed Ali',
            'customer_email' => 'ahmed@example.com',
            'customer_phone' => '+966501234567',
        ]);

        $result = $this->plugin->processPayment($paymentOrder);

        $this->assertInstanceOf(\Illuminate\View\View::class, $result);
        $this->assertEquals('payment-gateway::plugins.tamara-payment-error', $result->name());

        // Check that required variables are passed to the view
        $viewData = $result->getData();
        $this->assertArrayHasKey('paymentOrder', $viewData);
        $this->assertArrayHasKey('paymentMethod', $viewData);
        $this->assertArrayHasKey('errorMessage', $viewData);
        $this->assertArrayHasKey('failureUrl', $viewData);
    }

    public function test_plugin_supports_different_currencies()
    {
        $currencies = ['SAR', 'AED', 'KWD', 'BHD', 'QAR'];

        foreach ($currencies as $currency) {
            $this->paymentMethod->setSetting('supported_currency', $currency, false);
            $plugin = new TamaraPaymentPlugin($this->paymentMethod);

            $this->assertTrue($plugin->validateConfiguration());
        }
    }

    public function test_plugin_supports_different_payment_types()
    {
        $paymentTypes = ['PAY_BY_INSTALMENTS', 'PAY_BY_LATER', 'PAY_BY_MONTH', 'PAY_NOW'];

        foreach ($paymentTypes as $paymentType) {
            $this->paymentMethod->setSetting('payment_type', $paymentType, false);
            $plugin = new TamaraPaymentPlugin($this->paymentMethod);

            $this->assertTrue($plugin->validateConfiguration());
        }
    }

    public function test_plugin_handles_callback_with_payment_status_only()
    {
        $callbackData = [
            'payment_status' => 'APPROVED',
            'order_code' => 'PO-123',
            'order_id' => 'tamara_test_123',
            'message' => 'Payment approved successfully',
        ];

        $result = $this->plugin->handleCallback($callbackData);

        $this->assertTrue($result->success);
        $this->assertEquals('PO-123', $result->orderCode);
        $this->assertEquals('tamara_test_123', $result->transactionId);
    }

    public function test_plugin_generates_fallback_transaction_id()
    {
        $callbackData = [
            'status' => 'APPROVED',
            'order_code' => 'PO-123',
            'message' => 'Payment approved successfully',
        ];

        $result = $this->plugin->handleCallback($callbackData);

        $this->assertTrue($result->success);
        $this->assertEquals('PO-123', $result->orderCode);
        $this->assertStringStartsWith('tamara_', $result->transactionId);
    }

    public function test_plugin_includes_additional_data_in_failure_response()
    {
        $callbackData = [
            'status' => 'DECLINED',
            'order_code' => 'PO-123',
            'order_id' => 'tamara_test_123',
            'payment_status' => 'DECLINED',
            'message' => 'Payment was declined',
        ];

        $result = $this->plugin->handleCallback($callbackData);

        $this->assertFalse($result->success);
        $this->assertEquals('PO-123', $result->orderCode);
        $this->assertArrayHasKey('tamara_order_id', $result->additionalData);
        $this->assertArrayHasKey('tamara_status', $result->additionalData);
        $this->assertArrayHasKey('tamara_payment_status', $result->additionalData);
        $this->assertEquals('tamara_test_123', $result->additionalData['tamara_order_id']);
        $this->assertEquals('DECLINED', $result->additionalData['tamara_status']);
        $this->assertEquals('DECLINED', $result->additionalData['tamara_payment_status']);
    }
}
