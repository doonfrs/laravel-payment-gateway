# Creating Custom Payment Plugins

This guide shows how to create payment gateway plugins using the `PaymentPluginInterface`.

## Plugin Interface

All payment plugins must implement this interface:

```php
<?php

namespace Trinavo\PaymentGateway\Contracts;

use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Models\PaymentMethod;

interface PaymentPluginInterface
{
    public function __construct(PaymentMethod $paymentMethod);
    
    // Basic plugin information
    public function getName(): string;
    public function getDescription(): string;
    public function getVersion(): string;
    
    // Configuration
    public function getConfigurationFields(): array;
    public function validateConfiguration(): bool;
    
    // Payment processing
    public function processPayment(PaymentOrder $paymentOrder);
    public function handleCallback(array $callbackData): \Trinavo\PaymentGateway\Models\CallbackResponse;
    
    // UI customization
    public function getPaymentView(): ?string;
    public function getCallbackUrl(): string;
}
```

## Basic Plugin Structure

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

    public function __construct(PaymentMethod $paymentMethod)
    {
        $this->paymentMethod = $paymentMethod;
    }

    public function getName(): string
    {
        return 'Example Payment Gateway';
    }

    public function getDescription(): string
    {
        return 'Example payment gateway plugin';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getConfigurationFields(): array
    {
        return [
            [
                'name' => 'api_key',
                'label' => 'API Key',
                'type' => 'text',
                'required' => true,
                'encrypted' => false,
            ],
            [
                'name' => 'secret_key',
                'label' => 'Secret Key',
                'type' => 'password',
                'required' => true,
                'encrypted' => true,
            ],
        ];
    }

    public function validateConfiguration(): bool
    {
        $apiKey = $this->paymentMethod->getSetting('api_key');
        $secretKey = $this->paymentMethod->getSetting('secret_key');
        
        return !empty($apiKey) && !empty($secretKey);
    }

    public function processPayment(PaymentOrder $paymentOrder)
    {
        // Your payment processing logic here
        
        // For redirect-based payments:
        return redirect('https://gateway.example.com/pay/123');
        
        // For direct processing:
        // $paymentOrder->markAsCompleted(['transaction_id' => 'txn_123']);
        // return redirect()->route('payment-gateway.success', ['order' => $paymentOrder->order_code]);
    }

    public function handleCallback(array $callbackData): CallbackResponse
    {
        // Process webhook/callback from payment gateway
        
        return CallbackResponse::success(
            orderCode: $callbackData['order_id'],
            transactionId: $callbackData['transaction_id'],
            message: 'Payment completed successfully',
            additionalData: $callbackData
        );
    }


    public function getPaymentView(): ?string
    {
        return null; // Use default redirect behavior
    }

    public function getCallbackUrl(): string
    {
        return route('payment-gateway.callback', ['plugin' => 'example']);
    }
}
```

## Dummy Plugin Example

Look at the included `DummyPaymentPlugin` for a working example:

```php
// Located at: src/Plugins/Dummy/DummyPaymentPlugin.php

public function processPayment(PaymentOrder $paymentOrder)
{
    // Creates a test page with success/failure buttons
    return view('payment-gateway::dummy-payment', compact('paymentOrder'));
}

public function handleCallback(array $callbackData): CallbackResponse
{
    $action = $callbackData['action'] ?? 'success';
    
    if ($action === 'success') {
        return CallbackResponse::success(
            orderCode: $callbackData['order_code'],
            transactionId: 'dummy_' . uniqid(),
            message: 'Dummy payment completed successfully',
            additionalData: $callbackData
        );
    }
    
    return CallbackResponse::failure(
        orderCode: $callbackData['order_code'],
        message: 'Dummy payment failed',
        additionalData: $callbackData
    );
}
```

## Register Your Plugin

1. **Add to configuration** (though not currently used by code):

```php
// config/payment-gateway.php
'plugins' => [
    \Trinavo\PaymentGateway\Plugins\Dummy\DummyPaymentPlugin::class,
    \App\PaymentPlugins\ExamplePaymentPlugin::class,
],
```

2. **Create payment method record**:

```php
PaymentMethod::create([
    'name' => 'example',
    'plugin_class' => \App\PaymentPlugins\ExamplePaymentPlugin::class,
    'display_name' => 'Example Gateway',
    'enabled' => true,
    'sort_order' => 1,
]);
```

3. **Configure settings**:

```php
$paymentMethod->setSetting('api_key', 'your_api_key', false);
$paymentMethod->setSetting('secret_key', 'your_secret_key', true);
```

---

**Next:** [Views & Layouts](views-layouts.md) →
