<?php

namespace Trinavo\PaymentGateway\Tests\Unit;

use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;
use Trinavo\PaymentGateway\Models\PaymentMethod;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Plugins\MyFatoorah\MyFatoorahPaymentPlugin;
use Trinavo\PaymentGateway\Providers\PaymentGatewayServiceProvider;

class MyFatoorahPaymentPluginTest extends TestCase
{
    protected PaymentMethod $paymentMethod;

    protected MyFatoorahPaymentPlugin $plugin;

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
            'name' => 'myfatoorah',
            'plugin_class' => MyFatoorahPaymentPlugin::class,
            'display_name' => 'MyFatoorah Payment',
            'enabled' => true,
        ]);

        $this->paymentMethod->setSetting('api_token_test', 'test_token_abc123', true);
        $this->paymentMethod->setSetting('api_token_live', 'live_token_xyz789', true);
        $this->paymentMethod->setSetting('test_mode', true, false);
        $this->paymentMethod->setSetting('country_iso', 'KWT', false);

        $this->plugin = new MyFatoorahPaymentPlugin($this->paymentMethod);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_returns_correct_name()
    {
        $this->assertEquals('MyFatoorah Payment Plugin', $this->plugin->getName());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_returns_correct_description()
    {
        $description = $this->plugin->getDescription();
        $this->assertStringContainsString('MyFatoorah', $description);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_returns_configuration_fields()
    {
        $fields = $this->plugin->getConfigurationFields();

        $this->assertIsArray($fields);
        $this->assertNotEmpty($fields);

        $fieldNames = array_map(fn ($field) => $field->getName(), $fields);
        $this->assertContains('api_token_test', $fieldNames);
        $this->assertContains('api_token_live', $fieldNames);
        $this->assertContains('test_mode', $fieldNames);
        $this->assertContains('country_iso', $fieldNames);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_validates_configuration_in_test_mode()
    {
        $this->assertTrue($this->plugin->validateConfiguration());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_fails_validation_without_country_iso()
    {
        $this->paymentMethod->setSetting('country_iso', '', false);

        $plugin = new MyFatoorahPaymentPlugin($this->paymentMethod);
        $this->assertFalse($plugin->validateConfiguration());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_fails_validation_without_test_token()
    {
        $this->paymentMethod->setSetting('api_token_test', '', false);
        $this->paymentMethod->setSetting('test_mode', true, false);

        $plugin = new MyFatoorahPaymentPlugin($this->paymentMethod);
        $this->assertFalse($plugin->validateConfiguration());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_validates_configuration_in_live_mode()
    {
        $this->paymentMethod->setSetting('test_mode', false, false);

        $plugin = new MyFatoorahPaymentPlugin($this->paymentMethod);
        $this->assertTrue($plugin->validateConfiguration());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_fails_validation_without_live_token()
    {
        $this->paymentMethod->setSetting('api_token_live', '', false);
        $this->paymentMethod->setSetting('test_mode', false, false);

        $plugin = new MyFatoorahPaymentPlugin($this->paymentMethod);
        $this->assertFalse($plugin->validateConfiguration());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_resolves_test_base_url()
    {
        $this->assertEquals('https://apitest.myfatoorah.com', $this->plugin->getBaseUrl());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_resolves_live_base_url_for_kuwait()
    {
        $this->paymentMethod->setSetting('test_mode', false, false);
        $this->paymentMethod->setSetting('country_iso', 'KWT', false);

        $plugin = new MyFatoorahPaymentPlugin($this->paymentMethod);
        $this->assertEquals('https://api.myfatoorah.com', $plugin->getBaseUrl());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_resolves_live_base_url_for_uae()
    {
        $this->paymentMethod->setSetting('test_mode', false, false);
        $this->paymentMethod->setSetting('country_iso', 'ARE', false);

        $plugin = new MyFatoorahPaymentPlugin($this->paymentMethod);
        $this->assertEquals('https://api-ae.myfatoorah.com', $plugin->getBaseUrl());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_resolves_live_base_url_for_saudi()
    {
        $this->paymentMethod->setSetting('test_mode', false, false);
        $this->paymentMethod->setSetting('country_iso', 'SAU', false);

        $plugin = new MyFatoorahPaymentPlugin($this->paymentMethod);
        $this->assertEquals('https://api-sa.myfatoorah.com', $plugin->getBaseUrl());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_resolves_live_base_url_for_egypt()
    {
        $this->paymentMethod->setSetting('test_mode', false, false);
        $this->paymentMethod->setSetting('country_iso', 'EGY', false);

        $plugin = new MyFatoorahPaymentPlugin($this->paymentMethod);
        $this->assertEquals('https://api-eg.myfatoorah.com', $plugin->getBaseUrl());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_processes_payment_creates_invoice_and_redirects()
    {
        Http::fake([
            'apitest.myfatoorah.com/v2/SendPayment' => Http::response([
                'IsSuccess' => true,
                'Data' => [
                    'InvoiceId' => 12345,
                    'InvoiceURL' => 'https://demo.myfatoorah.com/pay/12345',
                ],
            ], 200),
        ]);

        $paymentOrder = PaymentOrder::create([
            'amount' => 50.00,
            'currency' => 'KWD',
            'customer_name' => 'Ali Hassan',
            'customer_email' => 'ali@example.com',
            'customer_phone' => '+96512345678',
        ]);

        $result = $this->plugin->processPayment($paymentOrder);

        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $result);
        $this->assertEquals('https://demo.myfatoorah.com/pay/12345', $result->getTargetUrl());

        $paymentOrder->refresh();
        $this->assertEquals(12345, $paymentOrder->payment_data['myfatoorah_invoice_id']);

        Http::assertSent(function ($request) use ($paymentOrder) {
            return $request->url() === 'https://apitest.myfatoorah.com/v2/SendPayment'
                && $request['InvoiceValue'] === 50.00
                && $request['CustomerReference'] === $paymentOrder->order_code;
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_processes_payment_returns_error_on_api_failure()
    {
        Http::fake([
            'apitest.myfatoorah.com/v2/SendPayment' => Http::response([
                'IsSuccess' => false,
                'Message' => 'Invalid token',
            ], 401),
        ]);

        $paymentOrder = PaymentOrder::create([
            'amount' => 50.00,
            'currency' => 'KWD',
            'customer_name' => 'Ali Hassan',
            'customer_email' => 'ali@example.com',
        ]);

        $result = $this->plugin->processPayment($paymentOrder);

        $this->assertInstanceOf(\Illuminate\View\View::class, $result);
        $this->assertEquals('payment-gateway::plugins.myfatoorah-payment-error', $result->name());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_handles_successful_callback()
    {
        Http::fake([
            'apitest.myfatoorah.com/v2/GetPaymentStatus' => Http::response([
                'IsSuccess' => true,
                'Data' => [
                    'InvoiceId' => 12345,
                    'InvoiceStatus' => 'Paid',
                    'CustomerReference' => 'PO-123',
                    'InvoiceTransactions' => [
                        [
                            'TransactionId' => 'txn_mf_123',
                            'TransactionStatus' => 'Succss',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->plugin->handleCallback([
            'paymentId' => 'pay_mf_123',
        ]);

        $this->assertTrue($result->success);
        $this->assertEquals('PO-123', $result->orderCode);
        $this->assertEquals('txn_mf_123', $result->transactionId);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_handles_failed_callback()
    {
        Http::fake([
            'apitest.myfatoorah.com/v2/GetPaymentStatus' => Http::response([
                'IsSuccess' => true,
                'Data' => [
                    'InvoiceId' => 12345,
                    'InvoiceStatus' => 'Pending',
                    'CustomerReference' => 'PO-456',
                    'InvoiceTransactions' => [],
                ],
            ], 200),
        ]);

        $result = $this->plugin->handleCallback([
            'paymentId' => 'pay_mf_456',
        ]);

        $this->assertFalse($result->success);
        $this->assertEquals('PO-456', $result->orderCode);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_handles_callback_without_payment_id()
    {
        $result = $this->plugin->handleCallback([
            'some_field' => 'value',
        ]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('required', strtolower($result->message));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_refund_succeeds()
    {
        Http::fake([
            'apitest.myfatoorah.com/v2/MakeRefund' => Http::response([
                'IsSuccess' => true,
                'Data' => [
                    'RefundId' => 'ref_mf_123',
                ],
            ], 200),
        ]);

        $paymentOrder = PaymentOrder::create([
            'amount' => 50.00,
            'currency' => 'KWD',
            'customer_name' => 'Test User',
            'customer_email' => 'test@example.com',
            'payment_data' => [
                'myfatoorah_invoice_id' => 12345,
            ],
        ]);

        $result = $this->plugin->refund($paymentOrder);

        $this->assertTrue($result->success);
        $this->assertEquals('ref_mf_123', $result->refundTransactionId);
        $this->assertEquals('12345', $result->originalTransactionId);
        $this->assertEquals(50.00, $result->refundedAmount);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_refund_fails_without_invoice_id()
    {
        $paymentOrder = PaymentOrder::create([
            'amount' => 50.00,
            'currency' => 'KWD',
            'customer_name' => 'Test User',
            'customer_email' => 'test@example.com',
            'payment_data' => [],
        ]);

        $result = $this->plugin->refund($paymentOrder);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not found', $result->message);
    }
}
