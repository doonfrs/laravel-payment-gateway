<?php

namespace Trinavo\PaymentGateway\Plugins\Moyasar;

use Trinavo\PaymentGateway\Configuration\CheckboxField;
use Trinavo\PaymentGateway\Configuration\TextField;
use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Models\RefundResponse;

class MoyasarPaymentPlugin extends PaymentPluginInterface
{
    public function getName(): string
    {
        return __('Moyasar Payment Plugin');
    }

    public function getDescription(): string
    {
        return __('Integrate Moyasar card payments using their hosted payment form.');
    }

    public function getConfigurationFields(): array
    {
        return [
            new TextField(
                name: 'publishable_api_key_demo',
                label: 'Publishable API Key (Demo)',
                required: true,
                encrypted: true,
                description: 'Your Moyasar publishable API key (Demo).'
            ),
            new TextField(
                name: 'secret_api_key_demo',
                label: 'Secret API Key (Demo)',
                required: true,
                encrypted: true,
                description: 'Your Moyasar secret API key (Demo).'
            ),

            new TextField(
                name: 'publishable_api_key',
                label: 'Publishable API Key',
                required: true,
                encrypted: true,
                description: 'Your Moyasar publishable API key.'
            ),
            new TextField(
                name: 'secret_api_key',
                label: 'Secret API Key',
                required: true,
                encrypted: true,
                description: 'Your Moyasar secret API key.'
            ),

            new CheckboxField(
                name: 'demo_mode',
                label: 'Demo Mode',
                default: true,
                description: 'Enable demo mode for Moyasar payments.'
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
        $amount = round($paymentOrder->amount, 2) * 100;

        $callbackUrl = $this->getCallbackUrl();
        $callbackUrl .= '?order_code='.$paymentOrder->order_code;

        $demo = $this->paymentMethod->getSetting('demo_mode');

        if ($demo) {
            $publishableApiKey = $this->paymentMethod->getSetting('publishable_api_key_demo');
            $secretApiKey = $this->paymentMethod->getSetting('secret_api_key_demo');
        } else {
            $publishableApiKey = $this->paymentMethod->getSetting('publishable_api_key');
            $secretApiKey = $this->paymentMethod->getSetting('secret_api_key');
        }

        return view('payment-gateway::plugins.moyasar-payment', [
            'publishable_api_key' => $publishableApiKey,
            'secret_api_key' => $secretApiKey,
            'amount' => $amount,
            'paymentOrder' => $paymentOrder,
            'paymentMethod' => $this->paymentMethod,
            'callbackUrl' => $callbackUrl,
            'successUrl' => $this->getSuccessUrl($paymentOrder),
            'failureUrl' => $this->getFailureUrl($paymentOrder),
        ]);
    }

    public function handleCallback(array $callbackData): \Trinavo\PaymentGateway\Models\CallbackResponse
    {

        // Handle callback from Moyasar (to be implemented)
        $status = $callbackData['status'] ?? 'failed';
        $orderCode = $callbackData['order_code'] ?? null;
        $paymentId = $callbackData['id'] ?? null;
        $message = $callbackData['message'] ?? null;

        if (! $orderCode) {
            return \Trinavo\PaymentGateway\Models\CallbackResponse::failure(
                orderCode: 'unknown',
                message: 'Order code is required'
            );
        }

        if ($status === 'paid') {
            return \Trinavo\PaymentGateway\Models\CallbackResponse::success(
                orderCode: $orderCode,
                transactionId: $paymentId,
                message: $message
            );
        }

        return \Trinavo\PaymentGateway\Models\CallbackResponse::failure(
            orderCode: $orderCode,
            message: 'Payment failed',
            status: $status,
            additionalData: ['moyasar_payment_id' => $paymentId]
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
