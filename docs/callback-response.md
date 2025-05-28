# CallbackResponse Class

The `CallbackResponse` class provides a type-safe, developer-friendly way to handle payment callback responses instead of using arrays with hard-to-remember keys.

## Overview

Instead of returning arrays from the `handleCallback` method, plugins now return `CallbackResponse` objects that provide:

- **Type safety** with proper PHP types and IDE autocompletion
- **Static factory methods** for common response types
- **Automatic data handling** for payment storage
- **Backward compatibility** with existing array-based code

## Basic Usage

### Success Response

```php
use Trinavo\PaymentGateway\Models\CallbackResponse;

public function handleCallback(array $callbackData): CallbackResponse
{
    return CallbackResponse::success(
        orderCode: $callbackData['order_id'],
        transactionId: $callbackData['transaction_id'],
        message: 'Payment completed successfully'
    );
}
```

### Failure Response

```php
return CallbackResponse::failure(
    orderCode: $callbackData['order_id'],
    message: 'Payment failed: ' . $callbackData['error_message'],
    status: 'declined'
);
```

### Pending Response

```php
return CallbackResponse::pending(
    orderCode: $callbackData['order_id'],
    transactionId: $callbackData['transaction_id'],
    message: 'Payment is being processed'
);
```

## Constructor Parameters

The `CallbackResponse` constructor accepts the following parameters:

- `bool $success` - Whether the payment was successful
- `string $orderCode` - The order code/ID
- `?string $transactionId` - External transaction ID (optional)
- `?string $status` - Payment status (optional)
- `?string $message` - Human-readable message (optional)
- `array $additionalData` - Any additional data to store (optional)

## Static Factory Methods

### `success()`

Creates a successful payment response:

```php
CallbackResponse::success(
    orderCode: 'ORDER123',
    transactionId: 'TXN456',
    message: 'Payment completed successfully',
    additionalData: ['gateway_fee' => 2.50]
)
```

### `failure()`

Creates a failed payment response:

```php
CallbackResponse::failure(
    orderCode: 'ORDER123',
    message: 'Insufficient funds',
    status: 'declined',
    additionalData: ['error_code' => 'INSUFFICIENT_FUNDS']
)
```

### `pending()`

Creates a pending payment response:

```php
CallbackResponse::pending(
    orderCode: 'ORDER123',
    transactionId: 'TXN456',
    message: 'Payment is being processed',
    additionalData: ['estimated_completion' => '2024-01-15 10:30:00']
)
```

## Additional Data

You can include any additional data that should be stored with the payment:

```php
return CallbackResponse::success(
    orderCode: $orderCode,
    transactionId: $transactionId,
    additionalData: [
        'gateway_fee' => 2.50,
        'exchange_rate' => 1.25,
        'payment_method' => 'visa_ending_1234',
        'risk_score' => 'low'
    ]
);
```

## Backward Compatibility

The system maintains full backward compatibility:

- `CallbackResponse` objects are automatically converted to arrays when needed
- Existing array-based responses continue to work
- The `toArray()` method provides the legacy format

### Array Conversion

```php
$response = CallbackResponse::success('ORDER123', 'TXN456');
$array = $response->toArray();

// Results in:
[
    'success' => true,
    'order_code' => 'ORDER123',
    'transaction_id' => 'TXN456',
    'status' => 'completed',
    'message' => 'Payment completed successfully'
]
```

### Payment Data Extraction

The `getPaymentData()` method extracts only the relevant payment data for storage:

```php
$response = CallbackResponse::success(
    orderCode: 'ORDER123',
    transactionId: 'TXN456',
    additionalData: ['gateway_fee' => 2.50]
);

$paymentData = $response->getPaymentData();

// Results in:
[
    'transaction_id' => 'TXN456',
    'status' => 'completed',
    'message' => 'Payment completed successfully',
    'gateway_fee' => 2.50
]
```

## Migration from Arrays

### Before (Array-based)

```php
public function handleCallback(array $callbackData): array
{
    if ($callbackData['status'] === 'success') {
        return [
            'success' => true,
            'order_code' => $callbackData['order_id'],
            'transaction_id' => $callbackData['txn_id'],
            'message' => 'Payment completed'
        ];
    }
    
    return [
        'success' => false,
        'order_code' => $callbackData['order_id'],
        'message' => 'Payment failed'
    ];
}
```

### After (CallbackResponse)

```php
public function handleCallback(array $callbackData): CallbackResponse
{
    if ($callbackData['status'] === 'success') {
        return CallbackResponse::success(
            orderCode: $callbackData['order_id'],
            transactionId: $callbackData['txn_id'],
            message: 'Payment completed'
        );
    }
    
    return CallbackResponse::failure(
        orderCode: $callbackData['order_id'],
        message: 'Payment failed'
    );
}
```

## Benefits

1. **Type Safety**: IDE autocompletion and type checking
2. **Cleaner Code**: No more array key typos or missing fields
3. **Better Documentation**: Self-documenting method signatures
4. **Consistent Structure**: Standardized response format across all plugins
5. **Easier Testing**: Predictable object structure for unit tests
6. **Future-Proof**: Easy to extend with new features

## Examples in Practice

### Stripe Plugin

```php
public function handleCallback(array $callbackData): CallbackResponse
{
    $event = $this->verifyWebhookSignature($callbackData);
    
    if ($event->type === 'payment_intent.succeeded') {
        return CallbackResponse::success(
            orderCode: $event->data->object->metadata->order_code,
            transactionId: $event->data->object->id,
            message: 'Payment completed successfully',
            additionalData: [
                'stripe_fee' => $event->data->object->application_fee_amount,
                'payment_method' => $event->data->object->payment_method
            ]
        );
    }
    
    return CallbackResponse::failure(
        orderCode: $event->data->object->metadata->order_code,
        message: 'Payment failed: ' . $event->data->object->last_payment_error->message,
        additionalData: ['stripe_error_code' => $event->data->object->last_payment_error->code]
    );
}
```

### PayPal Plugin

```php
public function handleCallback(array $callbackData): CallbackResponse
{
    $paymentStatus = $callbackData['payment_status'] ?? '';
    $orderCode = $callbackData['custom'] ?? '';
    
    switch ($paymentStatus) {
        case 'Completed':
            return CallbackResponse::success(
                orderCode: $orderCode,
                transactionId: $callbackData['txn_id'],
                message: 'PayPal payment completed',
                additionalData: [
                    'paypal_fee' => $callbackData['mc_fee'],
                    'payer_email' => $callbackData['payer_email']
                ]
            );
            
        case 'Pending':
            return CallbackResponse::pending(
                orderCode: $orderCode,
                transactionId: $callbackData['txn_id'],
                message: 'PayPal payment pending: ' . $callbackData['pending_reason']
            );
            
        default:
            return CallbackResponse::failure(
                orderCode: $orderCode,
                message: 'PayPal payment failed or cancelled'
            );
    }
}
```
