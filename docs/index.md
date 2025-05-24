# Laravel Payment Gateway Documentation

## Introduction

The **Laravel Payment Gateway** is a simple, extensible payment processing package for Laravel applications. It provides a plugin-based architecture for integrating multiple payment providers.

## Features

- **Plugin-based architecture** - Easy to add new payment gateways
- **Built-in dummy gateway** - For testing and development
- **Payment order management** - Track payment lifecycle
- **Modern UI** - Built with Tailwind CSS
- **Multi-language support** - English and Arabic included

## Documentation

| Topic | Description | Link |
|-------|-------------|------|
| **Setup** | Get started quickly | [Installation Guide](installation.md) |
| **Basic Usage** | Create your first payment | [Quick Start](quick-start.md) |
| **Configuration** | Package settings | [Configuration](configuration.md) |
| **Payment Orders** | Order management | [Payment Orders](payment-orders.md) |
| **Payment Methods** | Gateway configuration | [Payment Methods](payment-methods.md) |
| **Plugin Architecture** | How plugins work | [Plugin Architecture](plugin-architecture.md) |
| **Custom Gateway** | Build your own plugin | [Creating Plugins](creating-plugins.md) |
| **UI Customization** | Modify the interface | [Views & Layouts](views-layouts.md) |
| **Troubleshooting** | Common issues | [Troubleshooting](troubleshooting/common-issues.md) |

## Requirements

- **Laravel**: 12.x
- **PHP**: 8.2+
- **Database**: MySQL 8.0+ / PostgreSQL 13+ / SQLite 3.35+

## Package Routes

The package provides these routes with prefix `/payment-gateway`:

- `/checkout/{order}` - Payment page
- `/process` - Process payment method selection
- `/success` - Success page
- `/failure` - Failure page
- `/status/{order}` - Status page
- `/callback/{plugin}` - Plugin callbacks

## Quick Example

```php
use Trinavo\PaymentGateway\Models\PaymentOrder;

// Create payment order
$order = PaymentOrder::create([
    'amount' => 99.99,
    'currency' => 'USD',
    'customer_name' => 'John Doe',
    'customer_email' => 'john@example.com',
]);

// Redirect to payment page
return redirect("/payment-gateway/checkout/{$order->order_code}");
```

## License

This package is open-sourced software licensed under the [MIT license](../LICENSE.md).

---

**Ready to get started?** Begin with our [Installation Guide](installation.md) or jump into the [Quick Start](quick-start.md) tutorial!
