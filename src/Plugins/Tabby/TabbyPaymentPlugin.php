<?php

namespace Trinavo\PaymentGateway\Plugins\Tabby;

use Illuminate\Support\Facades\Log;
use Trinavo\PaymentGateway\Configuration\CheckboxField;
use Trinavo\PaymentGateway\Configuration\SelectField;
use Trinavo\PaymentGateway\Configuration\TextField;
use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Models\RefundResponse;

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
                name: 'supported_currency',
                label: 'Supported Currency',
                required: true,
                options: [
                    'AED' => 'UAE Dirham (AED)',
                    'SAR' => 'Saudi Riyal (SAR)',
                    'KWD' => 'Kuwaiti Dinar (KWD)',
                    'BHD' => 'Bahraini Dinar (BHD)',
                    'QAR' => 'Qatari Riyal (QAR)',
                    'EGP' => 'Egyptian Pound (EGP)',
                ],
                default: 'AED',
                description: 'Select the currency for Tabby payments.'
            ),

            new TextField(
                name: 'merchant_code',
                label: 'Merchant Code',
                required: false,
                encrypted: false,
                description: 'Your Tabby merchant code (if different from public key).'
            ),
        ];
    }

    public function validateConfiguration(): bool
    {
        $sandboxMode = $this->paymentMethod->getSetting('sandbox_mode', true);

        if ($sandboxMode) {
            return ! empty($this->paymentMethod->getSetting('public_key_sandbox')) &&
                   ! empty($this->paymentMethod->getSetting('secret_key_sandbox'));
        }

        return ! empty($this->paymentMethod->getSetting('public_key_production')) &&
               ! empty($this->paymentMethod->getSetting('secret_key_production'));
    }

    public function processPayment(PaymentOrder $paymentOrder)
    {
        // Log payment processing start
        Log::info('Tabby Payment Processing Started', [
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
            $publicKey = $this->paymentMethod->getSetting('public_key_sandbox');
            $secretKey = $this->paymentMethod->getSetting('secret_key_sandbox');
            $baseUrl = 'https://api.tabby.ai/api/v2';
        } else {
            $publicKey = $this->paymentMethod->getSetting('public_key_production');
            $secretKey = $this->paymentMethod->getSetting('secret_key_production');
            $baseUrl = 'https://api.tabby.ai/api/v2';
        }

        $currency = $this->paymentMethod->getSetting('supported_currency', 'AED');
        $merchantCode = $this->paymentMethod->getSetting('merchant_code', $publicKey);

        // Log configuration details
        Log::info('Tabby Configuration Loaded', [
            'sandbox_mode' => $sandboxMode,
            'base_url' => $baseUrl,
            'public_key_length' => strlen($publicKey),
            'secret_key_length' => strlen($secretKey),
            'has_public_key' => ! empty($publicKey),
            'has_secret_key' => ! empty($secretKey),
            'currency' => $currency,
        ]);

        // Create checkout session with Tabby API
        try {
            $checkoutUrl = $this->createTabbyCheckoutSession($paymentOrder, $publicKey, $baseUrl, $currency, $merchantCode);

            Log::info('Tabby Checkout Session Created', [
                'order_code' => $paymentOrder->order_code,
                'checkout_url' => $checkoutUrl,
            ]);

            // Redirect to Tabby payment page
            return redirect($checkoutUrl);

        } catch (\Exception $e) {
            report($e);

            return view('payment-gateway::plugins.tabby-payment-error', [
                'paymentOrder' => $paymentOrder,
                'paymentMethod' => $this->paymentMethod,
                'failureUrl' => $this->getFailureUrl($paymentOrder),
            ]);
        }
    }

    public function handleCallback(array $callbackData): \Trinavo\PaymentGateway\Models\CallbackResponse
    {
        // Log incoming callback data
        Log::info('Tabby Callback Received', [
            'raw_callback_data' => $callbackData,
            'callback_keys' => array_keys($callbackData),
        ]);

        $status = $callbackData['status'] ?? null;
        $orderCode = $callbackData['order_code'] ?? null;
        $paymentId = $callbackData['payment_id'] ?? null;
        $tabbyId = $callbackData['tabby_id'] ?? null;
        $message = $callbackData['message'] ?? null;

        // Log parsed callback data
        Log::info('Tabby Callback Data Parsed', [
            'status' => $status,
            'order_code' => $orderCode,
            'payment_id' => $paymentId,
            'tabby_id' => $tabbyId,
            'message' => $message,
        ]);

        if (! $orderCode) {
            Log::error('Tabby Callback Missing Order Code', [
                'callback_data' => $callbackData,
                'parsed_order_code' => $orderCode,
            ]);

            return \Trinavo\PaymentGateway\Models\CallbackResponse::failure(
                orderCode: 'unknown',
                message: 'Order code is required'
            );
        }

        // For success status, verify payment with Tabby API if payment_id is provided
        if (in_array($status, ['AUTHORIZED', 'CLOSED']) && $paymentId) {
            Log::info('Verifying Tabby Payment', [
                'order_code' => $orderCode,
                'payment_id' => $paymentId,
                'status' => $status,
            ]);

            try {
                $verificationResult = $this->verifyPaymentWithTabby($paymentId);

                if ($verificationResult['success']) {
                    Log::info('Tabby Payment Verification Successful', [
                        'order_code' => $orderCode,
                        'verified_status' => $verificationResult['status'],
                        'payment_id' => $paymentId,
                    ]);

                    return \Trinavo\PaymentGateway\Models\CallbackResponse::success(
                        orderCode: $orderCode,
                        transactionId: $verificationResult['tabby_id'] ?: $paymentId,
                        message: $message ?: 'Payment completed successfully via Tabby'
                    );
                } else {
                    Log::warning('Tabby Payment Verification Failed', [
                        'order_code' => $orderCode,
                        'payment_id' => $paymentId,
                        'verification_error' => $verificationResult['error'],
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Tabby Payment Verification Exception', [
                    'order_code' => $orderCode,
                    'payment_id' => $paymentId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Success without verification or for backward compatibility
        if (in_array($status, ['AUTHORIZED', 'CLOSED'])) {
            Log::info('Tabby Payment Success (No Verification)', [
                'order_code' => $orderCode,
                'status' => $status,
                'payment_id' => $paymentId,
            ]);

            return \Trinavo\PaymentGateway\Models\CallbackResponse::success(
                orderCode: $orderCode,
                transactionId: $tabbyId ?: $paymentId ?: 'tabby_'.uniqid(),
                message: $message ?: 'Payment completed successfully via Tabby'
            );
        }

        // Log payment failure
        Log::warning('Tabby Payment Failed', [
            'order_code' => $orderCode,
            'status' => $status,
            'payment_id' => $paymentId,
            'message' => $message,
            'failure_reason' => 'Payment status indicates failure or cancellation',
        ]);

        return \Trinavo\PaymentGateway\Models\CallbackResponse::failure(
            orderCode: $orderCode,
            message: $message ?: 'Payment failed or was rejected',
            status: $status,
            additionalData: [
                'tabby_payment_id' => $paymentId,
                'tabby_id' => $tabbyId,
                'tabby_status' => $status,
            ]
        );
    }

    /**
     * Get the API base URL based on sandbox mode
     */
    private function getApiUrl(): string
    {
        $sandboxMode = $this->paymentMethod->getSetting('sandbox_mode', true);
        $apiUrl = $sandboxMode ? 'https://api-sandbox.tabby.ai' : 'https://api.tabby.ai';

        Log::debug('Tabby API URL Generated', [
            'sandbox_mode' => $sandboxMode,
            'api_url' => $apiUrl,
        ]);

        return $apiUrl;
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

        $headers = [
            'Authorization' => 'Bearer '.$secretKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        // Log API headers (without exposing the actual secret key)
        Log::debug('Tabby API Headers Generated', [
            'sandbox_mode' => $sandboxMode,
            'has_authorization' => ! empty($headers['Authorization']),
            'authorization_length' => strlen($headers['Authorization']),
            'content_type' => $headers['Content-Type'],
            'accept' => $headers['Accept'],
        ]);

        return $headers;
    }

    /**
     * Log API request details
     */
    private function logApiRequest(string $method, string $endpoint, array $data = [], array $headers = []): void
    {
        Log::info('Tabby API Request', [
            'method' => $method,
            'endpoint' => $endpoint,
            'data' => $data,
            'headers' => array_map(function ($value) {
                // Mask sensitive header values
                if (str_contains(strtolower($value), 'bearer')) {
                    return 'Bearer ***'.substr($value, -4);
                }

                return $value;
            }, $headers),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Log API response details
     */
    private function logApiResponse(string $method, string $endpoint, $response, ?int $statusCode = null, array $headers = []): void
    {
        Log::info('Tabby API Response', [
            'method' => $method,
            'endpoint' => $endpoint,
            'status_code' => $statusCode,
            'response' => $response,
            'response_headers' => $headers,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Log API error details
     */
    private function logApiError(string $method, string $endpoint, \Exception $exception, array $requestData = []): void
    {
        Log::error('Tabby API Error', [
            'method' => $method,
            'endpoint' => $endpoint,
            'error_message' => $exception->getMessage(),
            'error_code' => $exception->getCode(),
            'error_file' => $exception->getFile(),
            'error_line' => $exception->getLine(),
            'request_data' => $requestData,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Create Tabby checkout session
     */
    private function createTabbyCheckoutSession(PaymentOrder $paymentOrder, string $publicKey, string $baseUrl, string $currency, string $merchantCode): string
    {
        $data = [
            'payment' => [
                'amount' => (string) round($paymentOrder->amount, 2),
                'currency' => $currency,
                'description' => $paymentOrder->description ?: 'Order #'.$paymentOrder->order_code,
                'buyer' => [
                    'phone' => $paymentOrder->customer_phone ?: '',
                    'email' => $paymentOrder->customer_email ?: '',
                    'name' => $paymentOrder->customer_name ?: '',
                ],
                'shipping_address' => [
                    'city' => $paymentOrder->customer_data['city'] ?? '',
                    'address' => $paymentOrder->customer_data['address'] ?? '',
                    'zip' => $paymentOrder->customer_data['postal_code'] ?? '',
                ],
                'order' => [
                    'reference_id' => $paymentOrder->order_code,
                    'items' => [
                        [
                            'title' => $paymentOrder->description ?: 'Payment',
                            'quantity' => 1,
                            'unit_price' => (string) round($paymentOrder->amount, 2),
                            'category' => 'service',
                        ],
                    ],
                ],
                'buyer_history' => [
                    'registered_since' => now()->subYear()->toISOString(),
                    'loyalty_level' => 0,
                ],
                'order_history' => [],
            ],
            'lang' => app()->getLocale() === 'ar' ? 'ar' : 'en',
            'merchant_code' => $merchantCode,
            'merchant_urls' => [
                'success' => route('payment-gateway.callback', ['plugin' => 'tabby']).'?status=CLOSED&order_code='.$paymentOrder->order_code,
                'cancel' => route('payment-gateway.callback', ['plugin' => 'tabby']).'?status=CANCELLED&order_code='.$paymentOrder->order_code,
                'failure' => route('payment-gateway.callback', ['plugin' => 'tabby']).'?status=FAILED&order_code='.$paymentOrder->order_code,
            ],
        ];

        Log::info('Tabby API Request Data', [
            'order_code' => $paymentOrder->order_code,
            'url' => $baseUrl.'/checkout',
            'data' => $data,
            'public_key' => $publicKey,
            'public_key_prefix' => substr($publicKey, 0, 10).'...',
            'merchant_code' => $merchantCode,
            'merchant_code_prefix' => substr($merchantCode, 0, 10).'...',
            'authorization_header' => 'Bearer '.substr($publicKey, 0, 10).'...',
            'has_merchant_code_in_data' => isset($data['merchant_code']),
            'callback_urls' => [
                'success' => $data['merchant_urls']['success'],
                'cancel' => $data['merchant_urls']['cancel'],
                'failure' => $data['merchant_urls']['failure'],
            ],
        ]);

        // Make API request to create checkout session
        // Note: For checkout creation, we need to use the public key as Authorization header
        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => 'Bearer '.$publicKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post($baseUrl.'/checkout', $data);

        Log::info('Tabby API Response', [
            'order_code' => $paymentOrder->order_code,
            'status_code' => $response->status(),
            'response_body' => $response->body(),
            'response_headers' => $response->headers(),
            'request_headers' => [
                'Authorization' => 'Bearer '.substr($publicKey, 0, 10).'...',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);

        if (! $response->successful()) {
            Log::error('Tabby API request failed', [
                'order_code' => $paymentOrder->order_code,
                'status_code' => $response->status(),
                'response_body' => $response->body(),
            ]);
            throw new \Exception(__('payment_gateway_error'));
        }

        $responseData = $response->json();

        // Check for rejection
        if (isset($responseData['status']) && $responseData['status'] === 'rejected') {
            $errorMessage = $responseData['rejection_reason_code'] ?? 'Payment rejected';
            throw new \Exception($errorMessage);
        }

        // Check for successful creation
        if (isset($responseData['status']) && $responseData['status'] === 'created') {
            // Store the remote transaction ID
            $paymentOrder->update(['remote_transaction_id' => $responseData['id']]);

            // Get the payment URL
            $paymentUrl = $responseData['configuration']['available_products']['installments'][0]['web_url'] ?? null;

            if (! $paymentUrl) {
                throw new \Exception('No payment URL returned from Tabby');
            }

            return $paymentUrl;
        }

        throw new \Exception('Unexpected response from Tabby API');
    }

    /**
     * Verify payment with Tabby API
     */
    private function verifyPaymentWithTabby(string $paymentId): array
    {
        try {
            $sandboxMode = $this->paymentMethod->getSetting('sandbox_mode', true);
            $secretKey = $sandboxMode
                ? $this->paymentMethod->getSetting('secret_key_sandbox')
                : $this->paymentMethod->getSetting('secret_key_production');

            $baseUrl = 'https://api.tabby.ai/api/v2';

            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Bearer '.$secretKey,
            ])->get($baseUrl.'/payments/'.$paymentId);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'error' => 'API request failed: '.$response->body(),
                ];
            }

            $paymentData = $response->json();
            $status = $paymentData['status'] ?? null;

            Log::info('Tabby Payment API Verification', [
                'payment_id' => $paymentId,
                'status' => $status,
                'amount' => $paymentData['amount'] ?? null,
                'response' => $paymentData,
            ]);

            if (in_array($status, ['CLOSED', 'AUTHORIZED'])) {
                return [
                    'success' => true,
                    'status' => $status,
                    'tabby_id' => $paymentData['id'] ?? $paymentId,
                    'data' => $paymentData,
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

    public function refund(PaymentOrder $paymentOrder): RefundResponse
    {
        return RefundResponse::failure(
            orderCode: $paymentOrder->order_code,
            message: 'Refunds are not supported for this payment method'
        );
    }
}
