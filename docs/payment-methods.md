# Payment Methods

Payment methods represent available payment gateways in your application.

## Database Structure

The `payment_methods` table:

```sql
CREATE TABLE payment_methods (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) UNIQUE NOT NULL,
    plugin_class VARCHAR(255) NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    enabled BOOLEAN DEFAULT true,
    sort_order INT DEFAULT 0,
    logo_url VARCHAR(500) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

Settings are stored separately with encryption support:

```sql
CREATE TABLE payment_method_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_method_id BIGINT UNSIGNED NOT NULL,
    setting_key VARCHAR(255) NOT NULL,
    setting_value TEXT NULL,
    is_encrypted BOOLEAN DEFAULT false,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

## Creating Payment Methods

```php
use Trinavo\PaymentGateway\Models\PaymentMethod;

$paymentMethod = PaymentMethod::create([
    'name' => 'stripe',
    'plugin_class' => \App\PaymentPlugins\StripePaymentPlugin::class,
    'display_name' => 'Credit Card',
    'description' => 'Pay with credit or debit card',
    'enabled' => true,
    'sort_order' => 1,
]);
```

## Managing Settings

### Setting Values

```php
// Set non-encrypted setting
$paymentMethod->setSetting('api_version', '2023-10-16', false);

// Set encrypted setting
$paymentMethod->setSetting('secret_key', 'sk_live_...', true);

// Set multiple settings
$paymentMethod->setSettings([
    'publishable_key' => ['value' => 'pk_live_...', 'encrypted' => false],
    'secret_key' => ['value' => 'sk_live_...', 'encrypted' => true],
]);
```

### Getting Values

```php
// Get setting
$secretKey = $paymentMethod->getSetting('secret_key');

// Get with default value
$apiVersion = $paymentMethod->getSetting('api_version', '2023-10-16');

// Get all settings
$allSettings = $paymentMethod->getAllSettings();

// Check if setting exists
if ($paymentMethod->hasSetting('webhook_secret')) {
    // Setting exists
}
```

## Plugin Integration

```php
// Get plugin instance
$plugin = $paymentMethod->getPluginInstance();

// Use plugin methods
$pluginName = $plugin->getName();
$isConfigured = $plugin->validateConfiguration();
$configFields = $plugin->getConfigurationFields();
```

## Querying Payment Methods

```php
// Get enabled methods
$enabledMethods = PaymentMethod::where('enabled', true)
    ->orderBy('sort_order')
    ->get();

// Get method by name
$stripeMethod = PaymentMethod::where('name', 'stripe')->first();

// With settings
$methods = PaymentMethod::with('settings')
    ->where('enabled', true)
    ->get();
```

## Example: Stripe Setup

```php
$stripe = PaymentMethod::create([
    'name' => 'stripe',
    'plugin_class' => \App\PaymentPlugins\StripePaymentPlugin::class,
    'display_name' => 'Credit Card',
    'enabled' => true,
    'sort_order' => 1,
]);

$stripe->setSettings([
    'publishable_key' => ['value' => env('STRIPE_PUBLISHABLE_KEY'), 'encrypted' => false],
    'secret_key' => ['value' => env('STRIPE_SECRET_KEY'), 'encrypted' => true],
]);
```

---

**Next:** [Plugin Architecture](plugin-architecture.md) →
