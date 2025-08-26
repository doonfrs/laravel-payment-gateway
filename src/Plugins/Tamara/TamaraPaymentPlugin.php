<?php

namespace Trinavo\PaymentGateway\Plugins\Tamara;

use Illuminate\Support\Facades\Log;
use Trinavo\PaymentGateway\Configuration\CheckboxField;
use Trinavo\PaymentGateway\Configuration\SelectField;
use Trinavo\PaymentGateway\Configuration\TextField;
use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\PaymentOrder;

class TamaraPaymentPlugin extends PaymentPluginInterface
{
    public function getName(): string
    {
        return __('Tamara Payment Plugin');
    }

    public function getDescription(): string
    {
        return __('Integrate Tamara Buy Now Pay Later (BNPL) payment solution. Allows customers to pay in installments with Tamara.');
    }

    public function getConfigurationFields(): array
    {
        return [
            new TextField(
                name: 'api_token_sandbox',
                label: 'API Token (Sandbox)',
                required: true,
                encrypted: true,
                description: 'Your Tamara API token for sandbox environment.'
            ),
            new TextField(
                name: 'notification_token_sandbox',
                label: 'Notification Token (Sandbox)',
                required: true,
                encrypted: true,
                description: 'Your Tamara notification token for sandbox environment (for webhook authentication).'
            ),
            new TextField(
                name: 'public_key_sandbox',
                label: 'Public Key (Sandbox)',
                required: false,
                encrypted: true,
                description: 'Your Tamara public key for sandbox environment (for widgets).'
            ),

            new TextField(
                name: 'api_token_production',
                label: 'API Token (Production)',
                required: false,
                encrypted: true,
                description: 'Your Tamara API token for production environment.'
            ),
            new TextField(
                name: 'notification_token_production',
                label: 'Notification Token (Production)',
                required: false,
                encrypted: true,
                description: 'Your Tamara notification token for production environment.'
            ),
            new TextField(
                name: 'public_key_production',
                label: 'Public Key (Production)',
                required: false,
                encrypted: true,
                description: 'Your Tamara public key for production environment.'
            ),

            new CheckboxField(
                name: 'sandbox_mode',
                label: 'Sandbox Mode',
                default: true,
                description: 'Enable sandbox mode for Tamara payments.'
            ),

            new SelectField(
                name: 'supported_currency',
                label: 'Supported Currency',
                required: true,
                options: [
                    'SAR' => 'Saudi Riyal (SAR)',
                    'AED' => 'UAE Dirham (AED)',
                    'KWD' => 'Kuwaiti Dinar (KWD)',
                    'BHD' => 'Bahraini Dinar (BHD)',
                    'QAR' => 'Qatari Riyal (QAR)',
                ],
                default: 'SAR',
                description: 'Select the currency for Tamara payments.'
            ),

            new SelectField(
                name: 'payment_type',
                label: 'Payment Type',
                required: true,
                options: [
                    'PAY_BY_INSTALMENTS' => 'Pay by Instalments',
                    'PAY_BY_LATER' => 'Pay Later',
                    'PAY_BY_MONTH' => 'Pay by Month',
                    'PAY_NOW' => 'Pay Now',
                ],
                default: 'PAY_BY_INSTALMENTS',
                description: 'Select the default payment type for Tamara.'
            ),

            new TextField(
                name: 'merchant_code',
                label: 'Merchant Code',
                required: false,
                encrypted: false,
                description: 'Your Tamara merchant code (optional).'
            ),
        ];
    }

    public function validateConfiguration(): bool
    {
        $sandboxMode = $this->paymentMethod->getSetting('sandbox_mode', true);

        if ($sandboxMode) {
            return ! empty($this->paymentMethod->getSetting('api_token_sandbox')) &&
                   ! empty($this->paymentMethod->getSetting('notification_token_sandbox'));
        }

        return ! empty($this->paymentMethod->getSetting('api_token_production')) &&
               ! empty($this->paymentMethod->getSetting('notification_token_production'));
    }

    public function processPayment(PaymentOrder $paymentOrder)
    {
        // Log payment processing start
        Log::info('Tamara Payment Processing Started', [
            'order_code' => $paymentOrder->order_code,
            'amount' => $paymentOrder->amount,
            'customer_email' => $paymentOrder->customer_email,
            'payment_method_id' => $this->paymentMethod->id ?? 'unknown',
        ]);

        // Ensure settings relationship is loaded
        if (! $this->paymentMethod->relationLoaded('settings')) {
            $this->paymentMethod->load('settings');
        }

        $sandboxMode = $this->paymentMethod->getSetting('sandbox_mode', true);

        if ($sandboxMode) {
            $apiToken = $this->paymentMethod->getSetting('api_token_sandbox');
            $baseUrl = 'https://api-sandbox.tamara.co';
        } else {
            $apiToken = $this->paymentMethod->getSetting('api_token_production');
            $baseUrl = 'https://api.tamara.co';
        }

        $currency = $this->paymentMethod->getSetting('supported_currency', 'SAR');
        $paymentType = $this->paymentMethod->getSetting('payment_type', 'PAY_BY_INSTALMENTS');
        $merchantCode = $this->paymentMethod->getSetting('merchant_code', '');

        // Log configuration details
        Log::info('Tamara Configuration Loaded', [
            'sandbox_mode' => $sandboxMode,
            'base_url' => $baseUrl,
            'api_token_length' => strlen($apiToken),
            'has_api_token' => ! empty($apiToken),
            'currency' => $currency,
            'payment_type' => $paymentType,
        ]);

        // Create checkout session with Tamara API
        try {
            $checkoutUrl = $this->createTamaraCheckoutSession($paymentOrder, $apiToken, $baseUrl, $currency, $paymentType, $merchantCode);

            Log::info('Tamara Checkout Session Created', [
                'order_code' => $paymentOrder->order_code,
                'checkout_url' => $checkoutUrl,
            ]);

            // Redirect to Tamara payment page
            return redirect($checkoutUrl);

        } catch (\Exception $e) {
            Log::error('Tamara Checkout Session Creation Failed', [
                'order_code' => $paymentOrder->order_code,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ]);

            // Return error view
            return view('payment-gateway::plugins.tamara-payment-error', [
                'paymentOrder' => $paymentOrder,
                'paymentMethod' => $this->paymentMethod,
                'errorMessage' => $e->getMessage(),
                'failureUrl' => $this->getFailureUrl($paymentOrder),
            ]);
        }
    }

    public function handleCallback(array $callbackData): \Trinavo\PaymentGateway\Models\CallbackResponse
    {
        // Log incoming callback data
        Log::info('Tamara Callback Received', [
            'raw_callback_data' => $callbackData,
            'callback_keys' => array_keys($callbackData),
        ]);

        $status = $callbackData['status'] ?? null;
        $orderCode = $callbackData['order_code'] ?? $callbackData['order_reference_id'] ?? null;
        $orderId = $callbackData['order_id'] ?? null;
        $paymentStatus = $callbackData['payment_status'] ?? null;
        $message = $callbackData['message'] ?? null;

        // Log parsed callback data
        Log::info('Tamara Callback Data Parsed', [
            'status' => $status,
            'order_code' => $orderCode,
            'order_id' => $orderId,
            'payment_status' => $paymentStatus,
            'message' => $message,
        ]);

        if (! $orderCode) {
            Log::error('Tamara Callback Missing Order Code', [
                'callback_data' => $callbackData,
                'parsed_order_code' => $orderCode,
            ]);

            return \Trinavo\PaymentGateway\Models\CallbackResponse::failure(
                orderCode: 'unknown',
                message: 'Order code is required'
            );
        }

        // For success status, verify payment with Tamara API if order_id is provided
        if (in_array($status, ['APPROVED', 'CAPTURED', 'FULLY_CAPTURED']) && $orderId) {
            Log::info('Verifying Tamara Payment', [
                'order_code' => $orderCode,
                'order_id' => $orderId,
                'status' => $status,
            ]);

            try {
                $verificationResult = $this->verifyPaymentWithTamara($orderId);

                if ($verificationResult['success']) {
                    Log::info('Tamara Payment Verification Successful', [
                        'order_code' => $orderCode,
                        'verified_status' => $verificationResult['status'],
                        'order_id' => $orderId,
                    ]);

                    return \Trinavo\PaymentGateway\Models\CallbackResponse::success(
                        orderCode: $orderCode,
                        transactionId: $verificationResult['order_id'] ?: $orderId,
                        message: $message ?: 'Payment completed successfully via Tamara'
                    );
                } else {
                    Log::warning('Tamara Payment Verification Failed', [
                        'order_code' => $orderCode,
                        'order_id' => $orderId,
                        'verification_error' => $verificationResult['error'],
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Tamara Payment Verification Exception', [
                    'order_code' => $orderCode,
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Success without verification or for backward compatibility
        if (in_array($status, ['APPROVED', 'CAPTURED', 'FULLY_CAPTURED']) ||
            in_array($paymentStatus, ['APPROVED', 'CAPTURED', 'FULLY_CAPTURED'])) {
            Log::info('Tamara Payment Success (No Verification)', [
                'order_code' => $orderCode,
                'status' => $status,
                'payment_status' => $paymentStatus,
                'order_id' => $orderId,
            ]);

            return \Trinavo\PaymentGateway\Models\CallbackResponse::success(
                orderCode: $orderCode,
                transactionId: $orderId ?: 'tamara_'.uniqid(),
                message: $message ?: 'Payment completed successfully via Tamara'
            );
        }

        // Log payment failure
        Log::warning('Tamara Payment Failed', [
            'order_code' => $orderCode,
            'status' => $status,
            'payment_status' => $paymentStatus,
            'order_id' => $orderId,
            'message' => $message,
            'failure_reason' => 'Payment status indicates failure or cancellation',
        ]);

        return \Trinavo\PaymentGateway\Models\CallbackResponse::failure(
            orderCode: $orderCode,
            message: $message ?: 'Payment failed or was rejected',
            status: $status ?: $paymentStatus,
            additionalData: [
                'tamara_order_id' => $orderId,
                'tamara_status' => $status,
                'tamara_payment_status' => $paymentStatus,
            ]
        );
    }

    /**
     * Get the API base URL based on sandbox mode
     */
    private function getApiUrl(): string
    {
        $sandboxMode = $this->paymentMethod->getSetting('sandbox_mode', true);
        $apiUrl = $sandboxMode ? 'https://api-sandbox.tamara.co' : 'https://api.tamara.co';

        Log::debug('Tamara API URL Generated', [
            'sandbox_mode' => $sandboxMode,
            'api_url' => $apiUrl,
        ]);

        return $apiUrl;
    }

    /**
     * Get API headers for Tamara requests
     */
    private function getApiHeaders(): array
    {
        $sandboxMode = $this->paymentMethod->getSetting('sandbox_mode', true);
        $apiToken = $sandboxMode
            ? $this->paymentMethod->getSetting('api_token_sandbox')
            : $this->paymentMethod->getSetting('api_token_production');

        $headers = [
            'Authorization' => 'Bearer '.$apiToken,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        // Log API headers (without exposing the actual token)
        Log::debug('Tamara API Headers Generated', [
            'sandbox_mode' => $sandboxMode,
            'has_authorization' => ! empty($headers['Authorization']),
            'authorization_length' => strlen($headers['Authorization']),
            'content_type' => $headers['Content-Type'],
            'accept' => $headers['Accept'],
        ]);

        return $headers;
    }

    /**
     * Create Tamara checkout session
     */
    private function createTamaraCheckoutSession(PaymentOrder $paymentOrder, string $apiToken, string $baseUrl, string $currency, string $paymentType, string $merchantCode): string
    {
        $data = [
            'order_reference_id' => $paymentOrder->order_code,
            'description' => $paymentOrder->description ?: 'Order #'.$paymentOrder->order_code,
            'country_code' => $this->getCurrencyCountryCode($currency),
            'payment_type' => $paymentType,
            'instalments' => $this->getInstalmentsForPaymentType($paymentType),
            'locale' => app()->getLocale() === 'ar' ? 'ar_SA' : 'en_US',
            'total_amount' => [
                'amount' => round($paymentOrder->amount, 2),
                'currency' => $currency,
            ],
            'shipping_amount' => [
                'amount' => 0,
                'currency' => $currency,
            ],
            'tax_amount' => [
                'amount' => 0,
                'currency' => $currency,
            ],
            'discount_amount' => [
                'amount' => 0,
                'currency' => $currency,
            ],
            'items' => [
                [
                    'reference_id' => '1',
                    'type' => 'Physical',
                    'name' => $paymentOrder->description ?: 'Payment Item',
                    'sku' => 'ITEM-'.$paymentOrder->order_code,
                    'quantity' => 1,
                    'total_amount' => [
                        'amount' => round($paymentOrder->amount, 2),
                        'currency' => $currency,
                    ],
                    'unit_price' => [
                        'amount' => round($paymentOrder->amount, 2),
                        'currency' => $currency,
                    ],
                ],
            ],
            'consumer' => [
                'first_name' => $this->extractFirstName($paymentOrder->customer_name),
                'last_name' => $this->extractLastName($paymentOrder->customer_name),
                'phone_number' => $paymentOrder->customer_phone ?: '',
                'email' => $paymentOrder->customer_email ?: '',
            ],
            'billing_address' => [
                'city' => $paymentOrder->customer_data['city'] ?? 'Unknown',
                'country_code' => $this->getCurrencyCountryCode($currency),
                'first_name' => $this->extractFirstName($paymentOrder->customer_name),
                'last_name' => $this->extractLastName($paymentOrder->customer_name),
                'line1' => $paymentOrder->customer_data['address'] ?? 'Unknown Address',
                'phone_number' => $paymentOrder->customer_phone ?: '',
            ],
            'shipping_address' => [
                'city' => $paymentOrder->customer_data['city'] ?? 'Unknown',
                'country_code' => $this->getCurrencyCountryCode($currency),
                'first_name' => $this->extractFirstName($paymentOrder->customer_name),
                'last_name' => $this->extractLastName($paymentOrder->customer_name),
                'line1' => $paymentOrder->customer_data['address'] ?? 'Unknown Address',
                'phone_number' => $paymentOrder->customer_phone ?: '',
            ],
            'merchant_url' => [
                'success' => route('payment-gateway.callback', ['plugin' => 'tamara']).'?status=APPROVED&order_code='.$paymentOrder->order_code,
                'failure' => route('payment-gateway.callback', ['plugin' => 'tamara']).'?status=DECLINED&order_code='.$paymentOrder->order_code,
                'cancel' => route('payment-gateway.callback', ['plugin' => 'tamara']).'?status=CANCELLED&order_code='.$paymentOrder->order_code,
                'notification' => route('payment-gateway.callback', ['plugin' => 'tamara']),
            ],
        ];

        // Add merchant code if provided
        if (! empty($merchantCode)) {
            $data['merchant_code'] = $merchantCode;
        }

        Log::info('Tamara API Request Data', [
            'order_code' => $paymentOrder->order_code,
            'url' => $baseUrl.'/checkout',
            'data' => $data,
            'api_token_prefix' => substr($apiToken, 0, 10).'...',
            'authorization_header' => 'Bearer '.substr($apiToken, 0, 10).'...',
            'callback_urls' => [
                'success' => $data['merchant_url']['success'],
                'failure' => $data['merchant_url']['failure'],
                'cancel' => $data['merchant_url']['cancel'],
                'notification' => $data['merchant_url']['notification'],
            ],
        ]);

        // Make API request to create checkout session
        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => 'Bearer '.$apiToken,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post($baseUrl.'/checkout', $data);

        Log::info('Tamara API Response', [
            'order_code' => $paymentOrder->order_code,
            'status_code' => $response->status(),
            'response_body' => $response->body(),
            'response_headers' => $response->headers(),
            'request_headers' => [
                'Authorization' => 'Bearer '.substr($apiToken, 0, 10).'...',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);

        if (! $response->successful()) {
            throw new \Exception('Tamara API request failed: '.$response->body());
        }

        $responseData = $response->json();

        // Check for successful creation
        if (isset($responseData['checkout_url'])) {
            // Store the remote transaction ID
            if (isset($responseData['order_id'])) {
                $paymentOrder->update(['remote_transaction_id' => $responseData['order_id']]);
            }

            return $responseData['checkout_url'];
        }

        throw new \Exception('No checkout URL returned from Tamara API');
    }

    /**
     * Verify payment with Tamara API
     */
    private function verifyPaymentWithTamara(string $orderId): array
    {
        try {
            $sandboxMode = $this->paymentMethod->getSetting('sandbox_mode', true);
            $apiToken = $sandboxMode
                ? $this->paymentMethod->getSetting('api_token_sandbox')
                : $this->paymentMethod->getSetting('api_token_production');

            $baseUrl = $this->getApiUrl();

            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Bearer '.$apiToken,
            ])->get($baseUrl.'/orders/'.$orderId);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'error' => 'API request failed: '.$response->body(),
                ];
            }

            $orderData = $response->json();
            $status = $orderData['status'] ?? null;

            Log::info('Tamara Order API Verification', [
                'order_id' => $orderId,
                'status' => $status,
                'amount' => $orderData['total_amount']['amount'] ?? null,
                'response' => $orderData,
            ]);

            if (in_array($status, ['approved', 'captured', 'fully_captured'])) {
                return [
                    'success' => true,
                    'status' => $status,
                    'order_id' => $orderData['order_id'] ?? $orderId,
                    'data' => $orderData,
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Payment status not successful: '.$status,
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Verification exception: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Get country code based on currency
     */
    private function getCurrencyCountryCode(string $currency): string
    {
        $mapping = [
            'SAR' => 'SA',
            'AED' => 'AE',
            'KWD' => 'KW',
            'BHD' => 'BH',
            'QAR' => 'QA',
        ];

        return $mapping[$currency] ?? 'SA';
    }

    /**
     * Get instalments configuration for payment type
     */
    private function getInstalmentsForPaymentType(string $paymentType): ?int
    {
        switch ($paymentType) {
            case 'PAY_BY_INSTALMENTS':
                return 4;
            case 'PAY_BY_MONTH':
                return 12;
            case 'PAY_BY_LATER':
            case 'PAY_NOW':
            default:
                return null;
        }
    }

    /**
     * Extract first name from full name
     */
    private function extractFirstName(?string $fullName): string
    {
        if (empty($fullName)) {
            return 'Unknown';
        }

        $parts = explode(' ', trim($fullName), 2);

        return $parts[0];
    }

    /**
     * Extract last name from full name
     */
    private function extractLastName(?string $fullName): string
    {
        if (empty($fullName)) {
            return 'User';
        }

        $parts = explode(' ', trim($fullName), 2);

        return isset($parts[1]) ? $parts[1] : 'User';
    }
}
