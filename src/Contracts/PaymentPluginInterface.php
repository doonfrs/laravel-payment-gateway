<?php

namespace Trinavo\PaymentGateway\Contracts;

use Trinavo\PaymentGateway\Models\PaymentMethod;
use Trinavo\PaymentGateway\Models\PaymentOrder;

abstract class PaymentPluginInterface
{
    protected PaymentMethod $paymentMethod;

    /**
     * Initialize the plugin with payment method configuration
     */
    public function __construct(PaymentMethod $paymentMethod)
    {
        $this->paymentMethod = $paymentMethod;
    }

    /**
     * Get the plugin name
     */
    abstract public function getName(): string;

    /**
     * Get the plugin description
     */
    abstract public function getDescription(): string;

    /**
     * Get required configuration fields for this plugin
     * Returns an array of ConfigurationField objects or legacy array format
     */
    abstract public function getConfigurationFields(): array;

    /**
     * Validate the plugin configuration
     */
    abstract public function validateConfiguration(): bool;

    /**
     * Process payment for the given order
     * This method should either:
     * 1. Return a view response for inline payment forms
     * 2. Return a redirect response to external payment gateway
     * 3. Return a JSON response with payment URL for AJAX handling
     */
    abstract public function processPayment(PaymentOrder $paymentOrder);

    /**
     * Handle payment callback/webhook from the payment gateway
     * This method should validate the callback and return payment status
     */
    abstract public function handleCallback(array $callbackData): \Trinavo\PaymentGateway\Models\CallbackResponse;

    /**
     * Get the callback URL for this plugin
     */
    abstract public function getCallbackUrl(): string;

    /**
     * Get the success URL for this plugin
     */
    abstract public function getSuccessUrl(PaymentOrder $paymentOrder): string;

    /**
     * Get the failure URL for this plugin
     */
    abstract public function getFailureUrl(PaymentOrder $paymentOrder): string;

    /**
     * Check if this plugin supports refunds
     */
    abstract public function supportsRefunds(): bool;

    /**
     * Process a refund for the given payment order
     */
    abstract public function processRefund(PaymentOrder $paymentOrder, ?float $amount = null): array;

    /**
     * Get payment status from external gateway
     */
    abstract public function getPaymentStatus(PaymentOrder $paymentOrder): string;

    /**
     * Convert configuration fields to array format
     * Supports both ConfigurationField objects and legacy arrays
     */
    protected function fieldsToArray(array $fields): array
    {
        return array_map(function ($field) {
            if ($field instanceof ConfigurationField) {
                return $field->toArray();
            }

            // Legacy array format - return as is
            return $field;
        }, $fields);
    }

    /**
     * Get configuration fields as array (for backward compatibility)
     */
    public function getConfigurationFieldsArray(): array
    {
        return $this->fieldsToArray($this->getConfigurationFields());
    }
}
