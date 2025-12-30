<?php

namespace Trinavo\PaymentGateway\Services;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Trinavo\PaymentGateway\Models\CallbackResponse;
use Trinavo\PaymentGateway\Models\PaymentMethod;
use Trinavo\PaymentGateway\Models\PaymentOrder;

class PaymentGatewayService
{
    /**
     * Set the default locale for payment gateway routes
     *
     * @return $this
     */
    public function setDefaultLocale(string $locale): self
    {
        app(LocaleService::class)->setDefaultLocale($locale);

        return $this;
    }

    /**
     * Set the available locales for payment gateway routes
     *
     * @return $this
     */
    public function setAvailableLocales(array $locales): self
    {
        app(LocaleService::class)->setAvailableLocales($locales);

        return $this;
    }

    /**
     * Create a new payment order
     */
    public function createPaymentOrder(
        float $amount, ?string $currency = null,
        ?string $customerName = null, ?string $customerEmail = null,
        ?string $customerPhone = null, ?array $customerData = null,
        ?string $description = null, ?string $successCallback = null,
        ?string $failureCallback = null, ?string $successUrl = null,
        ?string $failureUrl = null, ?array $ignoredPlugins = null): PaymentOrder
    {
        $paymentOrder = PaymentOrder::create([
            'amount' => $amount,
            'currency' => $currency,
            'status' => PaymentOrder::STATUS_PENDING,
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'customer_phone' => $customerPhone,
            'customer_data' => $customerData,
            'description' => $description,
            'success_callback' => $successCallback,
            'failure_callback' => $failureCallback,
            'success_url' => $successUrl,
            'failure_url' => $failureUrl,
            'ignored_plugins' => $ignoredPlugins,
        ]);

        return $paymentOrder;
    }

    /**
     * Get payment URL for the order
     */
    public function getPaymentUrl(PaymentOrder $paymentOrder): string
    {
        $localeService = app(LocaleService::class);
        $currentLocale = app()->getLocale();
        $defaultLocale = $localeService->getDefaultLocale();

        // If we're in a non-default locale, use the localized route
        if ($currentLocale !== $defaultLocale && in_array($currentLocale, $localeService->getAvailableLocales())) {
            return URL::route('localized.payment-gateway.checkout',
                [
                    'locale' => $currentLocale,
                    'order' => $paymentOrder->order_code,
                ]);
        }

        // Otherwise, use the non-localized route
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
     * Get available payment methods for a specific payment order
     * This will filter out any plugins that are ignored for this order
     */
    public function getAvailablePaymentMethodsForOrder(PaymentOrder $paymentOrder): \Illuminate\Database\Eloquent\Collection
    {
        $paymentMethods = PaymentMethod::enabled()->ordered()->get();

        // Filter out ignored plugins
        $ignoredPlugins = $paymentOrder->getIgnoredPlugins();

        if (empty($ignoredPlugins)) {
            return $paymentMethods;
        }

        return $paymentMethods->filter(function (PaymentMethod $paymentMethod) use ($ignoredPlugins) {
            // Check if this payment method's plugin is in the ignored list
            // We need to check both the plugin class name and the payment method name
            $pluginClass = $paymentMethod->plugin_class;
            $pluginName = $paymentMethod->name;

            return ! in_array($pluginName, $ignoredPlugins) &&
                   ! in_array($pluginClass, $ignoredPlugins);
        });
    }

    /**
     * Process payment with selected method
     */
    public function processPayment(PaymentOrder $paymentOrder, PaymentMethod $paymentMethod)
    {
        // Update payment order with selected method
        $paymentOrder->update(['payment_method_id' => $paymentMethod->id]);

        // Note: We don't mark as processing here to allow users to refresh the payment page
        // The status will be updated by the plugin or callback when payment is actually submitted
        // This prevents users from being locked out while still viewing the payment form

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
     * Handle payment cancellation
     */
    public function handlePaymentCancellation(PaymentOrder $paymentOrder, array $paymentData = []): void
    {
        $paymentOrder->markAsCancelled($paymentData);

        // Execute failure callback if provided (cancellation uses failure callback)
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

        $result = $plugin->handleCallback($callbackData);

        // Convert CallbackResponse to array for backward compatibility
        if ($result instanceof CallbackResponse) {
            return $result->toArray();
        }

        // Legacy array format - return as is
        return $result;
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
