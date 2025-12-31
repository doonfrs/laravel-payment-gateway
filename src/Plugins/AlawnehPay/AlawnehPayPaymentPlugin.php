<?php

namespace Trinavo\PaymentGateway\Plugins\AlawnehPay;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Trinavo\PaymentGateway\Configuration\CheckboxField;
use Trinavo\PaymentGateway\Configuration\TextField;
use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Models\RefundResponse;

class AlawnehPayPaymentPlugin extends PaymentPluginInterface
{
    public function getName(): string
    {
        return __('Alawneh Pay Payment Plugin');
    }

    public function getDescription(): string
    {
        return __('Integrate Alawneh Pay payment gateway with OAuth 2.0 authentication.');
    }

    public function getConfigurationFields(): array
    {
        return [
            new TextField(
                name: 'client_id_test',
                label: 'Client ID (Test)',
                required: true,
                encrypted: true,
                description: 'Your Alawneh Pay Client ID for test environment.'
            ),
            new TextField(
                name: 'client_secret_test',
                label: 'Client Secret (Test)',
                required: true,
                encrypted: true,
                description: 'Your Alawneh Pay Client Secret for test environment.'
            ),

            new TextField(
                name: 'client_id_production',
                label: 'Client ID (Production)',
                required: false,
                encrypted: true,
                description: 'Your Alawneh Pay Client ID for production environment.'
            ),
            new TextField(
                name: 'client_secret_production',
                label: 'Client Secret (Production)',
                required: false,
                encrypted: true,
                description: 'Your Alawneh Pay Client Secret for production environment.'
            ),

            new TextField(
                name: 'merchant_alias',
                label: 'Merchant Alias',
                required: true,
                encrypted: false,
                description: 'Your Alawneh Pay merchant alias.'
            ),

            new CheckboxField(
                name: 'test_mode',
                label: 'Test Mode',
                default: true,
                description: 'Enable test mode for Alawneh Pay payments.'
            ),
        ];
    }

    public function validateConfiguration(): bool
    {
        $testMode = $this->paymentMethod->getSetting('test_mode', true);

        if ($testMode) {
            return ! empty($this->paymentMethod->getSetting('client_id_test')) &&
                   ! empty($this->paymentMethod->getSetting('client_secret_test')) &&
                   ! empty($this->paymentMethod->getSetting('merchant_alias'));
        }

        return ! empty($this->paymentMethod->getSetting('client_id_production')) &&
               ! empty($this->paymentMethod->getSetting('client_secret_production')) &&
               ! empty($this->paymentMethod->getSetting('merchant_alias'));
    }

    public function processPayment(PaymentOrder $paymentOrder)
    {
        Log::info('Alawneh Pay Payment Processing Started', [
            'order_code' => $paymentOrder->order_code,
            'amount' => $paymentOrder->amount,
            'customer_email' => $paymentOrder->customer_email,
            'payment_method_id' => $this->paymentMethod->id ?? 'unknown',
        ]);

        // Ensure settings relationship is loaded
        if (! $this->paymentMethod->relationLoaded('settings')) {
            $this->paymentMethod->load('settings');
        }

        $testMode = $this->paymentMethod->getSetting('test_mode', true);
        $baseUrl = $this->getApiUrl();
        $merchantAlias = $this->paymentMethod->getSetting('merchant_alias');

        Log::info('Alawneh Pay Configuration Loaded', [
            'test_mode' => $testMode,
            'base_url' => $baseUrl,
            'merchant_alias' => $merchantAlias,
        ]);

        try {
            // Get OAuth access token
            $accessToken = $this->getAccessToken();

            if (! $accessToken) {
                throw new \Exception('Failed to obtain access token from Alawneh Pay');
            }

            // Create payment request
            $paymentResponse = $this->createPayment($paymentOrder, $accessToken, $merchantAlias);

            Log::info('Alawneh Pay Payment Created', [
                'order_code' => $paymentOrder->order_code,
                'payment_response' => $paymentResponse,
            ]);

            // Store the payment information
            if (isset($paymentResponse['paymentId'])) {
                $paymentOrder->update(['remote_transaction_id' => $paymentResponse['paymentId']]);
            }

            // Return success view with payment details
            return view('payment-gateway::plugins.alawneh-pay-payment', [
                'paymentOrder' => $paymentOrder,
                'paymentMethod' => $this->paymentMethod,
                'paymentResponse' => $paymentResponse,
                'successUrl' => $this->getSuccessUrl($paymentOrder),
                'failureUrl' => $this->getFailureUrl($paymentOrder),
            ]);

        } catch (\Exception $e) {
            report($e);

            return view('payment-gateway::plugins.alawneh-pay-payment-error', [
                'paymentOrder' => $paymentOrder,
                'paymentMethod' => $this->paymentMethod,
                'failureUrl' => $this->getFailureUrl($paymentOrder),
            ]);
        }
    }

    public function handleCallback(array $callbackData): \Trinavo\PaymentGateway\Models\CallbackResponse
    {
        Log::info('Alawneh Pay Callback Received', [
            'raw_callback_data' => $callbackData,
            'callback_keys' => array_keys($callbackData),
        ]);

        $status = $callbackData['status'] ?? null;
        $orderCode = $callbackData['order_code'] ?? null;
        $paymentId = $callbackData['payment_id'] ?? null;
        $externalId = $callbackData['external_id'] ?? null;

        Log::info('Alawneh Pay Callback Data Parsed', [
            'status' => $status,
            'order_code' => $orderCode,
            'payment_id' => $paymentId,
            'external_id' => $externalId,
        ]);

        if (! $orderCode) {
            Log::error('Alawneh Pay Callback Missing Order Code', [
                'callback_data' => $callbackData,
            ]);

            return \Trinavo\PaymentGateway\Models\CallbackResponse::failure(
                orderCode: 'unknown',
                message: 'Order code is required'
            );
        }

        // Verify payment with Alawneh Pay API if payment_id is provided
        if ($paymentId) {
            try {
                $verificationResult = $this->verifyPayment($paymentId);

                if ($verificationResult['success']) {
                    Log::info('Alawneh Pay Payment Verification Successful', [
                        'order_code' => $orderCode,
                        'verified_status' => $verificationResult['status'],
                        'payment_id' => $paymentId,
                    ]);

                    return \Trinavo\PaymentGateway\Models\CallbackResponse::success(
                        orderCode: $orderCode,
                        transactionId: $paymentId,
                        message: 'Payment completed successfully via Alawneh Pay'
                    );
                } else {
                    Log::warning('Alawneh Pay Payment Verification Failed', [
                        'order_code' => $orderCode,
                        'payment_id' => $paymentId,
                        'verification_error' => $verificationResult['error'],
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Alawneh Pay Payment Verification Exception', [
                    'order_code' => $orderCode,
                    'payment_id' => $paymentId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Success based on status
        if ($status === 'ACCEPTED') {
            Log::info('Alawneh Pay Payment Success', [
                'order_code' => $orderCode,
                'status' => $status,
                'payment_id' => $paymentId,
            ]);

            return \Trinavo\PaymentGateway\Models\CallbackResponse::success(
                orderCode: $orderCode,
                transactionId: $paymentId ?: 'alawneh_'.uniqid(),
                message: 'Payment completed successfully via Alawneh Pay'
            );
        }

        // Log payment failure
        Log::warning('Alawneh Pay Payment Failed', [
            'order_code' => $orderCode,
            'status' => $status,
            'payment_id' => $paymentId,
        ]);

        return \Trinavo\PaymentGateway\Models\CallbackResponse::failure(
            orderCode: $orderCode,
            message: __('payment_failed'),
            status: $status,
            additionalData: [
                'alawneh_payment_id' => $paymentId,
                'alawneh_status' => $status,
            ]
        );
    }

    /**
     * Get the API base URL based on test mode
     */
    private function getApiUrl(): string
    {
        $testMode = $this->paymentMethod->getSetting('test_mode', true);
        
        return $testMode 
            ? 'https://gateway-test.alawnehpay.com' 
            : 'https://gateway.alawnehpay.com';
    }

    /**
     * Get OAuth access token
     */
    private function getAccessToken(): ?string
    {
        $testMode = $this->paymentMethod->getSetting('test_mode', true);
        
        $clientId = $testMode
            ? $this->paymentMethod->getSetting('client_id_test')
            : $this->paymentMethod->getSetting('client_id_production');

        $clientSecret = $testMode
            ? $this->paymentMethod->getSetting('client_secret_test')
            : $this->paymentMethod->getSetting('client_secret_production');

        $baseUrl = $this->getApiUrl();
        $credentials = base64_encode($clientId.':'.$clientSecret);

        Log::info('Alawneh Pay OAuth Request', [
            'url' => $baseUrl.'/auth-server/oauth/token',
            'has_credentials' => ! empty($credentials),
        ]);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic '.$credentials,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->get($baseUrl.'/auth-server/oauth/token', [
                'grant_type' => 'client_credentials',
            ]);

            Log::info('Alawneh Pay OAuth Response', [
                'status_code' => $response->status(),
                'response_body' => $response->body(),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                return $data['access_token'] ?? null;
            }

            Log::error('Alawneh Pay OAuth Failed', [
                'status_code' => $response->status(),
                'error' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Alawneh Pay OAuth Exception', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Create payment with Alawneh Pay
     */
    private function createPayment(PaymentOrder $paymentOrder, string $accessToken, string $merchantAlias): array
    {
        $baseUrl = $this->getApiUrl();

        $data = [
            'amount' => (string) round($paymentOrder->amount, 2),
            'paymentType' => 'MONEY_TRANSFER',
            'paymentMethod' => 'MOBILE_ONLINE',
            'thirdPartyExternalId' => $paymentOrder->order_code,
            'fromAlias' => $merchantAlias,
            'toAlias' => $paymentOrder->customer_phone ?: $merchantAlias,
            'description' => $paymentOrder->description ?: 'Order #'.$paymentOrder->order_code,
        ];

        Log::info('Alawneh Pay Create Payment Request', [
            'url' => $baseUrl.'/businessapi/payments/singlePayment',
            'data' => $data,
        ]);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$accessToken,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post($baseUrl.'/businessapi/payments/singlePayment', $data);

        Log::info('Alawneh Pay Create Payment Response', [
            'status_code' => $response->status(),
            'response_body' => $response->body(),
        ]);

        if (! $response->successful()) {
            Log::error('Alawneh Pay API request failed', [
                'order_code' => $paymentOrder->order_code,
                'status_code' => $response->status(),
                'response_body' => $response->body(),
            ]);
            throw new \Exception(__('payment_gateway_error'));
        }

        $responseData = $response->json();

        // Check for rejection
        if (isset($responseData['status']) && $responseData['status'] !== 'ACCEPTED') {
            $errorMessage = $responseData['rejectionReason'] ?? 'Payment rejected';
            throw new \Exception($errorMessage);
        }

        return $responseData;
    }

    /**
     * Verify payment with Alawneh Pay API
     */
    private function verifyPayment(string $externalId): array
    {
        try {
            $accessToken = $this->getAccessToken();

            if (! $accessToken) {
                return [
                    'success' => false,
                    'error' => 'Failed to obtain access token',
                ];
            }

            $baseUrl = $this->getApiUrl();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$accessToken,
                'Accept' => 'application/json',
            ])->get($baseUrl.'/webservice/payment/inquiry', [
                'externalId' => $externalId,
            ]);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'error' => 'API request failed: '.$response->body(),
                ];
            }

            $paymentData = $response->json();
            $status = $paymentData['status'] ?? null;

            Log::info('Alawneh Pay Payment Verification', [
                'external_id' => $externalId,
                'status' => $status,
                'response' => $paymentData,
            ]);

            if ($status === 'ACCEPTED') {
                return [
                    'success' => true,
                    'status' => $status,
                    'data' => $paymentData,
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Payment status not accepted: '.$status,
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

