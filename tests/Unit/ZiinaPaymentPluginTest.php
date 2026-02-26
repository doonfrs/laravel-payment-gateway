<?php

namespace Trinavo\PaymentGateway\Tests\Unit;

use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;
use Trinavo\PaymentGateway\Models\PaymentMethod;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Plugins\Ziina\ZiinaPaymentPlugin;
use Trinavo\PaymentGateway\Providers\PaymentGatewayServiceProvider;

class ZiinaPaymentPluginTest extends TestCase
{
    protected PaymentMethod $paymentMethod;

    protected ZiinaPaymentPlugin $plugin;

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
            'name' => 'ziina',
            'plugin_class' => ZiinaPaymentPlugin::class,
            'display_name' => 'Ziina Payment',
            'enabled' => true,
        ]);

        $this->paymentMethod->setSetting('api_key_test', 'test_api_key_123', true);
        $this->paymentMethod->setSetting('api_key_live', 'live_api_key_456', true);
        $this->paymentMethod->setSetting('webhook_secret', 'webhook_secret_789', true);
        $this->paymentMethod->setSetting('test_mode', true, false);

        $this->plugin = new ZiinaPaymentPlugin($this->paymentMethod);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_returns_correct_name()
    {
        $this->assertEquals('Ziina Payment Plugin', $this->plugin->getName());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_returns_correct_description()
    {
        $description = $this->plugin->getDescription();
        $this->assertStringContainsString('Ziina', $description);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_returns_configuration_fields()
    {
        $fields = $this->plugin->getConfigurationFields();

        $this->assertIsArray($fields);
        $this->assertNotEmpty($fields);

        $fieldNames = array_map(fn ($field) => $field->getName(), $fields);
        $this->assertContains('api_key_test', $fieldNames);
        $this->assertContains('api_key_live', $fieldNames);
        $this->assertContains('webhook_secret', $fieldNames);
        $this->assertContains('test_mode', $fieldNames);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_validates_configuration_in_test_mode()
    {
        $this->assertTrue($this->plugin->validateConfiguration());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_fails_validation_without_test_api_key()
    {
        $this->paymentMethod->setSetting('api_key_test', '', false);
        $this->paymentMethod->setSetting('test_mode', true, false);

        $plugin = new ZiinaPaymentPlugin($this->paymentMethod);
        $this->assertFalse($plugin->validateConfiguration());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_validates_configuration_in_live_mode()
    {
        $this->paymentMethod->setSetting('test_mode', false, false);

        $plugin = new ZiinaPaymentPlugin($this->paymentMethod);
        $this->assertTrue($plugin->validateConfiguration());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_fails_validation_without_live_api_key()
    {
        $this->paymentMethod->setSetting('api_key_live', '', false);
        $this->paymentMethod->setSetting('test_mode', false, false);

        $plugin = new ZiinaPaymentPlugin($this->paymentMethod);
        $this->assertFalse($plugin->validateConfiguration());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_handles_successful_redirect_callback()
    {
        Http::fake([
            'api-v2.ziina.com/api/payment_intent/pi_test_123' => Http::response([
                'id' => 'pi_test_123',
                'status' => 'completed',
                'operation_id' => 'op_test_123',
                'amount' => 10000,
                'currency_code' => 'AED',
            ], 200),
        ]);

        $callbackData = [
            'order_code' => 'PO-123',
            'payment_intent_id' => 'pi_test_123',
            'status' => 'success',
        ];

        $result = $this->plugin->handleCallback($callbackData);

        $this->assertTrue($result->success);
        $this->assertEquals('PO-123', $result->orderCode);
        $this->assertEquals('op_test_123', $result->transactionId);
        $this->assertEquals('completed', $result->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_handles_cancelled_redirect_callback()
    {
        $callbackData = [
            'order_code' => 'PO-123',
            'payment_intent_id' => 'pi_test_123',
            'status' => 'cancelled',
        ];

        $result = $this->plugin->handleCallback($callbackData);

        $this->assertFalse($result->success);
        $this->assertEquals('PO-123', $result->orderCode);
        $this->assertTrue($result->isCancelled());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_handles_failed_redirect_callback()
    {
        Http::fake([
            'api-v2.ziina.com/api/payment_intent/pi_test_123' => Http::response([
                'id' => 'pi_test_123',
                'status' => 'failed',
                'latest_error' => ['message' => 'Card declined'],
            ], 200),
        ]);

        $callbackData = [
            'order_code' => 'PO-123',
            'payment_intent_id' => 'pi_test_123',
            'status' => 'success',
        ];

        $result = $this->plugin->handleCallback($callbackData);

        $this->assertFalse($result->success);
        $this->assertEquals('PO-123', $result->orderCode);
        $this->assertEquals('failed', $result->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_requires_order_code_in_callback()
    {
        $callbackData = [
            'payment_intent_id' => 'pi_test_123',
            'status' => 'success',
        ];

        $result = $this->plugin->handleCallback($callbackData);

        $this->assertFalse($result->success);
        $this->assertEquals('unknown', $result->orderCode);
        $this->assertStringContainsString('required', strtolower($result->message));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_handles_webhook_callback_completed()
    {
        $callbackData = [
            'event' => 'payment_intent.status.updated',
            'data' => [
                'id' => 'pi_test_123',
                'status' => 'completed',
                'operation_id' => 'op_test_123',
                'success_url' => 'https://example.com/callback?order_code=PO-123&payment_intent_id={PAYMENT_INTENT_ID}&status=success',
            ],
        ];

        // Mock the request for HMAC verification (no secret configured = skip)
        $this->paymentMethod->setSetting('webhook_secret', '', false);
        $plugin = new ZiinaPaymentPlugin($this->paymentMethod);

        $result = $plugin->handleCallback($callbackData);

        $this->assertTrue($result->success);
        $this->assertEquals('PO-123', $result->orderCode);
        $this->assertEquals('op_test_123', $result->transactionId);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_handles_webhook_callback_failed()
    {
        $callbackData = [
            'event' => 'payment_intent.status.updated',
            'data' => [
                'id' => 'pi_test_123',
                'status' => 'failed',
                'success_url' => 'https://example.com/callback?order_code=PO-123&payment_intent_id={PAYMENT_INTENT_ID}&status=success',
            ],
        ];

        $this->paymentMethod->setSetting('webhook_secret', '', false);
        $plugin = new ZiinaPaymentPlugin($this->paymentMethod);

        $result = $plugin->handleCallback($callbackData);

        $this->assertFalse($result->success);
        $this->assertEquals('PO-123', $result->orderCode);
        $this->assertEquals('failed', $result->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_processes_payment_creates_intent_and_redirects()
    {
        Http::fake([
            'api-v2.ziina.com/api/payment_intent' => Http::response([
                'id' => 'pi_test_new',
                'redirect_url' => 'https://pay.ziina.com/checkout/pi_test_new',
                'status' => 'requires_payment_instrument',
            ], 200),
        ]);

        $paymentOrder = PaymentOrder::create([
            'amount' => 100.00,
            'currency' => 'AED',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'customer_phone' => '+971501234567',
        ]);

        $result = $this->plugin->processPayment($paymentOrder);

        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $result);
        $this->assertEquals('https://pay.ziina.com/checkout/pi_test_new', $result->getTargetUrl());

        $paymentOrder->refresh();
        $this->assertEquals('pi_test_new', $paymentOrder->payment_data['ziina_payment_intent_id']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api-v2.ziina.com/api/payment_intent'
                && $request['amount'] === 10000
                && $request['currency_code'] === 'AED'
                && $request['test'] === true;
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_processes_payment_returns_error_view_on_api_failure()
    {
        Http::fake([
            'api-v2.ziina.com/api/payment_intent' => Http::response([
                'error' => 'Invalid API key',
            ], 401),
        ]);

        $paymentOrder = PaymentOrder::create([
            'amount' => 100.00,
            'currency' => 'AED',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
        ]);

        $result = $this->plugin->processPayment($paymentOrder);

        $this->assertInstanceOf(\Illuminate\View\View::class, $result);
        $this->assertEquals('payment-gateway::plugins.ziina-payment-error', $result->name());

        $viewData = $result->getData();
        $this->assertArrayHasKey('paymentOrder', $viewData);
        $this->assertArrayHasKey('paymentMethod', $viewData);
        $this->assertArrayHasKey('failureUrl', $viewData);
        $this->assertArrayHasKey('errorMessage', $viewData);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_converts_amount_for_standard_currencies()
    {
        Http::fake([
            'api-v2.ziina.com/api/payment_intent' => Http::response([
                'id' => 'pi_test_amt',
                'redirect_url' => 'https://pay.ziina.com/checkout/pi_test_amt',
            ], 200),
        ]);

        $paymentOrder = PaymentOrder::create([
            'amount' => 100.50,
            'currency' => 'AED',
            'customer_name' => 'Test User',
            'customer_email' => 'test@example.com',
        ]);

        $this->plugin->processPayment($paymentOrder);

        Http::assertSent(function ($request) {
            return $request['amount'] === 10050;
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_converts_amount_for_three_decimal_currencies()
    {
        Http::fake([
            'api-v2.ziina.com/api/payment_intent' => Http::response([
                'id' => 'pi_test_bhd',
                'redirect_url' => 'https://pay.ziina.com/checkout/pi_test_bhd',
            ], 200),
        ]);

        $paymentOrder = PaymentOrder::create([
            'amount' => 100.500,
            'currency' => 'BHD',
            'customer_name' => 'Test User',
            'customer_email' => 'test@example.com',
        ]);

        $this->plugin->processPayment($paymentOrder);

        Http::assertSent(function ($request) {
            return $request['amount'] === 100500
                && $request['currency_code'] === 'BHD';
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_refund_fails_without_payment_intent_id()
    {
        $paymentOrder = PaymentOrder::create([
            'amount' => 100.00,
            'currency' => 'AED',
            'customer_name' => 'Test User',
            'customer_email' => 'test@example.com',
            'payment_data' => [],
        ]);

        $result = $this->plugin->refund($paymentOrder);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not found', $result->message);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_refund_succeeds_with_valid_payment_intent_id()
    {
        Http::fake([
            'api-v2.ziina.com/api/refund' => Http::response([
                'id' => 'ref_test_123',
                'status' => 'completed',
            ], 200),
        ]);

        $paymentOrder = PaymentOrder::create([
            'amount' => 100.00,
            'currency' => 'AED',
            'customer_name' => 'Test User',
            'customer_email' => 'test@example.com',
            'payment_data' => [
                'ziina_payment_intent_id' => 'pi_test_123',
            ],
        ]);

        $result = $this->plugin->refund($paymentOrder);

        $this->assertTrue($result->success);
        $this->assertEquals('ref_test_123', $result->refundTransactionId);
        $this->assertEquals('pi_test_123', $result->originalTransactionId);
        $this->assertEquals(100.00, $result->refundedAmount);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api-v2.ziina.com/api/refund'
                && $request['payment_intent_id'] === 'pi_test_123'
                && $request['amount'] === 10000;
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_rejects_unsupported_currency()
    {
        Http::fake();

        $paymentOrder = PaymentOrder::create([
            'amount' => 100.00,
            'currency' => 'XYZ',
            'customer_name' => 'Test User',
            'customer_email' => 'test@example.com',
        ]);

        $result = $this->plugin->processPayment($paymentOrder);

        $this->assertInstanceOf(\Illuminate\View\View::class, $result);
        $this->assertEquals('payment-gateway::plugins.ziina-payment-error', $result->name());

        Http::assertNothingSent();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_handles_pending_payment_status()
    {
        Http::fake([
            'api-v2.ziina.com/api/payment_intent/pi_test_pending' => Http::response([
                'id' => 'pi_test_pending',
                'status' => 'pending',
            ], 200),
        ]);

        $callbackData = [
            'order_code' => 'PO-456',
            'payment_intent_id' => 'pi_test_pending',
            'status' => 'success',
        ];

        $result = $this->plugin->handleCallback($callbackData);

        $this->assertFalse($result->success);
        $this->assertEquals('PO-456', $result->orderCode);
        $this->assertEquals('pending', $result->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_webhook_extracts_order_code_from_db_fallback()
    {
        $paymentOrder = PaymentOrder::create([
            'amount' => 50.00,
            'currency' => 'AED',
            'customer_name' => 'Test User',
            'customer_email' => 'test@example.com',
            'payment_data' => [
                'ziina_payment_intent_id' => 'pi_db_lookup',
            ],
        ]);

        $this->paymentMethod->setSetting('webhook_secret', '', false);
        $plugin = new ZiinaPaymentPlugin($this->paymentMethod);

        $callbackData = [
            'event' => 'payment_intent.status.updated',
            'data' => [
                'id' => 'pi_db_lookup',
                'status' => 'completed',
                'operation_id' => 'op_db_lookup',
            ],
        ];

        $result = $plugin->handleCallback($callbackData);

        $this->assertTrue($result->success);
        $this->assertEquals($paymentOrder->order_code, $result->orderCode);
    }
}
