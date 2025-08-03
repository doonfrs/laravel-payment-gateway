# Localization Support

The payment gateway package supports localization for `display_name` and `description` fields in both `PaymentMethod` and `PaymentOrder` models.

## How It Works

The localization system automatically detects if a field contains JSON-formatted localization data and displays the appropriate text based on the current application locale.

### JSON Format

When storing localized content, use the following JSON format:

```json
{
    "en": "English text",
    "ar": "Arabic text",
    "fr": "French text"
}
```

### Fallback Logic

The system follows this fallback order:

1. Current application locale (e.g., `app()->getLocale()`)
2. English (`en`) as fallback
3. First available translation
4. Plain text (if not JSON)

## Usage Examples

### PaymentMethod Localization

```php
// Create a payment method with localized display name
$paymentMethod = new PaymentMethod();
$paymentMethod->display_name = json_encode([
    'en' => 'Credit Card',
    'ar' => 'بطاقة ائتمان',
    'fr' => 'Carte de crédit'
]);
$paymentMethod->description = json_encode([
    'en' => 'Pay securely with your credit card',
    'ar' => 'ادفع بأمان باستخدام بطاقة الائتمان الخاصة بك',
    'fr' => 'Payez en toute sécurité avec votre carte de crédit'
]);
$paymentMethod->save();

// In views, the localization is automatic
echo $paymentMethod->getLocalizedDisplayName(); // Returns text based on current locale
echo $paymentMethod->getLocalizedDescription(); // Returns text based on current locale
```

### PaymentOrder Localization

```php
// Create a payment order with localized description
$paymentOrder = new PaymentOrder();
$paymentOrder->description = json_encode([
    'en' => 'Order #12345 - Electronics Purchase',
    'ar' => 'طلب #12345 - شراء إلكترونيات',
    'fr' => 'Commande #12345 - Achat d\'électronique'
]);
$paymentOrder->save();

// In views, the localization is automatic
echo $paymentOrder->getLocalizedDescription(); // Returns text based on current locale
```

## View Integration

The localization is automatically handled in all view files:

- `checkout.blade.php`
- `success.blade.php`
- `failure.blade.php`
- `status.blade.php`
- Plugin view files

### Before (Old Way)

```blade
{{ $paymentMethod->display_name ?: $paymentMethod->name }}
{{ $paymentOrder->description }}
```

### After (New Way)

```blade
{{ $paymentMethod->getLocalizedDisplayName() }}
{{ $paymentOrder->getLocalizedDescription() }}
```

## Setting Application Locale

To change the application locale, use Laravel's built-in localization:

```php
// Set locale for the current request
App::setLocale('ar');

// Or in middleware
public function handle($request, Closure $next)
{
    App::setLocale($request->getPreferredLanguage());
    return $next($request);
}
```

## Database Migration

If you need to update existing data to use localization, you can create a migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdatePaymentMethodsToUseLocalization extends Migration
{
    public function up()
    {
        // Example: Update existing display names to use JSON format
        DB::table('payment_methods')->where('display_name', 'Credit Card')->update([
            'display_name' => json_encode([
                'en' => 'Credit Card',
                'ar' => 'بطاقة ائتمان'
            ])
        ]);
    }

    public function down()
    {
        // Revert if needed
    }
}
```

## Testing

You can test the localization by changing your application locale:

```php
// Test with different locales
App::setLocale('en');
echo $paymentMethod->getLocalizedDisplayName(); // "Credit Card"

App::setLocale('ar');
echo $paymentMethod->getLocalizedDisplayName(); // "بطاقة ائتمان"

App::setLocale('fr');
echo $paymentMethod->getLocalizedDisplayName(); // "Carte de crédit" (or English if French not available)
```
