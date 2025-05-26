# Configuration Fields

The Laravel Payment Gateway now supports a modern, type-safe configuration field system using classes instead of arrays. This makes it easier to define plugin configuration fields with proper IDE support and validation.

## Overview

Instead of defining configuration fields as arrays, you can now use dedicated field classes that provide:

- **Type Safety**: Proper PHP types and IDE autocompletion
- **Validation**: Built-in validation for field types
- **Consistency**: Standardized field definitions across all plugins
- **Extensibility**: Easy to add new field types

## Available Field Types

### TextField

For text input fields:

```php
use Trinavo\PaymentGateway\Configuration\TextField;

new TextField(
    name: 'api_key',
    label: 'API Key',
    required: true,
    description: 'Your payment gateway API key',
    placeholder: 'Enter your API key...',
    maxLength: 255
)
```

### PasswordField

For sensitive data that should be encrypted:

```php
use Trinavo\PaymentGateway\Configuration\PasswordField;

new PasswordField(
    name: 'secret_key',
    label: 'Secret Key',
    required: true,
    description: 'Your secret key (will be encrypted)',
    placeholder: 'sk_test_...'
)
```

### SelectField

For dropdown/select fields with predefined options:

```php
use Trinavo\PaymentGateway\Configuration\SelectField;

// Key-value options
new SelectField(
    name: 'environment',
    label: 'Environment',
    options: [
        'sandbox' => 'Sandbox (Testing)',
        'live' => 'Live (Production)'
    ],
    required: true,
    default: 'sandbox'
)

// Index-based options (value = label)
new SelectField(
    name: 'currency',
    label: 'Currency',
    options: ['USD', 'EUR', 'GBP'],
    required: true,
    default: 'USD'
)

// Multiple selection
new SelectField(
    name: 'supported_cards',
    label: 'Supported Cards',
    options: [
        'visa' => 'Visa',
        'mastercard' => 'Mastercard',
        'amex' => 'American Express'
    ],
    multiple: true
)
```

### CheckboxField

For boolean/toggle fields:

```php
use Trinavo\PaymentGateway\Configuration\CheckboxField;

new CheckboxField(
    name: 'test_mode',
    label: 'Test Mode',
    default: true,
    description: 'Enable test mode for this gateway'
)
```

### NumberField

For numeric input fields:

```php
use Trinavo\PaymentGateway\Configuration\NumberField;

new NumberField(
    name: 'timeout',
    label: 'Timeout (seconds)',
    required: false,
    default: 30,
    min: 1,
    max: 300,
    step: 1,
    placeholder: '30'
)
```

## Using Configuration Fields in Plugins

### Basic Usage

```php
<?php

namespace App\PaymentPlugins;

use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Configuration\TextField;
use Trinavo\PaymentGateway\Configuration\PasswordField;
use Trinavo\PaymentGateway\Configuration\SelectField;
use Trinavo\PaymentGateway\Configuration\CheckboxField;

class MyPaymentPlugin extends PaymentPluginInterface
{

    public function getConfigurationFields(): array
    {
        return [
            new TextField(
                name: 'merchant_id',
                label: 'Merchant ID',
                required: true,
                description: 'Your merchant identifier'
            ),
            
            new PasswordField(
                name: 'api_secret',
                label: 'API Secret',
                required: true,
                description: 'Your API secret key'
            ),
            
            new SelectField(
                name: 'environment',
                label: 'Environment',
                options: [
                    'sandbox' => 'Sandbox',
                    'production' => 'Production'
                ],
                default: 'sandbox'
            ),
            
            new CheckboxField(
                name: 'auto_capture',
                label: 'Auto Capture',
                default: true,
                description: 'Automatically capture payments'
            ),
        ];
    }
    
    // ... other plugin methods
}
```

### Backward Compatibility

The system maintains backward compatibility with the old array-based configuration:

```php
// Old way (still supported)
public function getConfigurationFields(): array
{
    return [
        [
            'name' => 'api_key',
            'label' => 'API Key',
            'type' => 'text',
            'required' => true,
        ],
    ];
}

// New way (recommended)
public function getConfigurationFields(): array
{
    return [
        new TextField(
            name: 'api_key',
            label: 'API Key',
            required: true
        ),
    ];
}
```

### Converting to Array Format

If you need to convert configuration fields to the legacy array format, use the built-in method:

```php
// Get fields as array format
$fieldsArray = $this->getConfigurationFieldsArray();
```

## Field Properties

All configuration fields support these common properties:

- **name**: Field identifier (required)
- **label**: Display label (required)
- **required**: Whether the field is required (default: false)
- **default**: Default value (optional)
- **description**: Help text for the field (optional)
- **encrypted**: Whether to encrypt the value (default: false, always true for PasswordField)

## Creating Custom Field Types

You can create custom field types by extending the `ConfigurationField` base class:

```php
<?php

namespace App\Configuration;

use Trinavo\PaymentGateway\Contracts\ConfigurationField;

class EmailField extends ConfigurationField
{
    public function getType(): string
    {
        return 'email';
    }
    
    public function toArray(): array
    {
        $array = parent::toArray();
        $array['validation'] = 'email';
        return $array;
    }
}
```

## Migration Guide

To migrate from array-based configuration to the new field classes:

1. **Change your plugin to extend the abstract class**:

   ```php
   class MyPaymentPlugin extends PaymentPluginInterface
   ```

2. **Import field classes**:

   ```php
   use Trinavo\PaymentGateway\Configuration\TextField;
   use Trinavo\PaymentGateway\Configuration\PasswordField;
   // ... other field types
   ```

3. **Replace array definitions** with field objects:

   ```php
   // Before
   [
       'name' => 'api_key',
       'label' => 'API Key',
       'type' => 'text',
       'required' => true,
   ]
   
   // After
   new TextField(
       name: 'api_key',
       label: 'API Key',
       required: true
   )
   ```

## Benefits

The new configuration field system provides:

- **Better Developer Experience**: IDE autocompletion and type hints
- **Reduced Errors**: Type safety prevents common configuration mistakes
- **Consistency**: Standardized field definitions across all plugins
- **Maintainability**: Easier to update and extend field types
- **Documentation**: Self-documenting code with clear field definitions
