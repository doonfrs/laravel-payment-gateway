<?php

namespace Trinavo\PaymentGateway\Services;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Trinavo\PaymentGateway\Models\PaymentMethod;
use Trinavo\PaymentGateway\Models\PaymentOrder;

class PaymentGatewayService
{
    /**
     * Create a new payment order
     */
    public function createPaymentOrder(
        float $amount, string $currency = 'USD',
        ?string $customerName = null, ?string $customerEmail = null,
        ?string $customerPhone = null, ?array $customerData = null,
        ?string $description = null, ?string $successCallback = null,
        ?string $failureCallback = null, ?string $successUrl = null,
        ?string $failureUrl = null): PaymentOrder
    {
        $paymentOrder = PaymentOrder::create([
            'amount' => $amount,
            'currency' => $currency,
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'customer_phone' => $customerPhone,
            'customer_data' => $customerData,
            'description' => $description,
            'success_callback' => $successCallback,
            'failure_callback' => $failureCallback,
            'success_url' => $successUrl,
            'failure_url' => $failureUrl,
        ]);

        return $paymentOrder;
    }

    /**
     * Get payment URL for the order
     */
    public function getPaymentUrl(PaymentOrder $paymentOrder): string
    {
        return URL::route('payment-gateway.checkout',
            [
                'order' => $paymentOrder->order_code,
            ]);
    }

    /**
     * Get available payment methods
     */
    public function getAvailablePaymentMethods(): \Illuminate\Database\Eloquent\Collection
    {
        return PaymentMethod::enabled()->ordered()->get();
    }

    /**
     * Process payment with selected method
     */
    public function processPayment(PaymentOrder $paymentOrder, PaymentMethod $paymentMethod)
    {
        // Update payment order with selected method
        $paymentOrder->update(['payment_method_id' => $paymentMethod->id]);
        $paymentOrder->markAsProcessing();

        // Get plugin instance and process payment
        $plugin = $paymentMethod->getPluginInstance();

        return $plugin->processPayment($paymentOrder);
    }

    /**
     * Handle payment success
     */
    public function handlePaymentSuccess(PaymentOrder $paymentOrder, array $paymentData = []): void
    {
        $paymentOrder->markAsCompleted($paymentData);

        // Execute success callback if provided
        if ($paymentOrder->success_callback) {
            $this->executeCallback($paymentOrder->success_callback, $paymentOrder);
        }
    }

    /**
     * Handle payment failure
     */
    public function handlePaymentFailure(PaymentOrder $paymentOrder, array $paymentData = []): void
    {
        $paymentOrder->markAsFailed($paymentData);

        // Execute failure callback if provided
        if ($paymentOrder->failure_callback) {
            $this->executeCallback($paymentOrder->failure_callback, $paymentOrder);
        }
    }

    /**
     * Execute PHP callback code
     */
    protected function executeCallback(string $callback, PaymentOrder $paymentOrder): void
    {
        // Create a safe environment for callback execution
        $order = $paymentOrder;
        if (! Str::endsWith($callback, ';')) {
            $callback .= ';';
        }
        eval($callback);

    }

    /**
     * Handle plugin callback
     */
    public function handlePluginCallback(string $pluginClass, array $callbackData): array
    {
        // Find payment method by plugin class
        $paymentMethod = PaymentMethod::where('plugin_class', $pluginClass)->first();

        if (! $paymentMethod) {
            throw new \Exception("Payment method not found for plugin: {$pluginClass}");
        }

        $plugin = $paymentMethod->getPluginInstance();

        return $plugin->handleCallback($callbackData);
    }

    /**
     * Get payment order by code
     */
    public function getPaymentOrderByCode(string $orderCode): ?PaymentOrder
    {
        return PaymentOrder::where('order_code', $orderCode)->first();
    }

    /**
     * Register a new payment plugin
     */
    public function registerPaymentMethod(array $data): PaymentMethod
    {
        return PaymentMethod::create($data);
    }
}
