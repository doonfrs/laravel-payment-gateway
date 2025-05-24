# Quick Start Guide

Get up and running with the Laravel Payment Gateway in 5 minutes!

## Step 1: Create a Payment Order

```php
<?php

namespace App\Http\Controllers;

use Trinavo\PaymentGateway\Models\PaymentOrder;

class CheckoutController extends Controller
{
    public function createPayment()
    {
        $paymentOrder = PaymentOrder::create([
            'amount' => 99.99,
            'currency' => 'USD',
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'description' => 'Premium Subscription',
        ]);

        // Redirect to payment page
        return redirect("/payment-gateway/checkout/{$paymentOrder->order_code}");
    }
}
```

## Step 2: Set Up a Payment Method

Create a dummy payment method for testing:

```bash
php artisan db:seed --class="Database\Seeders\PaymentMethodSeeder"
```

Or create manually:

```php
use Trinavo\PaymentGateway\Models\PaymentMethod;

PaymentMethod::create([
    'name' => 'dummy',
    'plugin_class' => \Trinavo\PaymentGateway\Plugins\DummyPaymentPlugin::class,
    'display_name' => 'Test Payment Gateway',
    'enabled' => true,
    'sort_order' => 1,
]);
```

## Step 3: Test the Flow

1. Visit: `/payment-gateway/checkout/{order-code}`
2. Select the dummy payment method
3. Test different scenarios:
   - Click "Pay Successfully" for success
   - Click "Fail Payment" for failure
   - Click "External Payment" for callback simulation

## Step 4: Check Payment Status

```php
$order = PaymentOrder::where('order_code', 'PO-123456')->first();

if ($order->isCompleted()) {
    echo "Payment successful!";
} elseif ($order->isFailed()) {
    echo "Payment failed";
} elseif ($order->isPending()) {
    echo "Payment pending";
}
```

## Available Routes

The package provides these routes:

- `/payment-gateway/checkout/{order}` - Payment page
- `/payment-gateway/process` - Process payment method selection
- `/payment-gateway/success` - Success page
- `/payment-gateway/failure` - Failure page
- `/payment-gateway/status/{order}` - Status page
- `/payment-gateway/callback/{plugin}` - Plugin callbacks

## What's Next?

- **[Create Custom Plugins](creating-plugins.md)** - Add real payment gateways
- **[Customize Views](views-layouts.md)** - Modify the UI
- **[Configuration](configuration.md)** - Package settings

---

**Next:** [Configuration Guide](configuration.md) →
