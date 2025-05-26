# Configuration Guide

The Laravel Payment Gateway package has minimal configuration options.

## Configuration File

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Trinavo\PaymentGateway\PaymentGatewayServiceProvider" --tag="config"
```

## Available Configuration

The `config/payment-gateway.php` file contains:

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Available Payment Plugins
    |--------------------------------------------------------------------------
    */
    'plugins' => [
        'dummy' => \Trinavo\PaymentGateway\Plugins\Dummy\DummyPaymentPlugin::class,
    ],
];
```

## Configuration Usage

The configuration values are now properly used throughout the package:

- **Routes**: Route prefix and middleware are loaded from `config('payment-gateway.routes')`
- **Plugins**: Plugin mapping is loaded from `config('payment-gateway.plugins')`
- **Default Currency**: Default currency is loaded from `config('payment-gateway.default_currency')`

## Adding Payment Methods

To add a new payment method, follow these steps:

### 1. Register the Plugin in Configuration

Add your plugin to the `config/payment-gateway.php` file:

```php
'plugins' => [
    'dummy' => \Trinavo\PaymentGateway\Plugins\Dummy\DummyPaymentPlugin::class,
    'stripe' => \App\PaymentPlugins\StripePaymentPlugin::class,
    'paypal' => \App\PaymentPlugins\PayPalPaymentPlugin::class,
],
```

### 2. Create the Payment Method Record

Use the database models to create the payment method:

```php
use Trinavo\PaymentGateway\Models\PaymentMethod;

PaymentMethod::create([
    'name' => 'stripe',
    'plugin_class' => \App\PaymentPlugins\StripePaymentPlugin::class,
    'display_name' => 'Credit Card',
    'enabled' => true,
    'sort_order' => 1,
]);
```

### 3. Configure Plugin Settings

Set up the plugin configuration:

```php
$paymentMethod = PaymentMethod::where('name', 'stripe')->first();
$paymentMethod->setSetting('publishable_key', env('STRIPE_PUBLISHABLE_KEY'));
$paymentMethod->setSetting('secret_key', env('STRIPE_SECRET_KEY'), true); // encrypted
```

See the [Creating Plugins](creating-plugins.md) guide for detailed plugin development instructions.

---

**Next:** [Payment Orders](payment-orders.md) →
