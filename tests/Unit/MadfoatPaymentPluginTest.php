<?php

namespace Trinavo\PaymentGateway\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Trinavo\PaymentGateway\Models\PaymentMethod;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Plugins\Madfoat\MadfoatPaymentPlugin;
use Trinavo\PaymentGateway\Providers\PaymentGatewayServiceProvider;

class MadfoatPaymentPluginTest extends TestCase
{
    protected PaymentMethod $paymentMethod;

    protected MadfoatPaymentPlugin $plugin;

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
            'name' => 'madfoat',
            'plugin_class' => MadfoatPaymentPlugin::class,
            'display_name' => 'Madfoat (eFAWATEERcom)',
            'enabled' => true,
        ]);

        $this->paymentMethod->setSetting('biller_code', '12345', false);
        $this->paymentMethod->setSetting('service_type', 'Sales', false);
        $this->paymentMethod->setSetting('bill_expiry_days', '7', false);

        $this->plugin = new MadfoatPaymentPlugin($this->paymentMethod);
    }

    public function test_plugin_returns_correct_name()
    {
        $this->assertEquals('Madfoat (eFAWATEERcom)', $this->plugin->getName());
    }

    public function test_plugin_returns_correct_description()
    {
        $description = $this->plugin->getDescription();
        $this->assertStringContainsString('eFAWATEERcom', $description);
        $this->assertStringContainsString('bill', $description);
    }

    public function test_plugin_has_configuration_fields()
    {
        $fields = $this->plugin->getConfigurationFields();
        $this->assertNotEmpty($fields);

        $fieldNames = array_map(fn ($f) => $f->getName(), $fields);
        $this->assertContains('biller_code', $fieldNames);
        $this->assertContains('service_type', $fieldNames);
        $this->assertContains('bill_expiry_days', $fieldNames);
        $this->assertContains('instructions', $fieldNames);
        $this->assertContains('allowed_ips', $fieldNames);
        $this->assertContains('log_channel', $fieldNames);
    }

    public function test_plugin_validates_configuration_with_required_fields()
    {
        $this->assertTrue($this->plugin->validateConfiguration());
    }

    public function test_plugin_fails_validation_without_biller_code()
    {
        $method = PaymentMethod::create([
            'name' => 'madfoat-invalid',
            'plugin_class' => MadfoatPaymentPlugin::class,
            'display_name' => 'Madfoat Invalid',
            'enabled' => true,
        ]);
        $method->setSetting('service_type', 'Sales', false);

        $plugin = new MadfoatPaymentPlugin($method);
        $this->assertFalse($plugin->validateConfiguration());
    }

    public function test_plugin_supports_inbound_requests()
    {
        $this->assertTrue($this->plugin->supportsInboundRequests());
    }

    public function test_refund_returns_failure()
    {
        $paymentOrder = PaymentOrder::create([
            'order_code' => 'TEST-001',
            'amount' => 25.00,
            'currency' => 'JOD',
            'status' => 'completed',
        ]);

        $refundResponse = $this->plugin->refund($paymentOrder);
        $this->assertFalse($refundResponse->success);
        $this->assertStringContainsString('not supported', $refundResponse->message);
    }

    public function test_handle_inbound_request_dispatches_unknown_action()
    {
        $response = $this->plugin->handleInboundRequest('unknown-action', []);
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function test_ip_allowed_when_no_whitelist_configured()
    {
        // No allowed_ips setting = allow all
        $method = new \ReflectionMethod($this->plugin, 'isIpAllowed');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($this->plugin, '1.2.3.4'));
    }

    public function test_ip_blocked_when_whitelist_configured()
    {
        $this->paymentMethod->setSetting('allowed_ips', '10.0.0.1,10.0.0.2', false);
        $plugin = new MadfoatPaymentPlugin($this->paymentMethod);

        $method = new \ReflectionMethod($plugin, 'isIpAllowed');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($plugin, '10.0.0.1'));
        $this->assertTrue($method->invoke($plugin, '10.0.0.2'));
        $this->assertFalse($method->invoke($plugin, '10.0.0.3'));
    }
}
