<?php

namespace Trinavo\PaymentGateway\Plugins\Offline;

use Trinavo\PaymentGateway\Configuration\TextField;
use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Models\RefundResponse;

class OfflinePaymentPlugin extends PaymentPluginInterface
{
    public function getName(): string
    {
        return 'Offline Payment Plugin';
    }

    public function getDescription(): string
    {
        return 'An offline payment plugin for cash on delivery scenarios. Shows a description to customers and provides a simple confirmation button.';
    }

    public function getConfigurationFields(): array
    {
        return [
            new TextField(
                name: 'description',
                label: 'Payment Description',
                required: false,
                default: 'Pay with cash when your order is delivered.',
                description: 'This description will be shown to customers on the payment page',
                placeholder: 'Enter payment instructions for customers...',
                maxLength: 500
            ),
        ];
    }

    public function validateConfiguration(): bool
    {
        // Offline plugin always has valid configuration
        return true;
    }

    public function processPayment(PaymentOrder $paymentOrder)
    {
        // Get the description from configuration
        $description = $this->paymentMethod->configuration['description'] ?? 'Pay with cash when your order is delivered.';

        // Return a view with offline payment confirmation
        return view('payment-gateway::plugins.offline-payment', [
            'paymentOrder' => $paymentOrder,
            'paymentMethod' => $this->paymentMethod,
            'description' => $description,
            'successUrl' => $this->getSuccessUrl($paymentOrder),
        ]);
    }

    public function handleCallback(array $callbackData): \Trinavo\PaymentGateway\Models\CallbackResponse
    {
        $orderCode = $callbackData['order_code'] ?? null;

        if (! $orderCode) {
            return \Trinavo\PaymentGateway\Models\CallbackResponse::failure(
                orderCode: 'unknown',
                message: 'Order code is required'
            );
        }

        // For offline payments, we always return success when callback is triggered
        return \Trinavo\PaymentGateway\Models\CallbackResponse::success(
            orderCode: $orderCode,
            transactionId: 'offline_'.uniqid(),
            message: 'Offline payment confirmed'
        );
    }

    public function refund(PaymentOrder $paymentOrder): RefundResponse
    {
        return RefundResponse::failure(
            orderCode: $paymentOrder->order_code,
            message: 'Refunds are not supported for this payment method'
        );
    }
}
