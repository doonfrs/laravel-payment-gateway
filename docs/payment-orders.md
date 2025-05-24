# Payment Orders

Payment orders are the core entity that tracks payment requests and their status.

## Database Structure

The `payment_orders` table contains:

```sql
CREATE TABLE payment_orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_code VARCHAR(255) UNIQUE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    
    customer_name VARCHAR(255) NOT NULL,
    customer_email VARCHAR(255) NOT NULL,
    customer_phone VARCHAR(255) NULL,
    
    payment_method_id BIGINT UNSIGNED NULL,
    external_transaction_id VARCHAR(255) NULL,
    payment_data JSON NULL,
    
    success_callback TEXT NULL,
    failure_callback TEXT NULL,
    success_url VARCHAR(500) NULL,
    failure_url VARCHAR(500) NULL,
    
    description TEXT NULL,
    customer_data JSON NULL,
    
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    paid_at TIMESTAMP NULL,
    attempted_at TIMESTAMP NULL
);
```

## Creating Orders

```php
use Trinavo\PaymentGateway\Models\PaymentOrder;

$order = PaymentOrder::create([
    'amount' => 99.99,
    'currency' => 'USD',
    'customer_name' => 'John Doe',
    'customer_email' => 'john@example.com',
    'description' => 'Premium Subscription',
]);

// Order code is automatically generated: "PO-A1B2C3D4"
```

## Status Management

### Available Statuses

- **pending** - Order created, waiting for payment
- **processing** - Payment is being processed  
- **completed** - Payment successful
- **failed** - Payment failed
- **cancelled** - Payment cancelled

### Status Check Methods

```php
$order = PaymentOrder::find(1);

if ($order->isPending()) {
    echo "Waiting for payment";
}

if ($order->isProcessing()) {
    echo "Payment in progress";
}

if ($order->isCompleted()) {
    echo "Payment successful";
}

if ($order->isFailed()) {
    echo "Payment failed";
}

if ($order->isCancelled()) {
    echo "Payment cancelled";
}
```

### Updating Status

```php
// Mark as completed
$order->markAsCompleted([
    'transaction_id' => 'txn_123456',
    'gateway_response' => $responseData,
]);

// Mark as failed
$order->markAsFailed([
    'error' => 'Card declined',
    'gateway_response' => $responseData,
]);
```

## Order Attributes

```php
$order = PaymentOrder::find(1);

// Basic info
echo $order->order_code;              // "PO-A1B2C3D4"
echo $order->amount;                  // 99.99
echo $order->currency;                // "USD"
echo $order->formatted_amount;        // "99.99 USD"

// Customer info
echo $order->customer_name;           // "John Doe"
echo $order->customer_email;          // "john@example.com"

// Payment info
echo $order->external_transaction_id; // Gateway transaction ID
echo $order->payment_data;            // JSON data from gateway
```

## Relationships

```php
// Get payment method used
$order = PaymentOrder::with('paymentMethod')->find(1);
echo $order->paymentMethod->display_name; // "Credit Card"
```

## Querying Orders

```php
// Find by order code
$order = PaymentOrder::where('order_code', 'PO-123456')->first();

// Get completed orders
$completed = PaymentOrder::where('status', 'completed')->get();

// Orders by customer
$customerOrders = PaymentOrder::where('customer_email', 'john@example.com')
    ->orderBy('created_at', 'desc')
    ->get();
```

---

**Next:** [Payment Methods](payment-methods.md) →
