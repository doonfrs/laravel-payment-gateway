<?php

namespace Trinavo\PaymentGateway\Tests\Unit;

use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;
use Trinavo\PaymentGateway\Models\PaymentMethod;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Plugins\Paymera\PaymeraPaymentPlugin;
use Trinavo\PaymentGateway\Providers\PaymentGatewayServiceProvider;

class PaymeraPaymentPluginTest extends TestCase
{
    protected PaymentMethod $paymentMethod;

    protected PaymeraPaymentPlugin $plugin;

    protected function getPackageProviders($app)
    {
        return [PaymentGatewayServiceProvider::class];
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('payment-gateway.plugins', [
            PaymeraPaymentPlugin::class,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentMethod = PaymentMethod::create([
            'name' => json_encode(['en' => 'Paymera Payment']),
            'plugin_class' => PaymeraPaymentPlugin::class,
            'enabled' => true,
        ]);

        $this->paymentMethod->setSetting('terminal_id_test', '99990001', true);
        $this->paymentMethod->setSetting('username_test', 'merchant_user', true);
        $this->paymentMethod->setSetting('password_test', 'merchant_pass', true);
        $this->paymentMethod->setSetting('test_mode', true, false);

        $this->plugin = new PaymeraPaymentPlugin($this->paymentMethod);
    }

    private function basicAuthHeader(string $username, string $password): string
    {
        return 'Basic ' . base64_encode($username . ':' . $password);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_returns_correct_name()
    {
        $this->assertEquals('Paymera', $this->plugin->getName());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_returns_description_mentioning_paymera()
    {
        $this->assertStringContainsString('Paymera', $this->plugin->getDescription());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_supports_syria()
    {
        $this->assertContains('SY', PaymeraPaymentPlugin::getSupportedCountries());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_returns_configuration_fields()
    {
        $fields = $this->plugin->getConfigurationFields();
        $names = array_map(fn ($field) => $field->getName(), $fields);

        foreach ([
            'terminal_id_test',
            'username_test',
            'password_test',
            'terminal_id_production',
            'username_production',
            'password_production',
            'test_mode',
        ] as $expected) {
            $this->assertContains($expected, $names);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_validates_configuration_in_test_mode()
    {
        $this->assertTrue($this->plugin->validateConfiguration());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_fails_validation_when_test_username_missing()
    {
        $this->paymentMethod->setSetting('username_test', '', true);
        $plugin = new PaymeraPaymentPlugin($this->paymentMethod);

        $this->assertFalse($plugin->validateConfiguration());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_validates_configuration_in_production_mode_when_creds_present()
    {
        $this->paymentMethod->setSetting('test_mode', false, false);
        $this->paymentMethod->setSetting('terminal_id_production', '88880002', true);
        $this->paymentMethod->setSetting('username_production', 'prod_user', true);
        $this->paymentMethod->setSetting('password_production', 'prod_pass', true);

        $plugin = new PaymeraPaymentPlugin($this->paymentMethod);
        $this->assertTrue($plugin->validateConfiguration());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_fails_validation_in_production_mode_without_creds()
    {
        $this->paymentMethod->setSetting('test_mode', false, false);
        $plugin = new PaymeraPaymentPlugin($this->paymentMethod);

        $this->assertFalse($plugin->validateConfiguration());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function process_payment_calls_create_payment_and_redirects()
    {
        Http::fake([
            'egate-t.paymera.cc/api/create-payment' => Http::response([
                'ErrorMessage' => 'Success',
                'ErrorCode' => 0,
                'Data' => [
                    'url' => 'https://egate-t.paymera.cc/start/test-payment-id/en',
                    'paymentId' => 'test-payment-id',
                ],
            ], 200),
        ]);

        $paymentOrder = PaymentOrder::create([
            'amount' => 1860000,
            'currency' => 'SYP',
            'customer_name' => 'Ahmad',
            'customer_email' => 'ahmad@example.com',
        ]);

        $result = $this->plugin->processPayment($paymentOrder);

        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $result);
        $this->assertEquals('https://egate-t.paymera.cc/start/test-payment-id/en', $result->getTargetUrl());

        $paymentOrder->refresh();
        $this->assertEquals('test-payment-id', $paymentOrder->external_transaction_id);
        $this->assertEquals('test-payment-id', $paymentOrder->payment_data['paymera_payment_id']);

        $expectedAuth = $this->basicAuthHeader('merchant_user', 'merchant_pass');

        Http::assertSent(function ($request) use ($paymentOrder, $expectedAuth) {
            return $request->url() === 'https://egate-t.paymera.cc/api/create-payment'
                && $request->method() === 'POST'
                && $request->header('Authorization')[0] === $expectedAuth
                && $request['terminalId'] === '99990001'
                && $request['amount'] === 1860000
                && str_contains($request['callbackURL'], 'order_code=' . urlencode($paymentOrder->order_code))
                && str_contains($request['triggerURL'], 'order_code=' . urlencode($paymentOrder->order_code))
                && str_contains($request['notes'], $paymentOrder->order_code);
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function process_payment_rounds_amount_to_integer()
    {
        Http::fake([
            'egate-t.paymera.cc/api/create-payment' => Http::response([
                'ErrorMessage' => 'Success',
                'ErrorCode' => 0,
                'Data' => [
                    'url' => 'https://egate-t.paymera.cc/start/x/en',
                    'paymentId' => 'x',
                ],
            ], 200),
        ]);

        $paymentOrder = PaymentOrder::create([
            'amount' => 1500.7,
            'currency' => 'SYP',
            'customer_name' => 'Sara',
            'customer_email' => 'sara@example.com',
        ]);

        $this->plugin->processPayment($paymentOrder);

        Http::assertSent(fn ($request) => $request['amount'] === 1501);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function process_payment_returns_error_view_when_api_returns_failure_code()
    {
        Http::fake([
            'egate-t.paymera.cc/api/create-payment' => Http::response([
                'ErrorMessage' => 'Unauthorized',
                'ErrorCode' => 1,
            ], 200),
        ]);

        $paymentOrder = PaymentOrder::create([
            'amount' => 1000,
            'currency' => 'SYP',
            'customer_name' => 'Test',
            'customer_email' => 'test@example.com',
        ]);

        $result = $this->plugin->processPayment($paymentOrder);

        $this->assertInstanceOf(\Illuminate\View\View::class, $result);
        $this->assertEquals('payment-gateway::plugins.paymera-payment-error', $result->name());

        $paymentOrder->refresh();
        $this->assertNull($paymentOrder->external_transaction_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function process_payment_returns_error_view_when_http_fails()
    {
        Http::fake([
            'egate-t.paymera.cc/api/create-payment' => Http::response(['error' => 'down'], 500),
        ]);

        $paymentOrder = PaymentOrder::create([
            'amount' => 1000,
            'currency' => 'SYP',
            'customer_name' => 'Test',
            'customer_email' => 'test@example.com',
        ]);

        $result = $this->plugin->processPayment($paymentOrder);

        $this->assertInstanceOf(\Illuminate\View\View::class, $result);
        $this->assertEquals('payment-gateway::plugins.paymera-payment-error', $result->name());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function handle_callback_returns_success_when_status_is_A()
    {
        $paymentOrder = PaymentOrder::create([
            'amount' => 1860000,
            'currency' => 'SYP',
            'customer_name' => 'Ahmad',
            'customer_email' => 'ahmad@example.com',
            'external_transaction_id' => 'pay-abc',
            'payment_data' => ['paymera_payment_id' => 'pay-abc'],
        ]);

        Http::fake([
            'egate-t.paymera.cc/api/get-payment-status/pay-abc' => Http::response([
                'ErrorMessage' => 'Success',
                'ErrorCode' => 0,
                'Data' => [
                    'status' => 'A',
                    'creationTimestamp' => '2026-05-17 12:00:00',
                    'rrn' => '000009876543',
                    'amount' => 1860000,
                    'terminalId' => '99990001',
                ],
            ], 200),
        ]);

        $result = $this->plugin->handleCallback(['order_code' => $paymentOrder->order_code]);

        $this->assertTrue($result->success);
        $this->assertEquals($paymentOrder->order_code, $result->orderCode);
        $this->assertEquals('000009876543', $result->transactionId);
        $this->assertEquals('A', $result->additionalData['paymera_status']);
        $this->assertEquals('pay-abc', $result->additionalData['paymera_payment_id']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function handle_callback_returns_failure_when_status_is_F()
    {
        $paymentOrder = PaymentOrder::create([
            'amount' => 1000,
            'currency' => 'SYP',
            'customer_name' => 'Test',
            'customer_email' => 'test@example.com',
            'payment_data' => ['paymera_payment_id' => 'pay-f'],
        ]);

        Http::fake([
            'egate-t.paymera.cc/api/get-payment-status/pay-f' => Http::response([
                'ErrorMessage' => 'Success',
                'ErrorCode' => 0,
                'Data' => ['status' => 'F', 'rrn' => null],
            ], 200),
        ]);

        $result = $this->plugin->handleCallback(['order_code' => $paymentOrder->order_code]);

        $this->assertFalse($result->success);
        $this->assertEquals('F', $result->additionalData['paymera_status']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function handle_callback_returns_cancelled_when_status_is_C()
    {
        $paymentOrder = PaymentOrder::create([
            'amount' => 1000,
            'currency' => 'SYP',
            'customer_name' => 'Test',
            'customer_email' => 'test@example.com',
            'payment_data' => ['paymera_payment_id' => 'pay-c'],
        ]);

        Http::fake([
            'egate-t.paymera.cc/api/get-payment-status/pay-c' => Http::response([
                'ErrorMessage' => 'Success',
                'ErrorCode' => 0,
                'Data' => ['status' => 'C'],
            ], 200),
        ]);

        $result = $this->plugin->handleCallback(['order_code' => $paymentOrder->order_code]);

        $this->assertFalse($result->success);
        $this->assertTrue($result->isCancelled());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function handle_callback_returns_pending_when_status_is_P()
    {
        $paymentOrder = PaymentOrder::create([
            'amount' => 1000,
            'currency' => 'SYP',
            'customer_name' => 'Test',
            'customer_email' => 'test@example.com',
            'payment_data' => ['paymera_payment_id' => 'pay-p'],
        ]);

        Http::fake([
            'egate-t.paymera.cc/api/get-payment-status/pay-p' => Http::response([
                'ErrorMessage' => 'Success',
                'ErrorCode' => 0,
                'Data' => ['status' => 'P', 'rrn' => '000009876544'],
            ], 200),
        ]);

        $result = $this->plugin->handleCallback(['order_code' => $paymentOrder->order_code]);

        $this->assertFalse($result->success);
        $this->assertEquals('pending', $result->status);
        $this->assertEquals('000009876544', $result->transactionId);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function handle_callback_fails_when_order_code_missing()
    {
        $result = $this->plugin->handleCallback([]);

        $this->assertFalse($result->success);
        $this->assertEquals('unknown', $result->orderCode);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function handle_callback_fails_when_paymera_payment_id_missing()
    {
        $paymentOrder = PaymentOrder::create([
            'amount' => 1000,
            'currency' => 'SYP',
            'customer_name' => 'Test',
            'customer_email' => 'test@example.com',
            'payment_data' => [],
        ]);

        $result = $this->plugin->handleCallback(['order_code' => $paymentOrder->order_code]);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not found', $result->message);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function refund_succeeds_when_cancel_payment_returns_error_code_zero()
    {
        Http::fake([
            'egate-t.paymera.cc/api/cancel-payment' => Http::response([
                'ErrorMessage' => 'Success',
                'ErrorCode' => 0,
            ], 200),
        ]);

        $paymentOrder = PaymentOrder::create([
            'amount' => 1500,
            'currency' => 'SYP',
            'customer_name' => 'Test',
            'customer_email' => 'test@example.com',
            'external_transaction_id' => 'pay-ref',
            'payment_data' => ['paymera_payment_id' => 'pay-ref'],
        ]);

        $result = $this->plugin->refund($paymentOrder);

        $this->assertTrue($result->success);
        $this->assertEquals('pay-ref', $result->refundTransactionId);
        $this->assertEquals('pay-ref', $result->originalTransactionId);
        $this->assertEquals(1500.0, $result->refundedAmount);

        $expectedAuth = $this->basicAuthHeader('merchant_user', 'merchant_pass');

        Http::assertSent(function ($request) use ($expectedAuth) {
            return $request->url() === 'https://egate-t.paymera.cc/api/cancel-payment'
                && $request->method() === 'POST'
                && $request->header('Authorization')[0] === $expectedAuth
                && $request['payment_id'] === 'pay-ref';
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function refund_fails_when_paymera_payment_id_missing()
    {
        $paymentOrder = PaymentOrder::create([
            'amount' => 500,
            'currency' => 'SYP',
            'customer_name' => 'Test',
            'customer_email' => 'test@example.com',
            'payment_data' => [],
        ]);

        $result = $this->plugin->refund($paymentOrder);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not found', $result->message);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function refund_fails_when_cancel_payment_returns_non_zero_error_code()
    {
        Http::fake([
            'egate-t.paymera.cc/api/cancel-payment' => Http::response([
                'ErrorMessage' => 'Not allowed',
                'ErrorCode' => 100,
            ], 200),
        ]);

        $paymentOrder = PaymentOrder::create([
            'amount' => 500,
            'currency' => 'SYP',
            'customer_name' => 'Test',
            'customer_email' => 'test@example.com',
            'payment_data' => ['paymera_payment_id' => 'pay-x'],
        ]);

        $result = $this->plugin->refund($paymentOrder);

        $this->assertFalse($result->success);
    }
}
