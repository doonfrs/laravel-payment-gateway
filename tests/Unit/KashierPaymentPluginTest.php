<?php

namespace Trinavo\PaymentGateway\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Trinavo\PaymentGateway\Models\PaymentMethod;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Plugins\Kashier\KashierPaymentPlugin;
use Trinavo\PaymentGateway\Providers\PaymentGatewayServiceProvider;

class KashierPaymentPluginTest extends TestCase
{
    protected PaymentMethod $paymentMethod;

    protected KashierPaymentPlugin $plugin;

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
            'name' => json_encode(['en' => 'Kashier Payment']),
            'plugin_class' => KashierPaymentPlugin::class,
            'enabled' => true,
        ]);

        $this->paymentMethod->setSetting('merchant_id', 'MID-3552-454', false);
        $this->paymentMethod->setSetting('api_key_test', '49c02cfa-8a4e-4120-8aa2-b154a6d08573', true);
        $this->paymentMethod->setSetting('api_key_live', 'live_api_key_456', true);
        $this->paymentMethod->setSetting('test_mode', true, false);
        $this->paymentMethod->setSetting('allowed_methods', 'card,wallet,bank_installments', false);
        $this->paymentMethod->setSetting('display_language', 'en', false);

        $this->plugin = new KashierPaymentPlugin($this->paymentMethod);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_returns_correct_name()
    {
        $this->assertEquals('Kashier Payment Plugin', $this->plugin->getName());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_returns_correct_description()
    {
        $description = $this->plugin->getDescription();
        $this->assertStringContainsString('Kashier', $description);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_returns_configuration_fields()
    {
        $fields = $this->plugin->getConfigurationFields();

        $this->assertIsArray($fields);
        $this->assertNotEmpty($fields);

        $fieldNames = array_map(fn ($field) => $field->getName(), $fields);
        $this->assertContains('merchant_id', $fieldNames);
        $this->assertContains('api_key_test', $fieldNames);
        $this->assertContains('api_key_live', $fieldNames);
        $this->assertContains('test_mode', $fieldNames);
        $this->assertContains('allowed_methods', $fieldNames);
        $this->assertContains('display_language', $fieldNames);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_validates_configuration_in_test_mode()
    {
        $this->assertTrue($this->plugin->validateConfiguration());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_fails_validation_without_merchant_id()
    {
        $this->paymentMethod->setSetting('merchant_id', '', false);

        $plugin = new KashierPaymentPlugin($this->paymentMethod);
        $this->assertFalse($plugin->validateConfiguration());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_fails_validation_without_test_api_key()
    {
        $this->paymentMethod->setSetting('api_key_test', '', false);
        $this->paymentMethod->setSetting('test_mode', true, false);

        $plugin = new KashierPaymentPlugin($this->paymentMethod);
        $this->assertFalse($plugin->validateConfiguration());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_validates_configuration_in_live_mode()
    {
        $this->paymentMethod->setSetting('test_mode', false, false);

        $plugin = new KashierPaymentPlugin($this->paymentMethod);
        $this->assertTrue($plugin->validateConfiguration());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_fails_validation_without_live_api_key()
    {
        $this->paymentMethod->setSetting('api_key_live', '', false);
        $this->paymentMethod->setSetting('test_mode', false, false);

        $plugin = new KashierPaymentPlugin($this->paymentMethod);
        $this->assertFalse($plugin->validateConfiguration());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_generates_correct_order_hash()
    {
        $mid = 'MID-3552-454';
        $orderId = 'PO-123';
        $amount = 20.0;
        $currency = 'EGP';
        $apiKey = '49c02cfa-8a4e-4120-8aa2-b154a6d08573';

        $hash = $this->plugin->generateOrderHash($mid, $orderId, $amount, $currency, $apiKey);

        $expectedPath = "/?payment={$mid}.{$orderId}.{$amount}.{$currency}";
        $expectedHash = hash_hmac('sha256', $expectedPath, $apiKey, false);

        $this->assertEquals($expectedHash, $hash);
        $this->assertNotEmpty($hash);
        $this->assertEquals(64, strlen($hash)); // SHA256 hex is 64 chars
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_process_payment_redirects_to_hpp()
    {
        $paymentOrder = PaymentOrder::create([
            'amount' => 100.00,
            'currency' => 'EGP',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'customer_phone' => '+201001234567',
        ]);

        $result = $this->plugin->processPayment($paymentOrder);

        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $result);

        $targetUrl = $result->getTargetUrl();
        $this->assertStringContainsString('https://checkout.kashier.io', $targetUrl);
        $this->assertStringContainsString('merchantId=MID-3552-454', $targetUrl);
        $this->assertStringContainsString('amount=100', $targetUrl);
        $this->assertStringContainsString('currency=EGP', $targetUrl);
        $this->assertStringContainsString('mode=test', $targetUrl);
        $this->assertStringContainsString('hash=', $targetUrl);
        $this->assertStringContainsString('merchantRedirect=', $targetUrl);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_process_payment_returns_error_view_without_config()
    {
        $this->paymentMethod->setSetting('merchant_id', '', false);
        $this->paymentMethod->setSetting('api_key_test', '', false);

        $plugin = new KashierPaymentPlugin($this->paymentMethod);

        $paymentOrder = PaymentOrder::create([
            'amount' => 100.00,
            'currency' => 'EGP',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
        ]);

        $result = $plugin->processPayment($paymentOrder);

        $this->assertInstanceOf(\Illuminate\View\View::class, $result);
        $this->assertEquals('payment-gateway::plugins.kashier-payment-error', $result->name());

        $viewData = $result->getData();
        $this->assertArrayHasKey('paymentOrder', $viewData);
        $this->assertArrayHasKey('paymentMethod', $viewData);
        $this->assertArrayHasKey('failureUrl', $viewData);
        $this->assertArrayHasKey('errorMessage', $viewData);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_handles_successful_callback()
    {
        $apiKey = '49c02cfa-8a4e-4120-8aa2-b154a6d08573';
        $orderCode = 'PO-123';

        $callbackData = [
            'paymentStatus' => 'SUCCESS',
            'orderId' => $orderCode,
            'transactionId' => 'txn_test_123',
            'kashierOrderId' => 'ko_test_123',
            'cardDataToken' => 'token_test_123',
            'maskedCard' => '****1234',
        ];

        // Generate valid signature
        $queryString = '';
        foreach ($callbackData as $key => $value) {
            if ($key === 'signature' || $key === 'mode') {
                continue;
            }
            $queryString .= '&'.$key.'='.$value;
        }
        $queryString = ltrim($queryString, '&');
        $callbackData['signature'] = hash_hmac('sha256', $queryString, $apiKey, false);

        $result = $this->plugin->handleCallback($callbackData);

        $this->assertTrue($result->success);
        $this->assertEquals($orderCode, $result->orderCode);
        $this->assertEquals('txn_test_123', $result->transactionId);
        $this->assertEquals('completed', $result->status);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_handles_failed_callback()
    {
        $apiKey = '49c02cfa-8a4e-4120-8aa2-b154a6d08573';
        $orderCode = 'PO-456';

        $callbackData = [
            'paymentStatus' => 'FAILURE',
            'orderId' => $orderCode,
            'transactionId' => 'txn_test_456',
        ];

        // Generate valid signature
        $queryString = '';
        foreach ($callbackData as $key => $value) {
            $queryString .= '&'.$key.'='.$value;
        }
        $queryString = ltrim($queryString, '&');
        $callbackData['signature'] = hash_hmac('sha256', $queryString, $apiKey, false);

        $result = $this->plugin->handleCallback($callbackData);

        $this->assertFalse($result->success);
        $this->assertEquals($orderCode, $result->orderCode);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_rejects_invalid_signature()
    {
        $callbackData = [
            'paymentStatus' => 'SUCCESS',
            'orderId' => 'PO-789',
            'transactionId' => 'txn_test_789',
            'signature' => 'invalid_signature_here',
        ];

        $result = $this->plugin->handleCallback($callbackData);

        $this->assertFalse($result->success);
        $this->assertEquals('PO-789', $result->orderCode);
        $this->assertStringContainsString('Invalid callback signature', $result->message);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_requires_order_code_in_callback()
    {
        $callbackData = [
            'paymentStatus' => 'SUCCESS',
            'transactionId' => 'txn_test_999',
            'signature' => 'some_signature',
        ];

        $result = $this->plugin->handleCallback($callbackData);

        $this->assertFalse($result->success);
        $this->assertEquals('unknown', $result->orderCode);
        $this->assertStringContainsString('required', strtolower($result->message));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_callback_rejects_missing_signature()
    {
        $callbackData = [
            'paymentStatus' => 'SUCCESS',
            'orderId' => 'PO-100',
            'transactionId' => 'txn_test_100',
        ];

        $result = $this->plugin->handleCallback($callbackData);

        $this->assertFalse($result->success);
        $this->assertEquals('PO-100', $result->orderCode);
        $this->assertStringContainsString('Invalid callback signature', $result->message);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_refund_returns_not_implemented()
    {
        $paymentOrder = PaymentOrder::create([
            'amount' => 100.00,
            'currency' => 'EGP',
            'customer_name' => 'Test User',
            'customer_email' => 'test@example.com',
        ]);

        $result = $this->plugin->refund($paymentOrder);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not yet implemented', $result->message);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_process_payment_uses_live_mode_url()
    {
        $this->paymentMethod->setSetting('test_mode', false, false);
        $plugin = new KashierPaymentPlugin($this->paymentMethod);

        $paymentOrder = PaymentOrder::create([
            'amount' => 50.00,
            'currency' => 'EGP',
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
        ]);

        $result = $plugin->processPayment($paymentOrder);

        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $result);
        $this->assertStringContainsString('mode=live', $result->getTargetUrl());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_process_payment_includes_display_language()
    {
        $this->paymentMethod->setSetting('display_language', 'ar', false);
        $plugin = new KashierPaymentPlugin($this->paymentMethod);

        $paymentOrder = PaymentOrder::create([
            'amount' => 50.00,
            'currency' => 'EGP',
            'customer_name' => 'Test User',
            'customer_email' => 'test@example.com',
        ]);

        $result = $plugin->processPayment($paymentOrder);

        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $result);
        $this->assertStringContainsString('display=ar', $result->getTargetUrl());
    }
}
