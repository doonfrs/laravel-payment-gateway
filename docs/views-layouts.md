# Views & Layouts Customization

This guide shows how to customize the payment gateway's user interface.

## Publishing Views

To customize the views, publish them to your application:

```bash
php artisan vendor:publish --provider="Trinavo\PaymentGateway\PaymentGatewayServiceProvider" --tag="views"
```

This publishes views to `resources/views/vendor/payment-gateway/` where you can modify them.

## Available Views

The package includes these views:

- `layouts/payment-gateway.blade.php` - Main layout
- `checkout.blade.php` - Payment method selection page
- `dummy-payment.blade.php` - Dummy payment plugin page
- `success.blade.php` - Payment success page
- `failure.blade.php` - Payment failure page
- `status.blade.php` - Payment status page

## Main Layout

The main layout file:

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', __('default_title'))</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-gray-50 min-h-screen">
    <div class="min-h-screen">
        <main>
            @yield('content')
        </main>
    </div>

    @stack('scripts')
</body>
</html>
```

## Customizing Views

After publishing, you can:

1. **Modify the layout** to include your application's header/navigation
2. **Update styling** using Tailwind CSS classes
3. **Add custom JavaScript** using `@push('scripts')`
4. **Change the overall structure** and branding

## Tailwind CSS Integration

Ensure your `tailwind.config.js` includes the package views:

```javascript
export default {
    content: [
        "./resources/**/*.blade.php",
        "./vendor/trinavo/laravel-payment-gateway/resources/views/**/*.blade.php",
    ],
    // ... rest of config
}
```

Then rebuild your assets:

```bash
npm run build
```

## Language Support

The views use Laravel's translation system. Language files are in:

- `/lang/en.json` - English translations
- `/lang/ar.json` - Arabic translations

You can modify these files or add new languages.

## Plugin-Specific Views

For custom payment plugins, you can create plugin-specific views and return the view name from your plugin's `getPaymentView()` method:

```php
public function getPaymentView(): ?string
{
    return 'payment-plugins.stripe'; // resources/views/payment-plugins/stripe.blade.php
}
```

---

**Next:** [Troubleshooting](troubleshooting/common-issues.md) →
