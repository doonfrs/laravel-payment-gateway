<?php

namespace Trinavo\PaymentGateway\Plugins\Dummy;

use Trinavo\PaymentGateway\Configuration\CheckboxField;
use Trinavo\PaymentGateway\Configuration\NumberField;
use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\PaymentOrder;

class DummyPaymentPlugin extends PaymentPluginInterface
{
    public function getName(): string
    {
        return 'Dummy Payment Plugin';
    }

    public function getDescription(): string
    {
        return 'A dummy payment plugin for testing purposes. Provides buttons to simulate success, failure, and callback scenarios.';
    }

    public function getConfigurationFields(): array
    {
        return [
            new CheckboxField(
                name: 'test_mode',
                label: 'Test Mode',
                default: true,
                description: 'Enable test mode for dummy payments'
            ),

            new NumberField(
                name: 'delay_seconds',
                label: 'Delay Seconds',
                required: false,
                default: 0,
                description: 'Simulate processing delay in seconds',
                min: 0,
                max: 60,
                placeholder: '0'
            ),
        ];
    }

    public function validateConfiguration(): bool
    {
        // Dummy plugin always has valid configuration
        return true;
    }

    public function processPayment(PaymentOrder $paymentOrder)
    {
        // Return a view with dummy payment options
        return view('payment-gateway::plugins.dummy-payment', [
            'paymentOrder' => $paymentOrder,
            'paymentMethod' => $this->paymentMethod,
            'successUrl' => $this->getSuccessUrl($paymentOrder),
            'failureUrl' => $this->getFailureUrl($paymentOrder),
            'callbackUrl' => $this->getCallbackUrl(),
        ]);
    }

    public function handleCallback(array $callbackData): \Trinavo\PaymentGateway\Models\CallbackResponse
    {
        $status = $callbackData['status'] ?? 'failed';
        $orderCode = $callbackData['order_code'] ?? null;

        if (! $orderCode) {
            return \Trinavo\PaymentGateway\Models\CallbackResponse::failure(
                orderCode: 'unknown',
                message: 'Order code is required'
            );
        }

        if ($status === 'success') {
            return \Trinavo\PaymentGateway\Models\CallbackResponse::success(
                orderCode: $orderCode,
                transactionId: 'dummy_'.uniqid(),
                message: 'Payment completed successfully'
            );
        }

        return \Trinavo\PaymentGateway\Models\CallbackResponse::failure(
            orderCode: $orderCode,
            message: 'Payment failed',
            status: $status
        );
    }
}
