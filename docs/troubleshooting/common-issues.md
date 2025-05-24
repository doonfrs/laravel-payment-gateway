# Common Issues & Solutions

Common issues specific to the Laravel Payment Gateway package and their solutions.

## Installation Issues

### Migration Errors

**Problem:** Migration fails with table already exists error

**Solution:**

```bash
# Check if tables already exist
php artisan tinker
>>> Schema::hasTable('payment_orders')

# If tables exist, rollback and re-run
php artisan migrate:rollback --step=3
php artisan migrate
```

### Views Not Styled

**Problem:** Payment pages look unstyled

**Solution:**

```bash
# Ensure Tailwind includes package views in tailwind.config.js:
content: [
    "./resources/**/*.blade.php",
    "./vendor/trinavo/laravel-payment-gateway/resources/views/**/*.blade.php",
]

# Rebuild assets
npm run build
```

## Payment Method Issues

### No Payment Methods Available

**Problem:** Checkout page shows no payment methods

**Solution:**

```php
// Check if payment methods exist and are enabled
use Trinavo\PaymentGateway\Models\PaymentMethod;

$methods = PaymentMethod::where('enabled', true)->get();

// If empty, create dummy payment method
PaymentMethod::create([
    'name' => 'dummy',
    'plugin_class' => \Trinavo\PaymentGateway\Plugins\DummyPaymentPlugin::class,
    'display_name' => 'Test Payment',
    'enabled' => true,
    'sort_order' => 1,
]);
```

### Plugin Class Not Found

**Problem:** `Class 'App\PaymentPlugins\StripePaymentPlugin' not found`

**Solution:**

```bash
# Ensure the plugin class exists and is autoloaded
composer dump-autoload

# Check the class exists in: app/PaymentPlugins/StripePaymentPlugin.php
# With namespace: App\PaymentPlugins
```

## Order Processing Issues

### Orders Stuck in Pending

**Problem:** Payment orders remain in "pending" status

**Solution:**

```php
// For dummy payments, ensure you click the action buttons
// For real gateways, check webhook/callback configuration

// Manually test order status update:
use Trinavo\PaymentGateway\Models\PaymentOrder;

$order = PaymentOrder::where('order_code', 'PO-123456')->first();
$order->markAsCompleted(['transaction_id' => 'test_123']);
```

### Callback Issues

**Problem:** External gateway callbacks not working

**Solution:**

```bash
# Check callback URL is accessible:
# https://yoursite.com/payment-gateway/callback/{plugin}

# Test route exists:
php artisan route:list | grep callback

# For production, ensure SSL certificate is valid
```

### CSRF Token Issues

**Problem:** `419 Page Expired` error on payment forms

**Solution:**

```blade
<!-- Ensure CSRF token is included -->
<form method="POST" action="{{ route('payment-gateway.process') }}">
    @csrf
    <!-- form fields -->
</form>
```

## Database Issues

### Cannot Delete Payment Methods

**Problem:** Foreign key constraint error when deleting payment methods

**Solution:**

```php
// Check for related orders first
$method = PaymentMethod::find(1);
$orderCount = PaymentOrder::where('payment_method_id', $method->id)->count();

if ($orderCount > 0) {
    // Disable instead of delete
    $method->update(['enabled' => false]);
} else {
    $method->delete();
}
```

### Settings Encryption Issues

**Problem:** Cannot decrypt payment method settings

**Solution:**

```bash
# Check APP_KEY is consistent
# If you changed APP_KEY, re-add encrypted settings

php artisan tinker
>>> encrypt('test')  # Should work
```

## Plugin Development Issues

### Plugin Not Working

**Problem:** Custom plugin not processing payments

**Solution:**

```php
// Ensure plugin implements all required interface methods
// Check plugin is properly registered in payment_methods table
// Test plugin configuration validation

$method = PaymentMethod::where('name', 'your-plugin')->first();
$plugin = $method->getPluginInstance();
$isValid = $plugin->validateConfiguration();
```

## Common Commands

```bash
# Clear caches
php artisan optimize:clear

# Check package routes
php artisan route:list | grep payment-gateway

# Check if package is installed
composer show trinavo/laravel-payment-gateway
```

---

**Next:** [Plugin Architecture](../plugin-architecture.md) →
