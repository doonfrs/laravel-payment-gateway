<?php

namespace Trinavo\PaymentGateway\Plugins\Offline;

use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Models\RefundResponse;

class OfflinePaymentPlugin extends PaymentPluginInterface
{
    public static function getLogoUrl(): string
    {
        return asset('vendor/payment-gateway/imgs/offline.svg');
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
        return [];
    }

    public function validateConfiguration(): bool
    {
        // Offline plugin always has valid configuration
        return true;
    }

    public function processPayment(PaymentOrder $paymentOrder)
    {
        $description = $this->paymentMethod->getLocalizedDescription();

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

        // before_submit validation: this plugin commits the order without
        // an external gateway, so this is the host app's last chance to
        // veto (e.g. cart-changed). The package's callback handler forwards
        // the message to failure_url via session flash.
        $paymentOrder = \Trinavo\PaymentGateway\Models\PaymentOrder::where('order_code', $orderCode)->first();
        if ($paymentOrder) {
            $validation = app(\Trinavo\PaymentGateway\Services\PaymentGatewayService::class)
                ->runValidation($paymentOrder, 'before_submit');

            if ($validation !== true) {
                $message = is_string($validation) ? $validation : __('Payment validation failed. Please review your order and try again.');

                return \Trinavo\PaymentGateway\Models\CallbackResponse::cancelled($orderCode, $message);
            }
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
