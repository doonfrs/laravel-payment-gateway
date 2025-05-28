<?php

namespace App\PaymentPlugins;

use Trinavo\PaymentGateway\Configuration\CheckboxField;
use Trinavo\PaymentGateway\Configuration\PasswordField;
use Trinavo\PaymentGateway\Configuration\SelectField;
use Trinavo\PaymentGateway\Configuration\TextField;
use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\CallbackResponse;
use Trinavo\PaymentGateway\Models\PaymentOrder;

/**
 * Example Stripe Payment Plugin
 *
 * This is an example implementation showing how to create a custom payment plugin.
 * To use this plugin:
 *
 * 1. Add it to your config/payment-gateway.php:
 *    'stripe' => \App\PaymentPlugins\ExampleStripePlugin::class,
 *
 * 2. Create a payment method record:
 *    PaymentMethod::create([
 *        'name' => 'stripe',
 *        'plugin_class' => \App\PaymentPlugins\ExampleStripePlugin::class,
 *        'display_name' => 'Credit Card (Stripe)',
 *        'enabled' => true,
 *        'sort_order' => 1,
 *    ]);
 *
 * 3. Configure the plugin settings:
 *    $paymentMethod->setSetting('publishable_key', env('STRIPE_PUBLISHABLE_KEY'));
 *    $paymentMethod->setSetting('secret_key', env('STRIPE_SECRET_KEY'), true);
 */
class ExampleStripePlugin extends PaymentPluginInterface
{
    public function getName(): string
    {
        return 'Stripe Payment Gateway';
    }

    public function getDescription(): string
    {
        return 'Accept credit card payments via Stripe';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getConfigurationFields(): array
    {
        return [
            new TextField(
                name: 'publishable_key',
                label: 'Publishable Key',
                required: true,
                description: 'Your Stripe publishable key',
                placeholder: 'pk_test_...'
            ),

            new PasswordField(
                name: 'secret_key',
                label: 'Secret Key',
                required: true,
                description: 'Your Stripe secret key (will be encrypted)',
                placeholder: 'sk_test_...'
            ),

            new PasswordField(
                name: 'webhook_secret',
                label: 'Webhook Secret',
                required: false,
                description: 'Stripe webhook endpoint secret (optional)',
                placeholder: 'whsec_...'
            ),

            new SelectField(
                name: 'environment',
                label: 'Environment',
                options: [
                    'sandbox' => 'Sandbox (Testing)',
                    'live' => 'Live (Production)',
                ],
                required: true,
                default: 'sandbox',
                description: 'Select the environment for this gateway'
            ),

            new CheckboxField(
                name: 'capture_immediately',
                label: 'Capture Immediately',
                default: true,
                description: 'Capture payments immediately or authorize only'
            ),
        ];
    }

    public function validateConfiguration(): bool
    {
        $publishableKey = $this->paymentMethod->getSetting('publishable_key');
        $secretKey = $this->paymentMethod->getSetting('secret_key');

        return ! empty($publishableKey) && ! empty($secretKey);
    }

    public function processPayment(PaymentOrder $paymentOrder)
    {
        // In a real implementation, you would:
        // 1. Create a Stripe Payment Intent
        // 2. Return a view with Stripe Elements
        // 3. Handle the payment confirmation

        // For this example, we'll just redirect to a dummy success
        return redirect()->route('payment-gateway.dummy-action', [
            'order' => $paymentOrder->order_code,
            'action' => 'success',
        ]);
    }

    public function handleCallback(array $callbackData): CallbackResponse
    {
        // In a real implementation, you would:
        // 1. Verify the webhook signature
        // 2. Parse the Stripe event
        // 3. Update the payment status accordingly

        return CallbackResponse::success(
            orderCode: $callbackData['order_code'] ?? '',
            transactionId: 'stripe_'.uniqid(),
            message: 'Payment completed successfully',
            additionalData: $callbackData
        );
    }

    public function getCallbackUrl(): string
    {
        return route('payment-gateway.callback', ['plugin' => 'stripe']);
    }

    public function getSuccessUrl(PaymentOrder $paymentOrder): string
    {
        return route('payment-gateway.success', ['order' => $paymentOrder->order_code]);
    }

    public function getFailureUrl(PaymentOrder $paymentOrder): string
    {
        return route('payment-gateway.failure', ['order' => $paymentOrder->order_code]);
    }

    public function supportsRefunds(): bool
    {
        return true;
    }

    public function processRefund(PaymentOrder $paymentOrder, ?float $amount = null): array
    {
        // In a real implementation, you would call Stripe's refund API
        return [
            'success' => false,
            'error' => 'Refund functionality not implemented in this example',
        ];
    }

    public function getPaymentStatus(PaymentOrder $paymentOrder): string
    {
        // In a real implementation, you would query Stripe's API
        return 'unknown';
    }

    public function getPaymentView(): ?string
    {
        // Return null to use default redirect behavior
        // Or return a view name for custom payment forms
        return null;
    }
}
