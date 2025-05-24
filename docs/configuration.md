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
        'dummy' => \Trinavo\PaymentGateway\Plugins\DummyPaymentPlugin::class,
    ],
];
```

## Important Note

**The configuration values are not actually used by the source code.** The package works with hardcoded values:

- Routes are hardcoded in `src/Http/routes.php` with prefix 'payment-gateway'
- Plugin mapping is hardcoded in `PaymentController::getPluginClass()`
- Default currency is hardcoded as 'USD' in `PaymentGatewayService`

## Adding Payment Methods

Use the database models directly:

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

---

**Next:** [Payment Orders](payment-orders.md) →
