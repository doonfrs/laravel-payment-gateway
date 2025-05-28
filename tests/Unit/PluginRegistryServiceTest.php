<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Trinavo\PaymentGateway\Services\PluginRegistryService;

class PluginRegistryServiceTest extends TestCase
{
    protected PluginRegistryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PluginRegistryService;
    }

    public function test_generates_plugin_key_from_class_name()
    {
        $testCases = [
            'DummyPaymentPlugin' => 'dummy_payment',
            'StripePaymentPlugin' => 'stripe_payment',
            'PayPalPlugin' => 'pay_pal',
            'MoyasarPaymentGateway' => 'moyasar_payment',
            'SimplePlugin' => 'simple',
        ];

        foreach ($testCases as $className => $expectedKey) {
            $result = $this->service->getPluginKey("App\\PaymentPlugins\\{$className}");
            $this->assertEquals($expectedKey, $result, "Failed for class: {$className}");
        }
    }

    public function test_normalizes_index_based_plugin_array()
    {
        // Mock config
        $plugins = [
            'App\\PaymentPlugins\\DummyPaymentPlugin',
            'App\\PaymentPlugins\\StripePaymentPlugin',
        ];

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('normalizePluginArray');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $plugins);

        $expected = [
            'dummy_payment' => 'App\\PaymentPlugins\\DummyPaymentPlugin',
            'stripe_payment' => 'App\\PaymentPlugins\\StripePaymentPlugin',
        ];

        $this->assertEquals($expected, $result);
    }

    public function test_normalizes_key_value_plugin_array()
    {
        // Mock config (legacy format)
        $plugins = [
            'dummy' => 'App\\PaymentPlugins\\DummyPaymentPlugin',
            'stripe' => 'App\\PaymentPlugins\\StripePaymentPlugin',
        ];

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('normalizePluginArray');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $plugins);

        $expected = [
            'dummy' => 'App\\PaymentPlugins\\DummyPaymentPlugin',
            'stripe' => 'App\\PaymentPlugins\\StripePaymentPlugin',
        ];

        $this->assertEquals($expected, $result);
    }

    public function test_normalizes_mixed_plugin_array()
    {
        // Mock config (mixed format)
        $plugins = [
            'custom_key' => 'App\\PaymentPlugins\\CustomPlugin',
            'App\\PaymentPlugins\\AutoPlugin',
        ];

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('normalizePluginArray');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $plugins);

        $expected = [
            'custom_key' => 'App\\PaymentPlugins\\CustomPlugin',
            'auto' => 'App\\PaymentPlugins\\AutoPlugin',
        ];

        $this->assertEquals($expected, $result);
    }

    public function test_finds_plugin_key_by_class()
    {
        $plugins = [
            'dummy' => 'App\\PaymentPlugins\\DummyPaymentPlugin',
            'stripe' => 'App\\PaymentPlugins\\StripePaymentPlugin',
        ];

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('normalizePluginArray');
        $method->setAccessible(true);

        // Mock the getRegisteredPlugins method
        $serviceStub = $this->createPartialMock(PluginRegistryService::class, ['getRegisteredPlugins']);
        $serviceStub->method('getRegisteredPlugins')->willReturn($plugins);

        $result = $serviceStub->findPluginKeyByClass('App\\PaymentPlugins\\StripePaymentPlugin');
        $this->assertEquals('stripe', $result);

        $result = $serviceStub->findPluginKeyByClass('App\\PaymentPlugins\\NonExistentPlugin');
        $this->assertNull($result);
    }

    public function test_removes_common_suffixes()
    {
        $testCases = [
            'StripePaymentPlugin' => 'stripe',
            'PayPalPlugin' => 'pay_pal',
            'MoyasarPaymentGateway' => 'moyasar',
            'SimpleGateway' => 'simple',
            'TestPayment' => 'test_payment', // Should not remove 'Payment' if not at end
        ];

        foreach ($testCases as $className => $expectedKey) {
            $result = $this->service->getPluginKey("App\\PaymentPlugins\\{$className}");
            $this->assertEquals($expectedKey, $result, "Failed for class: {$className}");
        }
    }
}
