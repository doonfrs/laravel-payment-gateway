<?php

namespace Trinavo\PaymentGateway\Plugins\Offline;

use Trinavo\PaymentGateway\Configuration\TextField;
use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Models\RefundResponse;

class OfflinePaymentPlugin extends PaymentPluginInterface
{
    public static function getLogoUrl(): string
    {
        return asset('vendor/payment-gateway/imgs/offline.png');
    }

    public function getName(): string
    {
        return __('Manual Payment');
    }

    public function getDescription(): string
    {
        return __('manual_payment_description');
    }

    public function getConfigurationFields(): array
    {
        return [
            new TextField(
                name: 'description',
                label: __('payment_instructions_label'),
                required: false,
                default: __('manual_payment_default_instructions'),
                description: __('payment_instructions_helper'),
                placeholder: __('payment_instructions_placeholder'),
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
