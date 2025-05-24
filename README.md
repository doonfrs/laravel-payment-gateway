# Laravel Payment Gateway

A powerful and flexible payment gateway package for Laravel applications that supports multiple payment providers through a plugin-based architecture.

## Features

- **Multiple Payment Gateways**: Support for multiple payment gateways through plugins
- **Payment Order Management**: Complete payment order lifecycle management
- **Flexible Configuration**: Key-value settings for each payment method
- **Callback Handling**: Unified callback handling for external payment gateways
- **Beautiful UI**: Modern, responsive payment pages with Tailwind CSS
- **Plugin Architecture**: Easy to extend with new payment providers
- **Dummy Plugin**: Built-in testing plugin for development
- **Multi-language Support**: Built-in support for English and Arabic with easy extensibility
- **Customizable Layout**: Publishable views and layout that developers can modify

## Installation

```bash
composer require trinavo/laravel-payment-gateway
```

## Configuration

### 1. Publish the Configuration

```bash
php artisan vendor:publish --provider="Trinavo\PaymentGateway\PaymentGatewayServiceProvider" --tag="config"
```

### 2. Publish and Run Migrations

```bash
php artisan vendor:publish --provider="Trinavo\PaymentGateway\PaymentGatewayServiceProvider" --tag="migrations"
php artisan migrate
```

### 3. Publish Views (Optional)

```bash
php artisan vendor:publish --provider="Trinavo\PaymentGateway\PaymentGatewayServiceProvider" --tag="views"
```

This will publish the views to `resources/views/vendor/payment-gateway/` where you can customize the layout, styling, and content.

### 4. Seed Payment Methods (Optional)

```bash
php artisan db:seed --class="Database\Seeders\PaymentMethodSeeder"
```

## Usage

### Creating a Payment Order

```php
use Trinavo\PaymentGateway\Facades\PaymentGateway;

$paymentOrder = PaymentGateway::createPaymentOrder([
    'amount' => 100.00,
    'currency' => 'USD',
    'customer_name' => 'John Doe',
    'customer_email' => 'john@example.com',
    'description' => 'Order #12345',
    'success_callback' => '$order->markAsPaid();', // PHP code to execute on success
    'failure_callback' => '$order->markAsFailed();', // PHP code to execute on failure
    'success_url' => 'https://yoursite.com/success',
    'failure_url' => 'https://yoursite.com/failed',
]);

// Get payment URL and redirect user
$paymentUrl = PaymentGateway::getPaymentUrl($paymentOrder);
return redirect($paymentUrl);
```

### Creating a Custom Payment Plugin

1. Create a class that implements `PaymentPluginInterface`:

```php
<?php

namespace App\PaymentPlugins;

use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Models\PaymentMethod;

class StripePaymentPlugin implements PaymentPluginInterface
{
    protected PaymentMethod $paymentMethod;

    public function __construct(PaymentMethod $paymentMethod)
    {
        $this->paymentMethod = $paymentMethod;
    }

    public function getName(): string
    {
        return 'Stripe Payment Gateway';
    }

    public function getDescription(): string
    {
        return 'Accept payments via Stripe';
    }

    public function getConfigurationFields(): array
    {
        return [
            [
                'name' => 'secret_key',
                'label' => 'Secret Key',
                'type' => 'text',
                'required' => true,
                'encrypted' => true,
            ],
            [
                'name' => 'publishable_key',
                'label' => 'Publishable Key',
                'type' => 'text',
                'required' => true,
            ],
        ];
    }

    public function validateConfiguration(): bool
    {
        return !empty($this->paymentMethod->getSetting('secret_key'));
    }

    public function processPayment(PaymentOrder $paymentOrder)
    {
        // Implement Stripe payment processing
        // Return view, redirect, or JSON response
    }

    public function handleCallback(array $callbackData): array
    {
        // Handle Stripe webhook
        return [
            'success' => true,
            'order_code' => $callbackData['order_code'],
            'transaction_id' => $callbackData['payment_intent'],
        ];
    }

    // ... implement other required methods
}
```

2. Register the plugin in your configuration:

```php
// config/payment-gateway.php
'plugins' => [
    'dummy' => \Trinavo\PaymentGateway\Plugins\DummyPaymentPlugin::class,
    'stripe' => \App\PaymentPlugins\StripePaymentPlugin::class,
],
```

3. Add the payment method to your database:

```php
use Trinavo\PaymentGateway\Models\PaymentMethod;

$paymentMethod = PaymentMethod::create([
    'name' => 'stripe',
    'plugin_class' => \App\PaymentPlugins\StripePaymentPlugin::class,
    'display_name' => 'Credit Card (Stripe)',
    'description' => 'Pay securely with your credit card',
    'enabled' => true,
    'sort_order' => 1,
]);

// Set plugin configuration
$paymentMethod->setSetting('secret_key', 'sk_test_...', true); // encrypted
$paymentMethod->setSetting('publishable_key', 'pk_test_...');
```

## Customization

### Views and Layout

The package uses a clean, modern layout with Tailwind CSS. You can customize the views by publishing them:

```bash
php artisan vendor:publish --provider="Trinavo\PaymentGateway\PaymentGatewayServiceProvider" --tag="views"
```

This will publish the views to `resources/views/vendor/payment-gateway/` where you can:

- Modify the main layout (`layouts/payment-gateway.blade.php`)
- Customize individual pages (checkout, success, failure, status)
- Change styling, fonts, colors, and branding
- Add your own CSS/JS assets

### Language Support

The package includes built-in support for English and Arabic. Language files are located in:

- `resources/lang/en/messages.php` - English translations
- `resources/lang/ar/messages.php` - Arabic translations

You can add more languages by creating additional language files following the same structure.

## Testing

The package includes a dummy payment plugin for testing purposes. It provides buttons to simulate:

- **Direct Success**: Immediate payment success
- **Direct Failure**: Immediate payment failure  
- **External Callback**: Simulates external gateway callback

## API Reference

### PaymentGatewayService Methods

- `createPaymentOrder(array $data): PaymentOrder` - Create a new payment order
- `getPaymentUrl(PaymentOrder $order): string` - Get checkout URL for an order
- `getAvailablePaymentMethods()` - Get enabled payment methods
- `processPayment(PaymentOrder $order, PaymentMethod $method)` - Process payment
- `handlePaymentSuccess(PaymentOrder $order, array $data)` - Handle successful payment
- `handlePaymentFailure(PaymentOrder $order, array $data)` - Handle failed payment

### PaymentOrder Model

- `isPending()`, `isProcessing()`, `isCompleted()`, `isFailed()`, `isCancelled()` - Status checks
- `markAsProcessing()`, `markAsCompleted()`, `markAsFailed()` - Status updates
- `getFormattedAmountAttribute()` - Get formatted amount with currency

### PaymentMethod Model

- `getSetting(string $key, $default = null)` - Get setting value
- `setSetting(string $key, $value, bool $encrypted = false)` - Set setting value
- `getSettings(): array` - Get all settings
- `getPluginInstance()` - Get plugin instance

## Routes

The package registers the following routes under the `/payment-gateway` prefix:

- `GET /checkout/{order}` - Checkout page
- `POST /checkout/{order}/process` - Process payment
- `ANY /callback/{plugin}` - Payment callback
- `GET /success/{order}` - Success page
- `GET /failure/{order}` - Failure page
- `GET /status/{order}` - Status page

## License

MIT License
