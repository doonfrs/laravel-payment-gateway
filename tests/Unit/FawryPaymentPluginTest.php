<?php

namespace Trinavo\PaymentGateway\Tests\Unit;

use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;
use Trinavo\PaymentGateway\Models\PaymentMethod;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Plugins\Fawry\FawryPaymentPlugin;
use Trinavo\PaymentGateway\Providers\PaymentGatewayServiceProvider;

class FawryPaymentPluginTest extends TestCase
{
    protected PaymentMethod $paymentMethod;

    protected FawryPaymentPlugin $plugin;

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
            'name' => 'fawry',
            'plugin_class' => FawryPaymentPlugin::class,
            'display_name' => 'Fawry Payment',
            'enabled' => true,
        ]);

        $this->paymentMethod->setSetting('merchant_code', 'test_merchant_code', false);
        $this->paymentMethod->setSetting('secure_key_test', 'test_secure_key_123', true);
        $this->paymentMethod->setSetting('secure_key_live', 'live_secure_key_456', true);
        $this->paymentMethod->setSetting('test_mode', true, false);
        $this->paymentMethod->setSetting('payment_expiry_hours', '24', false);

        $this->plugin = new FawryPaymentPlugin($this->paymentMethod);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_returns_correct_name()
    {
        $this->assertEquals('Fawry Payment Plugin', $this->plugin->getName());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_returns_correct_description()
    {
        $description = $this->plugin->getDescription();
        $this->assertStringContainsString('Fawry', $description);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_returns_configuration_fields()
    {
        $fields = $this->plugin->getConfigurationFields();

        $this->assertIsArray($fields);
        $this->assertNotEmpty($fields);

        $fieldNames = array_map(fn ($field) => $field->getName(), $fields);
        $this->assertContains('merchant_code', $fieldNames);
        $this->assertContains('secure_key_test', $fieldNames);
        $this->assertContains('secure_key_live', $fieldNames);
        $this->assertContains('test_mode', $fieldNames);
        $this->assertContains('payment_expiry_hours', $fieldNames);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_validates_configuration_in_test_mode()
    {
        $this->assertTrue($this->plugin->validateConfiguration());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_fails_validation_without_merchant_code()
    {
        $this->paymentMethod->setSetting('merchant_code', '', false);

        $plugin = new FawryPaymentPlugin($this->paymentMethod);
        $this->assertFalse($plugin->validateConfiguration());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_fails_validation_without_test_key()
    {
        $this->paymentMethod->setSetting('secure_key_test', '', false);
        $this->paymentMethod->setSetting('test_mode', true, false);

        $plugin = new FawryPaymentPlugin($this->paymentMethod);
        $this->assertFalse($plugin->validateConfiguration());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_validates_configuration_in_live_mode()
    {
        $this->paymentMethod->setSetting('test_mode', false, false);

        $plugin = new FawryPaymentPlugin($this->paymentMethod);
        $this->assertTrue($plugin->validateConfiguration());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_generates_correct_charge_signature()
    {
        $merchantCode = 'test_merchant';
        $merchantRefNum = 'PO-123';
        $secureKey = 'secret_key';

        $signature = $this->plugin->generateChargeSignature($merchantCode, $merchantRefNum, $secureKey);

        $expected = hash('sha256', $merchantCode.$merchantRefNum.$secureKey);
        $this->assertEquals($expected, $signature);
        $this->assertEquals(64, strlen($signature));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_processes_payment_and_shows_pending_view()
    {
        Http::fake([
            'atfawry.fawrystaging.com/*' => Http::response([
                'statusCode' => 200,
                'statusDescription' => 'Operation done successfully',
                'referenceNumber' => '773421563',
                'orderStatus' => 'PENDING',
            ], 200),
        ]);

        $paymentOrder = PaymentOrder::create([
            'amount' => 100.00,
            'currency' => 'EGP',
            'customer_name' => 'Ahmed Ali',
            'customer_email' => 'ahmed@example.com',
            'customer_phone' => '01001234567',
        ]);

        $result = $this->plugin->processPayment($paymentOrder);

        $this->assertInstanceOf(\Illuminate\View\View::class, $result);
        $this->assertEquals('payment-gateway::plugins.fawry-payment-pending', $result->name());

        $viewData = $result->getData();
        $this->assertEquals('773421563', $viewData['referenceNumber']);
        $this->assertArrayHasKey('expiryHours', $viewData);

        $paymentOrder->refresh();
        $this->assertEquals('773421563', $paymentOrder->payment_data['fawry_reference_number']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_processes_payment_returns_error_on_api_failure()
    {
        Http::fake([
            'atfawry.fawrystaging.com/*' => Http::response([
                'statusCode' => 9946,
                'statusDescription' => 'Invalid merchant code',
            ], 200),
        ]);

        $paymentOrder = PaymentOrder::create([
            'amount' => 100.00,
            'currency' => 'EGP',
            'customer_name' => 'Ahmed Ali',
            'customer_email' => 'ahmed@example.com',
        ]);

        $result = $this->plugin->processPayment($paymentOrder);

        $this->assertInstanceOf(\Illuminate\View\View::class, $result);
        $this->assertEquals('payment-gateway::plugins.fawry-payment-error', $result->name());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_handles_successful_webhook_callback()
    {
        $secureKey = 'test_secure_key_123';
        $fawryRefNumber = '773421563';
        $merchantRefNumber = 'PO-123';
        $paymentAmount = '100.00';
        $orderAmount = '100.00';
        $orderStatus = 'PAID';
        $paymentMethod = 'PayAtFawry';
        $paymentRefNumber = 'REF-123';

        $signature = hash('sha256',
            $fawryRefNumber.
            $merchantRefNumber.
            $paymentAmount.
            $orderAmount.
            $orderStatus.
            $paymentMethod.
            $paymentRefNumber.
            $secureKey
        );

        $result = $this->plugin->handleCallback([
            'fawryRefNumber' => $fawryRefNumber,
            'merchantRefNumber' => $merchantRefNumber,
            'paymentAmount' => $paymentAmount,
            'orderAmount' => $orderAmount,
            'orderStatus' => $orderStatus,
            'paymentMethod' => $paymentMethod,
            'paymentRefNumber' => $paymentRefNumber,
            'messageSignature' => $signature,
        ]);

        $this->assertTrue($result->success);
        $this->assertEquals('PO-123', $result->orderCode);
        $this->assertEquals($fawryRefNumber, $result->transactionId);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_handles_cancelled_webhook_callback()
    {
        $secureKey = 'test_secure_key_123';

        $signature = hash('sha256',
            '773421563'.
            'PO-456'.
            '100.00'.
            '100.00'.
            'CANCELLED'.
            'PayAtFawry'.
            ''.
            $secureKey
        );

        $result = $this->plugin->handleCallback([
            'fawryRefNumber' => '773421563',
            'merchantRefNumber' => 'PO-456',
            'paymentAmount' => '100.00',
            'orderAmount' => '100.00',
            'orderStatus' => 'CANCELLED',
            'paymentMethod' => 'PayAtFawry',
            'paymentRefNumber' => '',
            'messageSignature' => $signature,
        ]);

        $this->assertFalse($result->success);
        $this->assertEquals('PO-456', $result->orderCode);
        $this->assertTrue($result->isCancelled());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_rejects_invalid_webhook_signature()
    {
        $result = $this->plugin->handleCallback([
            'fawryRefNumber' => '773421563',
            'merchantRefNumber' => 'PO-789',
            'paymentAmount' => '100.00',
            'orderAmount' => '100.00',
            'orderStatus' => 'PAID',
            'paymentMethod' => 'PayAtFawry',
            'paymentRefNumber' => 'REF-789',
            'messageSignature' => 'invalid_signature',
        ]);

        $this->assertFalse($result->success);
        $this->assertEquals('PO-789', $result->orderCode);
        $this->assertStringContainsString('Invalid callback signature', $result->message);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_requires_order_code_in_callback()
    {
        $result = $this->plugin->handleCallback([
            'fawryRefNumber' => '773421563',
            'orderStatus' => 'PAID',
        ]);

        $this->assertFalse($result->success);
        $this->assertEquals('unknown', $result->orderCode);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_refund_succeeds()
    {
        Http::fake([
            'atfawry.fawrystaging.com/*' => Http::response([
                'statusCode' => 200,
                'statusDescription' => 'Operation done successfully',
                'refundId' => 'ref_123',
            ], 200),
        ]);

        $paymentOrder = PaymentOrder::create([
            'amount' => 100.00,
            'currency' => 'EGP',
            'customer_name' => 'Test User',
            'customer_email' => 'test@example.com',
            'payment_data' => [
                'fawry_reference_number' => '773421563',
            ],
        ]);

        $result = $this->plugin->refund($paymentOrder);

        $this->assertTrue($result->success);
        $this->assertEquals('ref_123', $result->refundTransactionId);
        $this->assertEquals('773421563', $result->originalTransactionId);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function plugin_refund_fails_without_reference()
    {
        $paymentOrder = PaymentOrder::create([
            'amount' => 100.00,
            'currency' => 'EGP',
            'customer_name' => 'Test User',
            'customer_email' => 'test@example.com',
            'payment_data' => [],
        ]);

        $result = $this->plugin->refund($paymentOrder);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not found', $result->message);
    }
}
