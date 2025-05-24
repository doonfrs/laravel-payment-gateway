<?php

namespace Trinavo\PaymentGateway\Contracts;

use Trinavo\PaymentGateway\Models\PaymentMethod;
use Trinavo\PaymentGateway\Models\PaymentOrder;

interface PaymentPluginInterface
{
    /**
     * Initialize the plugin with payment method configuration
     */
    public function __construct(PaymentMethod $paymentMethod);

    /**
     * Get the plugin name
     */
    public function getName(): string;

    /**
     * Get the plugin description
     */
    public function getDescription(): string;

    /**
     * Get required configuration fields for this plugin
     * Returns an array of field definitions
     */
    public function getConfigurationFields(): array;

    /**
     * Validate the plugin configuration
     */
    public function validateConfiguration(): bool;

    /**
     * Process payment for the given order
     * This method should either:
     * 1. Return a view response for inline payment forms
     * 2. Return a redirect response to external payment gateway
     * 3. Return a JSON response with payment URL for AJAX handling
     */
    public function processPayment(PaymentOrder $paymentOrder);

    /**
     * Handle payment callback/webhook from the payment gateway
     * This method should validate the callback and return payment status
     */
    public function handleCallback(array $callbackData): array;

    /**
     * Get the callback URL for this plugin
     */
    public function getCallbackUrl(): string;

    /**
     * Get the success URL for this plugin
     */
    public function getSuccessUrl(PaymentOrder $paymentOrder): string;

    /**
     * Get the failure URL for this plugin
     */
    public function getFailureUrl(PaymentOrder $paymentOrder): string;

    /**
     * Check if this plugin supports refunds
     */
    public function supportsRefunds(): bool;

    /**
     * Process a refund for the given payment order
     */
    public function processRefund(PaymentOrder $paymentOrder, ?float $amount = null): array;

    /**
     * Get payment status from external gateway
     */
    public function getPaymentStatus(PaymentOrder $paymentOrder): string;
}
