# Installation Guide

This guide will walk you through installing and setting up the Laravel Payment Gateway package in your Laravel 12 application.

## Requirements

Before installing, ensure your system meets these requirements:

- **Laravel**: 12.x
- **PHP**: 8.2 or higher
- **Database**: MySQL 8.0+ / PostgreSQL 13+ / SQLite 3.35+
- **Composer**: 2.0+
- **Node.js**: 18+ (for Vite asset compilation)

## Step 1: Install via Composer

Install the package using Composer:

```bash
composer require trinavo/laravel-payment-gateway
```

## Step 2: Publish Configuration

Publish the package configuration file:

```bash
php artisan vendor:publish --provider="Trinavo\PaymentGateway\PaymentGatewayServiceProvider" --tag="config"
```

This creates `config/payment-gateway.php` where you can configure payment plugins and settings.

## Step 3: Publish and Run Migrations

Publish the migration files:

```bash
php artisan vendor:publish --provider="Trinavo\PaymentGateway\PaymentGatewayServiceProvider" --tag="migrations"
```

Run the migrations to create the required database tables:

```bash
php artisan migrate
```

This creates three tables:

- `payment_orders` - Stores payment order information
- `payment_methods` - Stores available payment gateways
- `payment_method_settings` - Stores gateway configuration (encrypted)

## Step 4: Publish Views (Optional)

If you want to customize the payment interface, publish the views:

```bash
php artisan vendor:publish --provider="Trinavo\PaymentGateway\PaymentGatewayServiceProvider" --tag="views"
```

Views will be published to `resources/views/vendor/payment-gateway/` where you can customize:

- Layout and styling
- Payment pages (checkout, success, failure, status)
- Plugin-specific views

## Step 5: Publish Language Files (Optional)

The package includes English and Arabic translations. Language files are automatically available in `/lang/en.json` and `/lang/ar.json`. If you need to customize translations, you can modify these files directly.

## Step 6: Seed Payment Methods (Optional)

To get started quickly with the dummy payment gateway, run the seeder:

```bash
php artisan db:seed --class="Database\Seeders\PaymentMethodSeeder"
```

This creates a dummy payment method for testing purposes.

## Step 7: Configure Asset Compilation

The package uses Tailwind CSS for styling. Ensure your `vite.config.js` includes the package views:

```javascript
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    // Add this to include package views in Tailwind compilation
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './vendor/trinavo/laravel-payment-gateway/resources/views/**/*.blade.php',
    ],
});
```

And ensure your `tailwind.config.js` includes the package paths:

```javascript
/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./resources/**/*.blade.php",
        "./resources/**/*.js",
        "./resources/**/*.vue",
        "./vendor/trinavo/laravel-payment-gateway/resources/views/**/*.blade.php",
    ],
    theme: {
        extend: {},
    },
    plugins: [],
}
```

## Step 8: Build Assets

Compile your assets:

```bash
npm run build
# or for development
npm run dev
```

## Verification

To verify the installation was successful:

1. **Check Routes**: Visit `/payment-gateway/status/test` (replace 'test' with any order code)
2. **Check Database**: Verify the three tables were created
3. **Check Config**: Ensure `config/payment-gateway.php` exists

## Environment Configuration

The package works with the default configuration. No environment variables are required for basic functionality.

## Troubleshooting Installation

### Common Issues

**Migration Errors**

```bash
# If you get migration conflicts, try:
php artisan migrate:fresh
# Or rollback and re-run:
php artisan migrate:rollback
php artisan migrate
```

**Asset Compilation Issues**

```bash
# Clear and rebuild assets:
npm run build
php artisan view:clear
php artisan config:clear
```

**Permission Issues**

```bash
# Ensure proper permissions:
chmod -R 755 storage/
chmod -R 755 bootstrap/cache/
```

### Verification Commands

```bash
# Check if package is installed
composer show trinavo/laravel-payment-gateway

# Verify tables exist
php artisan tinker
>>> \Schema::hasTable('payment_orders')
>>> \Schema::hasTable('payment_methods')
>>> \Schema::hasTable('payment_method_settings')

# Test the service
>>> app(\Trinavo\PaymentGateway\Services\PaymentGatewayService::class)->getAvailablePaymentMethods()
```

## Next Steps

Once installation is complete:

1. **[Quick Start](quick-start.md)** - Create your first payment
2. **[Configuration](configuration.md)** - Configure payment gateways
3. **[Creating Plugins](creating-plugins.md)** - Add custom payment gateways

## Getting Help

If you encounter issues during installation:

- Check the [Common Issues](troubleshooting/common-issues.md) guide
- Review the [FAQ](troubleshooting/faq.md)
- Visit our [Support](troubleshooting/support.md) page

---

**Next:** [Quick Start Guide](quick-start.md) →
