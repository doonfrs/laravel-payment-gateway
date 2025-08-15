<?php

namespace Trinavo\PaymentGateway\Plugins\Tabby;

use Trinavo\PaymentGateway\Configuration\CheckboxField;
use Trinavo\PaymentGateway\Configuration\TextField;
use Trinavo\PaymentGateway\Configuration\SelectField;
use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\PaymentOrder;

class TabbyPaymentPlugin extends PaymentPluginInterface
{
    public function getName(): string
    {
        return __('Tabby Payment Plugin');
    }

    public function getDescription(): string
    {
        return __('Integrate Tabby Buy Now Pay Later (BNPL) payment solution. Allows customers to pay in 4 interest-free installments.');
    }

    public function getConfigurationFields(): array
    {
        return [
            new TextField(
                name: 'public_key_sandbox',
                label: 'Public Key (Sandbox)',
                required: true,
                encrypted: true,
                description: 'Your Tabby public API key for sandbox environment.'
            ),
            new TextField(
                name: 'secret_key_sandbox',
                label: 'Secret Key (Sandbox)',
                required: true,
                encrypted: true,
                description: 'Your Tabby secret API key for sandbox environment.'
            ),

            new TextField(
                name: 'public_key_production',
                label: 'Public Key (Production)',
                required: false,
                encrypted: true,
                description: 'Your Tabby public API key for production environment.'
            ),
            new TextField(
                name: 'secret_key_production',
                label: 'Secret Key (Production)',
                required: false,
                encrypted: true,
                description: 'Your Tabby secret API key for production environment.'
            ),

            new CheckboxField(
                name: 'sandbox_mode',
                label: 'Sandbox Mode',
                default: true,
                description: 'Enable sandbox mode for Tabby payments.'
            ),

            new SelectField(
                name: 'payment_product',
                label: 'Payment Product',
                required: true,
                options: [
                    'installments' => 'Pay in 4 Installments',
                    'monthly' => 'Monthly Payments',
                    'credit' => 'Credit Card Payments'
                ],
                default: 'installments',
                description: 'Select the Tabby payment product to offer to customers.'
            ),

            new SelectField(
                name: 'supported_currency',
                label: 'Supported Currency',
                required: true,
                options: [
                    'AED' => 'UAE Dirham (AED)',
                    'SAR' => 'Saudi Riyal (SAR)',
                    'KWD' => 'Kuwaiti Dinar (KWD)',
                    'BHD' => 'Bahraini Dinar (BHD)',
                    'QAR' => 'Qatari Riyal (QAR)',
                    'EGP' => 'Egyptian Pound (EGP)'
                ],
                default: 'AED',
                description: 'Select the currency for Tabby payments.'
            ),
        ];
    }

    public function validateConfiguration(): bool
    {
        $sandboxMode = $this->paymentMethod->getSetting('sandbox_mode', true);
        
        if ($sandboxMode) {
            return !empty($this->paymentMethod->getSetting('public_key_sandbox')) && 
                   !empty($this->paymentMethod->getSetting('secret_key_sandbox'));
        }
        
        return !empty($this->paymentMethod->getSetting('public_key_production')) && 
               !empty($this->paymentMethod->getSetting('secret_key_production'));
    }

    public function processPayment(PaymentOrder $paymentOrder)
    {
        $sandboxMode = $this->paymentMethod->getSetting('sandbox_mode', true);
        
        if ($sandboxMode) {
            $publicKey = $this->paymentMethod->getSetting('public_key_sandbox');
            $secretKey = $this->paymentMethod->getSetting('secret_key_sandbox');
            $apiUrl = 'https://api-sandbox.tabby.ai';
        } else {
            $publicKey = $this->paymentMethod->getSetting('public_key_production');
            $secretKey = $this->paymentMethod->getSetting('secret_key_production');
            $apiUrl = 'https://api.tabby.ai';
        }

        $currency = $this->paymentMethod->getSetting('supported_currency', 'AED');
        $paymentProduct = $this->paymentMethod->getSetting('payment_product', 'installments');
        
        // Prepare the checkout session data
        $checkoutData = [
            'payment' => [
                'amount' => (string) round($paymentOrder->amount, 2),
                'currency' => $currency,
                'description' => $paymentOrder->description ?: 'Order #' . $paymentOrder->order_code,
                'buyer' => [
                    'phone' => $paymentOrder->customer_phone ?: '',
                    'email' => $paymentOrder->customer_email ?: '',
                    'name' => $paymentOrder->customer_name ?: '',
                ],
                'buyer_history' => [
                    'registered_since' => now()->subYear()->toISOString(),
                    'loyalty_level' => 0,
                ],
                'order' => [
                    'tax_amount' => '0.00',
                    'shipping_amount' => '0.00',
                    'discount_amount' => '0.00',
                    'updated_at' => now()->toISOString(),
                    'reference_id' => $paymentOrder->order_code,
                    'items' => [
                        [
                            'title' => $paymentOrder->description ?: 'Payment',
                            'description' => 'Payment for order #' . $paymentOrder->order_code,
                            'quantity' => 1,
                            'unit_price' => (string) round($paymentOrder->amount, 2),
                            'discount_amount' => '0.00',
                            'reference_id' => $paymentOrder->order_code,
                            'image_url' => '',
                            'product_url' => '',
                            'category' => 'general'
                        ]
                    ]
                ],
                'order_history' => [
                    [
                        'purchased_at' => now()->subMonth()->toISOString(),
                        'amount' => (string) round($paymentOrder->amount, 2),
                        'payment_method' => 'card',
                        'status' => 'new'
                    ]
                ],
                'meta' => [
                    'order_id' => $paymentOrder->order_code,
                    'customer' => $paymentOrder->customer_data ?? []
                ]
            ],
            'lang' => app()->getLocale(),
            'merchant_code' => $publicKey,
            'merchant_urls' => [
                'success' => $this->getSuccessUrl($paymentOrder),
                'cancel' => $this->getFailureUrl($paymentOrder),
                'failure' => $this->getFailureUrl($paymentOrder)
            ]
        ];

        return view('payment-gateway::plugins.tabby-payment', [
            'paymentOrder' => $paymentOrder,
            'paymentMethod' => $this->paymentMethod,
            'publicKey' => $publicKey,
            'secretKey' => $secretKey,
            'apiUrl' => $apiUrl,
            'checkoutData' => $checkoutData,
            'callbackUrl' => $this->getCallbackUrl(),
            'successUrl' => $this->getSuccessUrl($paymentOrder),
            'failureUrl' => $this->getFailureUrl($paymentOrder),
            'sandboxMode' => $sandboxMode,
            'currency' => $currency,
            'paymentProduct' => $paymentProduct,
        ]);
    }

    public function handleCallback(array $callbackData): \Trinavo\PaymentGateway\Models\CallbackResponse
    {
        $status = $callbackData['status'] ?? 'failed';
        $orderCode = $callbackData['order_code'] ?? null;
        $paymentId = $callbackData['payment_id'] ?? null;
        $tabbyId = $callbackData['tabby_id'] ?? null;
        $message = $callbackData['message'] ?? null;

        if (!$orderCode) {
            return \Trinavo\PaymentGateway\Models\CallbackResponse::failure(
                orderCode: 'unknown',
                message: 'Order code is required'
            );
        }

        // Tabby payment statuses: AUTHORIZED, CLOSED, EXPIRED, REJECTED, CREATED
        if (in_array($status, ['AUTHORIZED', 'CLOSED'])) {
            return \Trinavo\PaymentGateway\Models\CallbackResponse::success(
                orderCode: $orderCode,
                transactionId: $tabbyId ?: $paymentId,
                message: $message ?: 'Payment completed successfully via Tabby'
            );
        }

        return \Trinavo\PaymentGateway\Models\CallbackResponse::failure(
            orderCode: $orderCode,
            message: $message ?: 'Payment failed or was rejected',
            status: $status,
            additionalData: [
                'tabby_payment_id' => $paymentId,
                'tabby_id' => $tabbyId,
                'tabby_status' => $status
            ]
        );
    }

    /**
     * Get the API base URL based on sandbox mode
     */
    private function getApiUrl(): string
    {
        $sandboxMode = $this->paymentMethod->getSetting('sandbox_mode', true);
        return $sandboxMode ? 'https://api-sandbox.tabby.ai' : 'https://api.tabby.ai';
    }

    /**
     * Get API headers for Tabby requests
     */
    private function getApiHeaders(): array
    {
        $sandboxMode = $this->paymentMethod->getSetting('sandbox_mode', true);
        $secretKey = $sandboxMode 
            ? $this->paymentMethod->getSetting('secret_key_sandbox')
            : $this->paymentMethod->getSetting('secret_key_production');

        return [
            'Authorization' => 'Bearer ' . $secretKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }
}
