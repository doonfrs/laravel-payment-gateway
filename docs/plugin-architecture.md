# Plugin Architecture

This guide explains the plugin architecture of the Laravel Payment Gateway package, how plugins work, and the design patterns that make the system extensible and maintainable.

## Overview

The Laravel Payment Gateway uses a plugin-based architecture that allows you to integrate with any payment provider by implementing a standardized interface. This design provides:

- **Separation of Concerns** - Each payment gateway has its own isolated plugin
- **Extensibility** - Easy to add new payment gateways without modifying core code
- **Consistency** - All plugins follow the same interface and patterns
- **Maintainability** - Updates to one gateway don't affect others
- **Testability** - Each plugin can be tested independently

## Core Components

### 1. PaymentPluginInterface

The heart of the plugin system is the `PaymentPluginInterface` that all plugins must implement:

```php
<?php

namespace Trinavo\PaymentGateway\Contracts;

use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Models\PaymentMethod;

interface PaymentPluginInterface
{
    /**
     * Constructor receives the payment method configuration
     */
    public function __construct(PaymentMethod $paymentMethod);
    
    /**
     * Basic plugin information
     */
    public function getName(): string;
    public function getDescription(): string;
    public function getVersion(): string;
    
    /**
     * Configuration management
     */
    public function getConfigurationFields(): array;
    public function validateConfiguration(): bool;
    
    /**
     * Core payment processing
     */
    public function processPayment(PaymentOrder $paymentOrder);
    public function handleCallback(array $callbackData): \Trinavo\PaymentGateway\Models\CallbackResponse;
    
    /**
     * Optional advanced features
     */
    public function supportsRefunds(): bool;
    public function processRefund(PaymentOrder $paymentOrder, float $amount): array;
    public function getPaymentStatus(PaymentOrder $paymentOrder): string;
    
    /**
     * UI customization
     */
    public function getPaymentView(): ?string;
    public function getCallbackUrl(): string;
}
```

### 2. Plugin Registration

Plugins are registered in the configuration file:

```php
// config/payment-gateway.php
'plugins' => [
    'dummy' => \Trinavo\PaymentGateway\Plugins\Dummy\DummyPaymentPlugin::class,
    'stripe' => \App\PaymentPlugins\StripePaymentPlugin::class,
    'paypal' => \App\PaymentPlugins\PayPalPaymentPlugin::class,
    'razorpay' => \App\PaymentPlugins\RazorpayPaymentPlugin::class,
],
```

### 3. Plugin Instantiation

Plugins are instantiated through the PaymentMethod model:

```php
$paymentMethod = PaymentMethod::where('name', 'stripe')->first();
$plugin = $paymentMethod->getPluginInstance();

// The PaymentMethod model handles:
// 1. Loading the plugin class
// 2. Passing configuration to the constructor
// 3. Caching the instance for performance
```

## Plugin Lifecycle

### 1. Registration Phase

```php
// 1. Plugin class is registered in config
'plugins' => [
    'stripe' => \App\PaymentPlugins\StripePaymentPlugin::class,
],

// 2. PaymentMethod record is created
PaymentMethod::create([
    'name' => 'stripe',
    'plugin_class' => \App\PaymentPlugins\StripePaymentPlugin::class,
    'display_name' => 'Credit Card',
    'enabled' => true,
]);

// 3. Configuration settings are added
$paymentMethod->setSetting('secret_key', 'sk_test_...', true);
$paymentMethod->setSetting('publishable_key', 'pk_test_...', false);
```

### 2. Configuration Phase

```php
// Plugin defines its configuration requirements
public function getConfigurationFields(): array
{
    return [
        [
            'name' => 'secret_key',
            'label' => 'Secret Key',
            'type' => 'password',
            'required' => true,
            'encrypted' => true,
        ],
        [
            'name' => 'publishable_key',
            'label' => 'Publishable Key',
            'type' => 'text',
            'required' => true,
            'encrypted' => false,
        ],
    ];
}

// Configuration is validated
public function validateConfiguration(): bool
{
    $secretKey = $this->paymentMethod->getSetting('secret_key');
    $publishableKey = $this->paymentMethod->getSetting('publishable_key');
    
    return !empty($secretKey) && 
           !empty($publishableKey) && 
           str_starts_with($secretKey, 'sk_') &&
           str_starts_with($publishableKey, 'pk_');
}
```

### 3. Payment Processing Phase

```php
// 1. Customer initiates payment
$paymentOrder = PaymentGateway::createPaymentOrder([...]);

// 2. Customer selects payment method
$paymentMethod = PaymentMethod::find($selectedMethodId);

// 3. Plugin processes the payment
$plugin = $paymentMethod->getPluginInstance();
$result = $plugin->processPayment($paymentOrder);

// 4. Plugin handles callbacks from gateway
$callbackResult = $plugin->handleCallback($webhookData);
```

## Plugin Implementation Patterns

### 1. Basic Plugin Structure

```php
<?php

namespace App\PaymentPlugins;

use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\CallbackResponse;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Models\PaymentMethod;

class ExamplePaymentPlugin implements PaymentPluginInterface
{
    protected PaymentMethod $paymentMethod;
    protected array $config;

    public function __construct(PaymentMethod $paymentMethod)
    {
        $this->paymentMethod = $paymentMethod;
        $this->config = $this->loadConfiguration();
        $this->initializeGateway();
    }

    protected function loadConfiguration(): array
    {
        return [
            'api_key' => $this->paymentMethod->getSetting('api_key'),
            'secret_key' => $this->paymentMethod->getSetting('secret_key'),
            'environment' => $this->paymentMethod->getSetting('environment', 'sandbox'),
        ];
    }

    protected function initializeGateway(): void
    {
        // Initialize the payment gateway SDK
        // Set API credentials, environment, etc.
    }

    // Implement interface methods...
}
```

### 2. Configuration Management Pattern

```php
public function getConfigurationFields(): array
{
    return [
        // Basic credentials
        [
            'name' => 'api_key',
            'label' => 'API Key',
            'type' => 'text',
            'required' => true,
            'encrypted' => false,
            'description' => 'Your API key from the gateway dashboard',
        ],
        [
            'name' => 'secret_key',
            'label' => 'Secret Key',
            'type' => 'password',
            'required' => true,
            'encrypted' => true,
            'description' => 'Your secret key (will be encrypted)',
        ],
        
        // Environment settings
        [
            'name' => 'environment',
            'label' => 'Environment',
            'type' => 'select',
            'options' => [
                'sandbox' => 'Sandbox (Testing)',
                'production' => 'Production (Live)',
            ],
            'default' => 'sandbox',
            'required' => true,
        ],
        
        // Optional webhook settings
        [
            'name' => 'webhook_secret',
            'label' => 'Webhook Secret',
            'type' => 'password',
            'required' => false,
            'encrypted' => true,
            'description' => 'Secret for webhook verification (optional)',
        ],
        
        // Feature flags
        [
            'name' => 'enable_refunds',
            'label' => 'Enable Refunds',
            'type' => 'checkbox',
            'default' => true,
            'description' => 'Allow refunds through this gateway',
        ],
    ];
}
```

### 3. Payment Processing Pattern

```php
public function processPayment(PaymentOrder $paymentOrder)
{
    try {
        // 1. Validate order
        $this->validatePaymentOrder($paymentOrder);
        
        // 2. Prepare payment data
        $paymentData = $this->preparePaymentData($paymentOrder);
        
        // 3. Create payment with gateway
        $gatewayResponse = $this->createGatewayPayment($paymentData);
        
        // 4. Handle response
        return $this->handleGatewayResponse($gatewayResponse, $paymentOrder);
        
    } catch (\Exception $e) {
        // 5. Handle errors
        return $this->handlePaymentError($e, $paymentOrder);
    }
}

protected function validatePaymentOrder(PaymentOrder $paymentOrder): void
{
    if ($paymentOrder->amount <= 0) {
        throw new \InvalidArgumentException('Payment amount must be greater than zero');
    }
    
    if (empty($paymentOrder->customer_email)) {
        throw new \InvalidArgumentException('Customer email is required');
    }
    
    // Gateway-specific validations
    if ($this->config['environment'] === 'production' && $paymentOrder->amount < 0.50) {
        throw new \InvalidArgumentException('Minimum payment amount is $0.50 in production');
    }
}

protected function preparePaymentData(PaymentOrder $paymentOrder): array
{
    return [
        'amount' => $paymentOrder->amount * 100, // Convert to cents
        'currency' => strtolower($paymentOrder->currency),
        'description' => $paymentOrder->description ?: 'Payment',
        'customer' => [
            'name' => $paymentOrder->customer_name,
            'email' => $paymentOrder->customer_email,
            'phone' => $paymentOrder->customer_phone,
        ],
        'metadata' => [
            'order_code' => $paymentOrder->order_code,
            'internal_id' => $paymentOrder->id,
        ],
        'success_url' => route('payment-gateway.success', ['order' => $paymentOrder->order_code]),
        'cancel_url' => route('payment-gateway.failure', ['order' => $paymentOrder->order_code]),
    ];
}
```

### 4. Callback Handling Pattern

```php
public function handleCallback(array $callbackData): CallbackResponse
{
    try {
        // 1. Verify callback authenticity
        $this->verifyCallback($callbackData);
        
        // 2. Extract payment information
        $paymentInfo = $this->extractPaymentInfo($callbackData);
        
        // 3. Determine payment status
        $status = $this->determinePaymentStatus($callbackData);
        
        // 4. Return standardized response
        if ($status === 'completed') {
            return CallbackResponse::success(
                orderCode: $paymentInfo['order_code'],
                transactionId: $paymentInfo['transaction_id'],
                message: 'Payment completed successfully',
                additionalData: $paymentInfo
            );
        } elseif ($status === 'pending') {
            return CallbackResponse::pending(
                orderCode: $paymentInfo['order_code'],
                transactionId: $paymentInfo['transaction_id'],
                message: 'Payment is being processed',
                additionalData: $paymentInfo
            );
        } else {
            return CallbackResponse::failure(
                orderCode: $paymentInfo['order_code'],
                message: 'Payment failed',
                status: $status,
                additionalData: $paymentInfo
            );
        }
        
    } catch (\Exception $e) {
        return CallbackResponse::failure(
            orderCode: $callbackData['order_code'] ?? 'unknown',
            message: 'Callback processing failed: ' . $e->getMessage()
        );
    }
}

protected function verifyCallback(array $callbackData): void
{
    $webhookSecret = $this->paymentMethod->getSetting('webhook_secret');
    
    if (!$webhookSecret) {
        return; // Skip verification if no secret configured
    }
    
    $signature = request()->header('X-Signature');
    $payload = request()->getContent();
    
    $expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);
    
    if (!hash_equals($expectedSignature, $signature)) {
        throw new \Exception('Invalid webhook signature');
    }
}
```

## Advanced Plugin Features

### 1. Custom Payment Views

```php
public function getPaymentView(): ?string
{
    // Return null for redirect-based payments
    if ($this->isRedirectBased()) {
        return null;
    }
    
    // Return custom view for embedded payments
    return 'payment-plugins.' . $this->paymentMethod->name;
}

// In the view file: resources/views/payment-plugins/stripe.blade.php
@extends('payment-gateway::layouts.payment-gateway')

@section('content')
<div class="payment-form">
    <div id="card-element">
        <!-- Stripe Elements will be inserted here -->
    </div>
    
    <button id="submit-payment" class="btn btn-primary">
        Pay {{ $paymentOrder->formatted_amount }}
    </button>
</div>

<script src="https://js.stripe.com/v3/"></script>
<script>
    const stripe = Stripe('{{ $paymentMethod->getSetting("publishable_key") }}');
    // Custom payment form logic
</script>
@endsection
```

### 2. Refund Support

```php
public function supportsRefunds(): bool
{
    return $this->paymentMethod->getSetting('enable_refunds', true);
}

public function processRefund(PaymentOrder $paymentOrder, float $amount): array
{
    try {
        // 1. Validate refund request
        $this->validateRefundRequest($paymentOrder, $amount);
        
        // 2. Process refund with gateway
        $refundResponse = $this->processGatewayRefund($paymentOrder, $amount);
        
        // 3. Return standardized response
        return [
            'success' => true,
            'refund_id' => $refundResponse['id'],
            'amount' => $amount,
            'status' => $refundResponse['status'],
            'gateway_response' => $refundResponse,
        ];
        
    } catch (\Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
        ];
    }
}
```

### 3. Status Checking

```php
public function getPaymentStatus(PaymentOrder $paymentOrder): string
{
    try {
        $transactionId = $paymentOrder->transaction_id;
        
        if (!$transactionId) {
            return 'unknown';
        }
        
        $gatewayStatus = $this->fetchGatewayStatus($transactionId);
        
        return $this->mapGatewayStatus($gatewayStatus);
        
    } catch (\Exception $e) {
        Log::error('Failed to fetch payment status', [
            'order_code' => $paymentOrder->order_code,
            'error' => $e->getMessage(),
        ]);
        
        return 'unknown';
    }
}

protected function mapGatewayStatus(string $gatewayStatus): string
{
    return match($gatewayStatus) {
        'succeeded', 'paid', 'completed' => 'completed',
        'pending', 'processing' => 'processing',
        'failed', 'declined', 'error' => 'failed',
        'cancelled', 'canceled' => 'cancelled',
        default => 'unknown',
    };
}
```

## Plugin Testing Strategies

### 1. Unit Testing

```php
<?php

namespace Tests\Unit\PaymentPlugins;

use Tests\TestCase;
use App\PaymentPlugins\StripePaymentPlugin;
use Trinavo\PaymentGateway\Models\PaymentMethod;
use Trinavo\PaymentGateway\Models\PaymentOrder;

class StripePaymentPluginTest extends TestCase
{
    protected PaymentMethod $paymentMethod;
    protected StripePaymentPlugin $plugin;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->paymentMethod = PaymentMethod::factory()->create([
            'name' => 'stripe',
            'plugin_class' => StripePaymentPlugin::class,
        ]);
        
        $this->paymentMethod->setSettings([
            'secret_key' => ['value' => 'sk_test_123', 'encrypted' => true],
            'publishable_key' => ['value' => 'pk_test_123', 'encrypted' => false],
        ]);
        
        $this->plugin = new StripePaymentPlugin($this->paymentMethod);
    }

    public function test_plugin_validates_configuration()
    {
        $this->assertTrue($this->plugin->validateConfiguration());
    }

    public function test_plugin_returns_configuration_fields()
    {
        $fields = $this->plugin->getConfigurationFields();
        
        $this->assertIsArray($fields);
        $this->assertNotEmpty($fields);
        
        $fieldNames = array_column($fields, 'name');
        $this->assertContains('secret_key', $fieldNames);
        $this->assertContains('publishable_key', $fieldNames);
    }

    public function test_plugin_handles_callback()
    {
        $callbackData = [
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_123',
                    'payment_status' => 'paid',
                    'metadata' => ['order_code' => 'PO-123'],
                ]
            ]
        ];
        
        $result = $this->plugin->handleCallback($callbackData);
        
        $this->assertTrue($result['success']);
        $this->assertEquals('PO-123', $result['order_code']);
    }
}
```

### 2. Integration Testing

```php
public function test_complete_payment_flow()
{
    // Mock external API calls
    Http::fake([
        'api.stripe.com/*' => Http::response([
            'id' => 'cs_test_123',
            'url' => 'https://checkout.stripe.com/pay/cs_test_123',
            'payment_status' => 'unpaid',
        ], 200),
    ]);
    
    $order = PaymentOrder::factory()->create();
    
    $response = $this->plugin->processPayment($order);
    
    $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
    $this->assertStringContains('checkout.stripe.com', $response->getTargetUrl());
}
```

### 3. Mock Plugin for Testing

```php
<?php

namespace App\PaymentPlugins;

use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Models\PaymentMethod;

class MockPaymentPlugin implements PaymentPluginInterface
{
    protected PaymentMethod $paymentMethod;
    protected bool $shouldSucceed;

    public function __construct(PaymentMethod $paymentMethod)
    {
        $this->paymentMethod = $paymentMethod;
        $this->shouldSucceed = $paymentMethod->getSetting('mock_success', true);
    }

    public function processPayment(PaymentOrder $paymentOrder)
    {
        if ($this->shouldSucceed) {
            $paymentOrder->markAsCompleted([
                'transaction_id' => 'mock_txn_' . uniqid(),
                'mock_payment' => true,
            ]);
            
            return redirect()->route('payment-gateway.success', [
                'order' => $paymentOrder->order_code
            ]);
        } else {
            $paymentOrder->markAsFailed([
                'error' => 'Mock payment failure',
                'mock_payment' => true,
            ]);
            
            return redirect()->route('payment-gateway.failure', [
                'order' => $paymentOrder->order_code
            ]);
        }
    }

    // Implement other interface methods...
}
```

## Plugin Best Practices

### 1. Error Handling

```php
protected function handlePaymentError(\Exception $e, PaymentOrder $paymentOrder)
{
    // Log the error with context
    Log::error('Payment processing failed', [
        'plugin' => static::class,
        'order_code' => $paymentOrder->order_code,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    
    // Mark order as failed with error details
    $paymentOrder->markAsFailed([
        'error' => $e->getMessage(),
        'error_code' => $e->getCode(),
        'plugin_error' => true,
    ]);
    
    // Return user-friendly error response
    return redirect()->route('payment-gateway.failure', [
        'order' => $paymentOrder->order_code,
        'error' => 'payment_processing_failed',
    ]);
}
```

### 2. Configuration Validation

```php
public function validateConfiguration(): bool
{
    try {
        // Check required settings
        $requiredSettings = ['api_key', 'secret_key'];
        foreach ($requiredSettings as $setting) {
            if (empty($this->paymentMethod->getSetting($setting))) {
                return false;
            }
        }
        
        // Test API connection
        $this->testApiConnection();
        
        return true;
        
    } catch (\Exception $e) {
        Log::warning('Plugin configuration validation failed', [
            'plugin' => static::class,
            'error' => $e->getMessage(),
        ]);
        
        return false;
    }
}

protected function testApiConnection(): void
{
    // Make a simple API call to verify credentials
    $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . $this->config['api_key'],
    ])->get($this->getApiEndpoint() . '/test');
    
    if (!$response->successful()) {
        throw new \Exception('API connection test failed');
    }
}
```

### 3. Security Considerations

```php
protected function sanitizeCallbackData(array $data): array
{
    // Remove potentially dangerous data
    unset($data['__proto__'], $data['constructor']);
    
    // Validate data types
    $sanitized = [];
    foreach ($data as $key => $value) {
        if (is_string($key) && (is_string($value) || is_numeric($value) || is_array($value))) {
            $sanitized[$key] = $value;
        }
    }
    
    return $sanitized;
}

protected function validateWebhookSource(): void
{
    $allowedIps = $this->getGatewayIpWhitelist();
    $clientIp = request()->ip();
    
    if (!empty($allowedIps) && !in_array($clientIp, $allowedIps)) {
        throw new \Exception('Webhook from unauthorized IP: ' . $clientIp);
    }
}
```

## Plugin Documentation Template

When creating a new plugin, document it thoroughly:

```markdown
# [Gateway Name] Payment Plugin

## Overview
Brief description of the payment gateway and what it supports.

## Configuration
List of required and optional configuration fields:

- `api_key` (required) - Your API key from the gateway
- `secret_key` (required, encrypted) - Your secret key
- `environment` (optional) - sandbox or production

## Features
- ✅ Payment processing
- ✅ Webhook callbacks
- ✅ Refunds
- ❌ Recurring payments
- ❌ Multi-party payments

## Testing
Instructions for testing the plugin in sandbox mode.

## Production Setup
Steps to configure for production use.

## Troubleshooting
Common issues and solutions.
```

---

**Next:** [Troubleshooting](troubleshooting/common-issues.md) →
