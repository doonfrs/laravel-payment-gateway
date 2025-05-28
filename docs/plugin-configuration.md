# Plugin Configuration

This guide explains how to configure payment plugins in the Laravel Payment Gateway package.

## Index-Based Configuration (Recommended)

The recommended approach is to use an index-based array where you simply list the plugin class names. Plugin keys will be automatically generated from the class names.

```php
// config/payment-gateway.php
'plugins' => [
    \Trinavo\PaymentGateway\Plugins\Dummy\DummyPaymentPlugin::class,
    \App\PaymentPlugins\StripePaymentPlugin::class,
    \App\PaymentPlugins\PayPalPaymentPlugin::class,
    \App\PaymentPlugins\MoyasarPaymentPlugin::class,
],
```

### Automatic Key Generation

Plugin keys are automatically generated from class names using these rules:

1. **Extract class basename**: `StripePaymentPlugin` → `StripePaymentPlugin`
2. **Remove common suffixes**: `Plugin`, `PaymentPlugin`, `Gateway`, `PaymentGateway`
3. **Convert to snake_case**: `StripePayment` → `stripe_payment`

#### Examples

| Class Name | Generated Key |
|------------|---------------|
| `StripePaymentPlugin` | `stripe_payment` |
| `PayPalPlugin` | `pay_pal` |
| `MoyasarPaymentGateway` | `moyasar_payment` |
| `DummyPaymentPlugin` | `dummy_payment` |
| `SimpleGateway` | `simple` |

### Benefits

- **Simpler configuration**: No need to remember or maintain plugin keys
- **Less error-prone**: No risk of typos in plugin keys
- **Automatic consistency**: Keys are always generated consistently
- **Easier maintenance**: Adding/removing plugins requires only class name changes

## Legacy Key-Value Configuration (Still Supported)

For backward compatibility, you can still use the key-value format:

```php
// config/payment-gateway.php
'plugins' => [
    'dummy' => \Trinavo\PaymentGateway\Plugins\Dummy\DummyPaymentPlugin::class,
    'stripe' => \App\PaymentPlugins\StripePaymentPlugin::class,
    'paypal' => \App\PaymentPlugins\PayPalPaymentPlugin::class,
],
```

## Mixed Configuration

You can even mix both approaches in the same configuration:

```php
// config/payment-gateway.php
'plugins' => [
    // Index-based (automatic key generation)
    \Trinavo\PaymentGateway\Plugins\Dummy\DummyPaymentPlugin::class,
    \App\PaymentPlugins\StripePaymentPlugin::class,
    
    // Key-value (custom keys)
    'custom_paypal' => \App\PaymentPlugins\PayPalPaymentPlugin::class,
    'bank_transfer' => \App\PaymentPlugins\BankTransferPlugin::class,
],
```

## Plugin Registration Process

1. **Configuration Loading**: Plugins are loaded from the config file
2. **Key Normalization**: Both index-based and key-value formats are normalized
3. **Plugin Registry**: The `PluginRegistryService` manages plugin mapping
4. **Callback Routing**: Routes are automatically generated using plugin keys

## Callback URL Generation

Plugin callback URLs are automatically generated using the plugin key:

```php
// For StripePaymentPlugin, the callback URL will be:
// /payment-gateway/callback/stripe_payment

// In your plugin, you can get the callback URL:
public function getCallbackUrl(): string
{
    // This is automatically implemented in the abstract class
    return route('payment-gateway.callback', ['plugin' => 'stripe_payment']);
}
```

## Plugin Key Lookup

You can programmatically work with plugin keys:

```php
use Trinavo\PaymentGateway\Services\PluginRegistryService;

$registry = app(PluginRegistryService::class);

// Get all registered plugins
$plugins = $registry->getRegisteredPlugins();
// Returns: ['dummy_payment' => 'DummyPaymentPlugin', ...]

// Get plugin class by key
$class = $registry->getPluginClass('stripe_payment');
// Returns: 'App\PaymentPlugins\StripePaymentPlugin'

// Get plugin key from class
$key = $registry->getPluginKey('App\PaymentPlugins\StripePaymentPlugin');
// Returns: 'stripe_payment'

// Check if plugin is registered
$exists = $registry->isPluginRegistered('stripe_payment');
// Returns: true/false

// Find plugin key by class (reverse lookup)
$key = $registry->findPluginKeyByClass('App\PaymentPlugins\StripePaymentPlugin');
// Returns: 'stripe_payment'
```

## Migration from Key-Value to Index-Based

If you're migrating from the old key-value format:

### Before

```php
'plugins' => [
    'dummy' => \Trinavo\PaymentGateway\Plugins\Dummy\DummyPaymentPlugin::class,
    'stripe' => \App\PaymentPlugins\StripePaymentPlugin::class,
],
```

### After

```php
'plugins' => [
    \Trinavo\PaymentGateway\Plugins\Dummy\DummyPaymentPlugin::class,
    \App\PaymentPlugins\StripePaymentPlugin::class,
],
```

### Important Notes

1. **Plugin keys may change**: The auto-generated keys might be different from your custom keys
2. **Update payment methods**: You may need to update the `name` field in your `payment_methods` table
3. **Callback URLs**: External webhook configurations may need updating if keys change
4. **Gradual migration**: You can migrate one plugin at a time using the mixed approach

## Best Practices

1. **Use index-based configuration** for new projects
2. **Consistent naming**: Use descriptive class names that generate meaningful keys
3. **Avoid conflicts**: Ensure generated keys don't conflict with existing custom keys
4. **Test thoroughly**: Verify callback URLs work after configuration changes
5. **Document changes**: Keep track of key changes when migrating

## Troubleshooting

### Plugin Not Found Error

If you get "Unknown plugin" errors:

1. **Check configuration**: Ensure the plugin class is listed in the config
2. **Verify class exists**: Make sure the plugin class file exists and is autoloaded
3. **Check key generation**: Use `PluginRegistryService::getPluginKey()` to see the generated key
4. **Clear cache**: Run `php artisan config:clear` to clear configuration cache

### Callback URL Issues

If callbacks aren't working:

1. **Check generated key**: Verify the plugin key matches the callback URL
2. **Update webhooks**: Update external webhook URLs if keys changed
3. **Test routes**: Verify the callback route is accessible
4. **Check logs**: Look for routing or plugin loading errors

### Key Conflicts

If you have key conflicts between auto-generated and custom keys:

1. **Use custom keys**: Override with explicit key-value pairs
2. **Rename classes**: Adjust class names to generate different keys
3. **Mixed approach**: Use both formats strategically

---

**Next:** [Creating Plugins](creating-plugins.md) →
