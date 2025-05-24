<?php

namespace Trinavo\PaymentGateway\Plugins\Dummy;

use Illuminate\Support\Facades\URL;
use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\PaymentMethod;
use Trinavo\PaymentGateway\Models\PaymentOrder;

class DummyPaymentPlugin implements PaymentPluginInterface
{
    protected PaymentMethod $paymentMethod;

    public function __construct(PaymentMethod $paymentMethod)
    {
        $this->paymentMethod = $paymentMethod;
    }

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
            [
                'name' => 'test_mode',
                'label' => 'Test Mode',
                'type' => 'boolean',
                'required' => false,
                'default' => true,
                'description' => 'Enable test mode for dummy payments',
            ],
            [
                'name' => 'delay_seconds',
                'label' => 'Delay Seconds',
                'type' => 'number',
                'required' => false,
                'default' => 0,
                'description' => 'Simulate processing delay in seconds',
            ],
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

    public function handleCallback(array $callbackData): array
    {
        $status = $callbackData['status'] ?? 'failed';
        $orderCode = $callbackData['order_code'] ?? null;

        if (! $orderCode) {
            return [
                'success' => false,
                'message' => 'Order code is required',
            ];
        }

        return [
            'success' => $status === 'success',
            'status' => $status,
            'order_code' => $orderCode,
            'transaction_id' => 'dummy_'.uniqid(),
            'message' => $status === 'success' ? 'Payment completed successfully' : 'Payment failed',
        ];
    }

    public function getCallbackUrl(): string
    {
        return URL::route('payment-gateway.callback', ['plugin' => 'dummy']);
    }

    public function getSuccessUrl(PaymentOrder $paymentOrder): string
    {
        return $paymentOrder->success_url ?? URL::route('payment-gateway.success', ['order' => $paymentOrder->order_code]);
    }

    public function getFailureUrl(PaymentOrder $paymentOrder): string
    {
        return $paymentOrder->failure_url ?? URL::route('payment-gateway.failure', ['order' => $paymentOrder->order_code]);
    }

    public function supportsRefunds(): bool
    {
        return true;
    }

    public function processRefund(PaymentOrder $paymentOrder, ?float $amount = null): array
    {
        $refundAmount = $amount ?? $paymentOrder->amount;

        return [
            'success' => true,
            'refund_id' => 'dummy_refund_'.uniqid(),
            'amount' => $refundAmount,
            'message' => 'Refund processed successfully (dummy)',
        ];
    }

    public function getPaymentStatus(PaymentOrder $paymentOrder): string
    {
        // For dummy plugin, return the current status
        return $paymentOrder->status;
    }
}
