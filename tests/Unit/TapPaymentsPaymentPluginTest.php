<?php

namespace Trinavo\PaymentGateway\Tests\Unit;

use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;
use Trinavo\PaymentGateway\Models\PaymentMethod;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Plugins\TapPayments\TapPaymentsPaymentPlugin;
use Trinavo\PaymentGateway\Providers\PaymentGatewayServiceProvider;

class TapPaymentsPaymentPluginTest extends TestCase
{
    protected PaymentMethod $paymentMethod;

    protected TapPaymentsPaymentPlugin $plugin;

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
            'name' => 'tap_payments',
            'plugin_class' => TapPaymentsPaymentPlugin::class,
            'display_name' => 'Tap Payments',
            'enabled' => true,
        ]);

        $this->paymentMethod->setSetting('secret_key_test', 'sk_test_abc123', true);
        $this->paymentMethod->setSetting('secret_key_live', 'sk_live_xyz789', true);
        $this->paymentMethod->setSetting('test_mode', true, false);

        $this->plugin = new TapPaymentsPaymentPlugin($this->paymentMethod);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_returns_correct_name()
    {
        $this->assertEquals('Tap Payments Plugin', $this->plugin->getName());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_returns_correct_description()
    {
        $description = $this->plugin->getDescription();
        $this->assertStringContainsString('Tap Payments', $description);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_returns_configuration_fields()
    {
        $fields = $this->plugin->getConfigurationFields();

        $this->assertIsArray($fields);
        $this->assertNotEmpty($fields);

        $fieldNames = array_map(fn ($field) => $field->getName(), $fields);
        $this->assertContains('secret_key_test', $fieldNames);
        $this->assertContains('secret_key_live', $fieldNames);
        $this->assertContains('test_mode', $fieldNames);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_validates_configuration_in_test_mode()
    {
        $this->assertTrue($this->plugin->validateConfiguration());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_fails_validation_without_test_key()
    {
        $this->paymentMethod->setSetting('secret_key_test', '', false);
        $this->paymentMethod->setSetting('test_mode', true, false);

        $plugin = new TapPaymentsPaymentPlugin($this->paymentMethod);
        $this->assertFalse($plugin->validateConfiguration());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_validates_configuration_in_live_mode()
    {
        $this->paymentMethod->setSetting('test_mode', false, false);

        $plugin = new TapPaymentsPaymentPlugin($this->paymentMethod);
        $this->assertTrue($plugin->validateConfiguration());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_fails_validation_without_live_key()
    {
        $this->paymentMethod->setSetting('secret_key_live', '', false);
        $this->paymentMethod->setSetting('test_mode', false, false);

        $plugin = new TapPaymentsPaymentPlugin($this->paymentMethod);
        $this->assertFalse($plugin->validateConfiguration());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_processes_payment_creates_charge_and_redirects()
    {
        Http::fake([
            'api.tap.company/v2/charges/' => Http::response([
                'id' => 'chg_test_123',
                'status' => 'INITIATED',
                'transaction' => [
                    'url' => 'https://tap.company/pay/chg_test_123',
                ],
            ], 200),
        ]);

        $paymentOrder = PaymentOrder::create([
            'amount' => 100.00,
            'currency' => 'SAR',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'customer_phone' => '+966501234567',
        ]);

        $result = $this->plugin->processPayment($paymentOrder);

        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $result);
        $this->assertEquals('https://tap.company/pay/chg_test_123', $result->getTargetUrl());

        $paymentOrder->refresh();
        $this->assertEquals('chg_test_123', $paymentOrder->payment_data['tap_charge_id']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.tap.company/v2/charges/'
                && $request['amount'] === 100.00
                && $request['currency'] === 'SAR'
                && $request['source']['id'] === 'src_all';
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_processes_payment_returns_error_view_on_api_failure()
    {
        Http::fake([
            'api.tap.company/v2/charges/' => Http::response([
                'errors' => [['description' => 'Invalid API key']],
            ], 401),
        ]);

        $paymentOrder = PaymentOrder::create([
            'amount' => 100.00,
            'currency' => 'SAR',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
        ]);

        $result = $this->plugin->processPayment($paymentOrder);

        $this->assertInstanceOf(\Illuminate\View\View::class, $result);
        $this->assertEquals('payment-gateway::plugins.tap-payments-payment-error', $result->name());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_handles_successful_callback()
    {
        Http::fake([
            'api.tap.company/v2/charges/chg_test_456' => Http::response([
                'id' => 'chg_test_456',
                'status' => 'CAPTURED',
                'reference' => [
                    'transaction' => 'PO-123',
                ],
                'receipt' => [
                    'id' => 'rcpt_test_456',
                ],
                'card' => [
                    'brand' => 'VISA',
                    'last_four' => '1234',
                ],
            ], 200),
        ]);

        $result = $this->plugin->handleCallback([
            'tap_id' => 'chg_test_456',
        ]);

        $this->assertTrue($result->success);
        $this->assertEquals('PO-123', $result->orderCode);
        $this->assertEquals('chg_test_456', $result->transactionId);
        $this->assertEquals('completed', $result->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_handles_failed_callback()
    {
        Http::fake([
            'api.tap.company/v2/charges/chg_test_fail' => Http::response([
                'id' => 'chg_test_fail',
                'status' => 'FAILED',
                'reference' => [
                    'transaction' => 'PO-456',
                ],
            ], 200),
        ]);

        $result = $this->plugin->handleCallback([
            'tap_id' => 'chg_test_fail',
        ]);

        $this->assertFalse($result->success);
        $this->assertEquals('PO-456', $result->orderCode);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_handles_callback_without_tap_id()
    {
        $result = $this->plugin->handleCallback([
            'some_field' => 'value',
        ]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', strtolower($result->message));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_handles_callback_verification_failure()
    {
        Http::fake([
            'api.tap.company/v2/charges/chg_invalid' => Http::response([], 404),
        ]);

        $result = $this->plugin->handleCallback([
            'tap_id' => 'chg_invalid',
        ]);

        $this->assertFalse($result->success);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_refund_succeeds()
    {
        Http::fake([
            'api.tap.company/v2/refunds' => Http::response([
                'id' => 'ref_test_123',
                'status' => 'REFUNDED',
            ], 200),
        ]);

        $paymentOrder = PaymentOrder::create([
            'amount' => 100.00,
            'currency' => 'SAR',
            'customer_name' => 'Test User',
            'customer_email' => 'test@example.com',
            'payment_data' => [
                'tap_charge_id' => 'chg_test_123',
            ],
        ]);

        $result = $this->plugin->refund($paymentOrder);

        $this->assertTrue($result->success);
        $this->assertEquals('ref_test_123', $result->refundTransactionId);
        $this->assertEquals('chg_test_123', $result->originalTransactionId);
        $this->assertEquals(100.00, $result->refundedAmount);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_refund_fails_without_charge_id()
    {
        $paymentOrder = PaymentOrder::create([
            'amount' => 100.00,
            'currency' => 'SAR',
            'customer_name' => 'Test User',
            'customer_email' => 'test@example.com',
            'payment_data' => [],
        ]);

        $result = $this->plugin->refund($paymentOrder);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not found', $result->message);
    }
}
