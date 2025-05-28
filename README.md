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

## Quick Setup

For the fastest setup, use the install command:

```bash
php artisan payment-gateway:install
```

This command will:

- Publish configuration files
- Add Tailwind CSS source paths
- Optionally publish views and translations
- Run database migrations

## Manual Configuration

### 1. Publish the Configuration

```bash
php artisan vendor:publish --provider="Trinavo\PaymentGateway\PaymentGatewayServiceProvider" --tag="config"
```

### 2. Publish and Run Migrations

```bash
php artisan vendor:publish --provider="Trinavo\PaymentGateway\PaymentGatewayServiceProvider" --tag="migrations"
php artisan migrate
```

### 3. Configure Tailwind CSS

Add this line to your `resources/css/app.css`:

```css
@source '../../vendor/trinavo/laravel-payment-gateway/resources/**/*.php';
```

### 4. Publish Views (Optional)

```bash
php artisan vendor:publish --provider="Trinavo\PaymentGateway\PaymentGatewayServiceProvider" --tag="views"
```

This will publish the views to `resources/views/vendor/payment-gateway/` where you can customize the layout, styling, and content.

### 5. Seed Payment Methods (Optional)

```bash
php artisan db:seed --class="Database\Seeders\PaymentMethodSeeder"
```

## Basic Usage

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
    'ignored_plugins' => ['stripe', 'paypal'], // Optional: plugins to exclude for this order
]);

// Get payment URL and redirect user
$paymentUrl = PaymentGateway::getPaymentUrl($paymentOrder);
return redirect($paymentUrl);
```

### Alternative Direct Model Usage

```php
use Trinavo\PaymentGateway\Models\PaymentOrder;

$paymentOrder = PaymentOrder::create([
    'amount' => 100.00,
    'currency' => 'USD',
    'customer_name' => 'John Doe',
    'customer_email' => 'john@example.com',
    'description' => 'Order #12345',
]);

// Redirect to checkout
return redirect("/payment-gateway/checkout/{$paymentOrder->order_code}");
```

### Ignoring Specific Plugins for Orders

You can exclude specific payment plugins from being available for individual orders by passing an `ignored_plugins` array:

```php
use Trinavo\PaymentGateway\Facades\PaymentGateway;

// Create an order that excludes certain payment methods
$paymentOrder = PaymentGateway::createPaymentOrder([
    'amount' => 100.00,
    'currency' => 'USD',
    'customer_name' => 'John Doe',
    'customer_email' => 'john@example.com',
    'description' => 'Order #12345',
    'ignored_plugins' => ['stripe', 'paypal'], // These plugins won't be available
]);

// You can also set ignored plugins after creation
$paymentOrder->setIgnoredPlugins(['dummy', 'bank_transfer']);

// Check if a plugin is ignored
if ($paymentOrder->isPluginIgnored('stripe')) {
    // Stripe is not available for this order
}

// Get all ignored plugins
$ignoredPlugins = $paymentOrder->getIgnoredPlugins();
```

The `ignored_plugins` array can contain:

- **Plugin names** (e.g., 'stripe', 'paypal', 'dummy')
- **Plugin class names** (e.g., 'App\PaymentPlugins\StripePaymentPlugin')

This is useful for scenarios like:

- **Subscription payments** where only certain methods are allowed
- **High-risk transactions** where specific gateways should be avoided
- **Regional restrictions** where some payment methods aren't available
- **Customer preferences** where users have blocked certain payment types

## Creating a Custom Payment Plugin

1. Create a class that implements `PaymentPluginInterface`:

```php
<?php

namespace App\PaymentPlugins;

use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\CallbackResponse;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Configuration\TextField;
use Trinavo\PaymentGateway\Configuration\PasswordField;

class StripePaymentPlugin extends PaymentPluginInterface
{

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
            new PasswordField(
                name: 'secret_key',
                label: 'Secret Key',
                required: true,
                description: 'Your Stripe secret key (will be encrypted)',
                placeholder: 'sk_test_...'
            ),
            
            new TextField(
                name: 'publishable_key',
                label: 'Publishable Key',
                required: true,
                description: 'Your Stripe publishable key',
                placeholder: 'pk_test_...'
            ),
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

    public function handleCallback(array $callbackData): CallbackResponse
    {
        // Handle Stripe webhook
        return CallbackResponse::success(
            orderCode: $callbackData['order_code'],
            transactionId: $callbackData['payment_intent'],
            message: 'Payment completed successfully'
        );
    }

    // ... implement other required methods
}
```

2. Register the plugin in your configuration:

```php
// config/payment-gateway.php
'plugins' => [
    \Trinavo\PaymentGateway\Plugins\Dummy\DummyPaymentPlugin::class,
    \App\PaymentPlugins\StripePaymentPlugin::class,
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

- `lang/en.json` - English translations
- `lang/ar.json` - Arabic translations

You can add more languages by creating additional language files following the same structure.

## Testing

The package includes a dummy payment plugin for testing purposes. It provides buttons to simulate:

- **Direct Success**: Immediate payment success
- **Direct Failure**: Immediate payment failure  
- **External Callback**: Simulates external gateway callback

## API Reference

### PaymentGateway Facade Methods

- `createPaymentOrder(array $data): PaymentOrder` - Create a new payment order (supports `ignored_plugins` array)
- `getPaymentUrl(PaymentOrder $order): string` - Get checkout URL for an order
- `getAvailablePaymentMethods()` - Get enabled payment methods
- `getAvailablePaymentMethodsForOrder(PaymentOrder $order)` - Get available methods for specific order (respects ignored plugins)
- `processPayment(PaymentOrder $order, PaymentMethod $method)` - Process payment
- `handlePaymentSuccess(PaymentOrder $order, array $data)` - Handle successful payment
- `handlePaymentFailure(PaymentOrder $order, array $data)` - Handle failed payment
- `getPaymentOrderByCode(string $orderCode): ?PaymentOrder` - Get order by code
- `registerPaymentMethod(array $data): PaymentMethod` - Register new payment method

### PaymentOrder Model Methods

- `isPending()`, `isProcessing()`, `isCompleted()`, `isFailed()`, `isCancelled()` - Status checks
- `markAsProcessing()`, `markAsCompleted()`, `markAsFailed()` - Status updates
- `getFormattedAmountAttribute()` - Get formatted amount with currency

### PaymentMethod Model Methods

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
- `GET /dummy/{order}/{action}` - Dummy payment actions (success|failure|callback)

## Documentation

For detailed documentation, see the [docs](docs/) directory:

- [Installation Guide](docs/installation.md)
- [Quick Start](docs/quick-start.md)
- [Configuration](docs/configuration.md)
- [Plugin Configuration](docs/plugin-configuration.md)
- [Configuration Fields](docs/configuration-fields.md)
- [CallbackResponse Class](docs/callback-response.md)
- [Payment Orders](docs/payment-orders.md)
- [Payment Methods](docs/payment-methods.md)
- [Creating Plugins](docs/creating-plugins.md)
- [Views & Layouts](docs/views-layouts.md)
- [Troubleshooting](docs/troubleshooting/common-issues.md)

## License

MIT License

## Plugin System

The Laravel Payment Gateway uses a flexible plugin system that allows you to integrate with any payment provider. All plugins are loaded dynamically from the configuration file.

### Adding New Payment Plugins

1. **Configure the plugin** in `config/payment-gateway.php` (simply add the class name):

```php
'plugins' => [
    \Trinavo\PaymentGateway\Plugins\Dummy\DummyPaymentPlugin::class,
    \App\PaymentPlugins\StripePaymentPlugin::class,
    \App\PaymentPlugins\PayPalPaymentPlugin::class,
],
```

Plugin keys are automatically generated from class names (e.g., `StripePaymentPlugin` → `stripe`).

2. **Create a payment method record**:

```php
use Trinavo\PaymentGateway\Models\PaymentMethod;

PaymentMethod::create([
    'name' => 'stripe',
    'plugin_class' => \App\PaymentPlugins\StripePaymentPlugin::class,
    'display_name' => 'Credit Card (Stripe)',
    'enabled' => true,
    'sort_order' => 1,
]);
```

3. **Configure plugin settings**:

```php
$paymentMethod = PaymentMethod::where('name', 'stripe')->first();
$paymentMethod->setSetting('publishable_key', env('STRIPE_PUBLISHABLE_KEY'));
$paymentMethod->setSetting('secret_key', env('STRIPE_SECRET_KEY'), true); // encrypted
```

### Available Payment Methods

The system will automatically list all enabled payment methods from the database. The configuration ensures that:

- **Plugin callbacks** are routed correctly based on the config
- **Default currency** is loaded from config settings
- **Route configuration** (prefix, middleware) is respected
- **Multiple plugins** can coexist and be managed independently

See the [Creating Plugins](docs/creating-plugins.md) guide for detailed plugin development instructions.
