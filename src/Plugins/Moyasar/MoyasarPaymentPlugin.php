<?php

namespace Trinavo\PaymentGateway\Plugins\Moyasar;

use Illuminate\Support\Facades\URL;
use Trinavo\PaymentGateway\Configuration\TextField;
use Trinavo\PaymentGateway\Configuration\CheckboxField;
use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\PaymentOrder;

class MoyasarPaymentPlugin extends PaymentPluginInterface
{
    public function getName(): string
    {
        return 'Moyasar Payment Plugin';
    }

    public function getDescription(): string
    {
        return 'Integrate Moyasar card payments using their hosted payment form.';
    }

    public function getConfigurationFields(): array
    {
        return [
            new TextField(
                name: 'publishable_api_key',
                label: 'Publishable API Key',
                required: true,
                description: 'Your Moyasar publishable API key.'
            ),
            new TextField(
                name: 'secret_api_key',
                label: 'Secret API Key',
                required: true,
                description: 'Your Moyasar secret API key.'
            ),
            new CheckboxField(
                name: 'test_mode',
                label: 'Test Mode',
                default: true,
                description: 'Enable test mode for Moyasar payments.'
            ),
        ];
    }

    public function validateConfiguration(): bool
    {
        // Add validation logic if needed
        return true;
    }

    public function processPayment(PaymentOrder $paymentOrder)
    {
        // Return a view for the Moyasar payment form (to be implemented)
        return view('payment-gateway::plugins.moyasar-payment', [
            'publishable_api_key' => $this->paymentMethod->configuration['publishable_api_key'],
            'secret_api_key' => $this->paymentMethod->configuration['secret_api_key'],
            'paymentOrder' => $paymentOrder,
            'paymentMethod' => $this->paymentMethod,
            'callbackUrl' => $this->getCallbackUrl(),
            'successUrl' => $this->getSuccessUrl($paymentOrder),
            'failureUrl' => $this->getFailureUrl($paymentOrder),
        ]);
    }

    public function handleCallback(array $callbackData): array
    {
        // Handle callback from Moyasar (to be implemented)
        $status = $callbackData['status'] ?? 'failed';
        $orderCode = $callbackData['order_code'] ?? null;
        $paymentId = $callbackData['id'] ?? null;

        if (! $orderCode) {
            return [
                'success' => false,
                'message' => 'Order code is required',
            ];
        }

        return [
            'success' => $status === 'paid',
            'status' => $status,
            'order_code' => $orderCode,
            'transaction_id' => $paymentId,
            'message' => $status === 'paid' ? 'Payment completed successfully' : 'Payment failed',
        ];
    }

    public function getCallbackUrl(): string
    {
        return URL::route('payment-gateway.callback', ['plugin' => 'moyasar']);
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
        // Moyasar supports refunds via API, but not implemented here
        return false;
    }

    public function processRefund(PaymentOrder $paymentOrder, ?float $amount = null): array
    {
        // Not implemented
        return [
            'success' => false,
            'message' => 'Refunds are not supported in this plugin.'
        ];
    }

    public function getPaymentStatus(PaymentOrder $paymentOrder): string
    {
        // Not implemented: would require API call to Moyasar
        return $paymentOrder->status;
    }
} 