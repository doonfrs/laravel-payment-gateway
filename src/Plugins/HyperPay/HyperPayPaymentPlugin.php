<?php

namespace Trinavo\PaymentGateway\Plugins\HyperPay;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Trinavo\PaymentGateway\Configuration\CheckboxField;
use Trinavo\PaymentGateway\Configuration\TextField;
use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Models\RefundResponse;

class HyperPayPaymentPlugin extends PaymentPluginInterface
{
    public function getName(): string
    {
        return __('HyperPay Payment Plugin');
    }

    public function getDescription(): string
    {
        return __('Integrate HyperPay payment gateway using COPYandPAY widget integration.');
    }

    public function getConfigurationFields(): array
    {
        return [
            new TextField(
                name: 'entity_id_test',
                label: 'Entity ID (Test)',
                required: true,
                encrypted: true,
                description: 'Your HyperPay Entity ID for test environment. Get this from your HyperPay developer portal or contact HyperPay support.'
            ),
            new TextField(
                name: 'access_token_test',
                label: 'Access Token (Test)',
                required: true,
                encrypted: true,
                description: 'Your HyperPay Access Token for test environment. Get this from your HyperPay developer portal or contact HyperPay support.'
            ),
            new TextField(
                name: 'base_url_test',
                label: 'Base URL (Test)',
                required: false,
                encrypted: false,
                description: 'HyperPay test environment base URL (e.g., https://eu-test.oppwa.com).',
                default: 'https://eu-test.oppwa.com'
            ),

            new TextField(
                name: 'entity_id_production',
                label: 'Entity ID (Production)',
                required: false,
                encrypted: true,
                description: 'Your HyperPay Entity ID for production environment.'
            ),
            new TextField(
                name: 'access_token_production',
                label: 'Access Token (Production)',
                required: false,
                encrypted: true,
                description: 'Your HyperPay Access Token for production environment.'
            ),
            new TextField(
                name: 'base_url_production',
                label: 'Base URL (Production)',
                required: false,
                encrypted: false,
                description: 'HyperPay production environment base URL (e.g., https://eu-prod.oppwa.com).',
                default: 'https://eu-prod.oppwa.com'
            ),

            new TextField(
                name: 'supported_brands',
                label: 'Supported Brands',
                required: false,
                encrypted: false,
                description: 'Space-separated list of supported card brands (e.g., VISA MASTER AMEX).',
                default: 'VISA MASTER AMEX'
            ),

            new CheckboxField(
                name: 'test_mode',
                label: 'Test Mode',
                default: true,
                description: 'Enable test mode for HyperPay payments.'
            ),
        ];
    }

    public function validateConfiguration(): bool
    {
        $testMode = $this->paymentMethod->getSetting('test_mode', true);

        if ($testMode) {
            return ! empty($this->paymentMethod->getSetting('entity_id_test')) &&
                   ! empty($this->paymentMethod->getSetting('access_token_test'));
        }

        return ! empty($this->paymentMethod->getSetting('entity_id_production')) &&
               ! empty($this->paymentMethod->getSetting('access_token_production'));
    }

    public function processPayment(PaymentOrder $paymentOrder)
    {
        Log::info('HyperPay Payment Processing Started', [
            'order_code' => $paymentOrder->order_code,
            'amount' => $paymentOrder->amount,
            'currency' => $paymentOrder->currency,
            'customer_email' => $paymentOrder->customer_email,
            'payment_method_id' => $this->paymentMethod->id ?? 'unknown',
        ]);

        // Ensure settings relationship is loaded
        if (! $this->paymentMethod->relationLoaded('settings')) {
            $this->paymentMethod->load('settings');
        }

        try {
            // Prepare checkout
            $checkoutData = $this->prepareCheckout($paymentOrder);

            if (! isset($checkoutData['id'])) {
                throw new \Exception('Failed to create checkout session with HyperPay');
            }

            Log::info('HyperPay Checkout Created', [
                'order_code' => $paymentOrder->order_code,
                'checkout_id' => $checkoutData['id'],
            ]);

            // Store checkout ID
            $paymentOrder->update(['remote_transaction_id' => $checkoutData['id']]);

            // Return payment view with widget
            return view('payment-gateway::plugins.hyperpay-payment', [
                'paymentOrder' => $paymentOrder,
                'paymentMethod' => $this->paymentMethod,
                'checkoutId' => $checkoutData['id'],
                'integrity' => $checkoutData['integrity'] ?? null,
                'baseUrl' => $this->getApiUrl(),
                'successUrl' => $this->getSuccessUrl($paymentOrder),
                'failureUrl' => $this->getFailureUrl($paymentOrder),
                'callbackUrl' => $this->getCallbackUrl(),
            ]);

        } catch (\Exception $e) {
            Log::error('HyperPay Payment Failed', [
                'order_code' => $paymentOrder->order_code,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ]);

            return view('payment-gateway::plugins.hyperpay-payment-error', [
                'paymentOrder' => $paymentOrder,
                'paymentMethod' => $this->paymentMethod,
                'errorMessage' => $e->getMessage(),
                'failureUrl' => $this->getFailureUrl($paymentOrder),
            ]);
        }
    }

    public function handleCallback(array $callbackData): \Trinavo\PaymentGateway\Models\CallbackResponse
    {
        Log::info('HyperPay Callback Received', [
            'raw_callback_data' => $callbackData,
            'callback_keys' => array_keys($callbackData),
        ]);

        $resourcePath = $callbackData['resourcePath'] ?? null;
        $orderCode = $callbackData['order_code'] ?? null;

        // Try to get order code from resourcePath if not provided
        if (! $orderCode && $resourcePath) {
            // Extract from resourcePath: /v1/checkouts/{checkoutId}/payment
            // We'll need to query the payment status to get the order code
        }

        // If we have resourcePath, query payment status
        if ($resourcePath) {
            try {
                $paymentStatus = $this->getPaymentStatus($resourcePath);

                if ($paymentStatus['success']) {
                    $paymentData = $paymentStatus['data'];
                    $result = $paymentData['result'] ?? [];
                    $resultCode = $result['code'] ?? null;
                    $merchantTransactionId = $paymentData['merchantTransactionId'] ?? null;

                    // Use merchantTransactionId as order code if available
                    if ($merchantTransactionId) {
                        $orderCode = $merchantTransactionId;
                    }

                    Log::info('HyperPay Payment Status Retrieved', [
                        'order_code' => $orderCode,
                        'result_code' => $resultCode,
                        'result' => $result,
                    ]);

                    // Check result code
                    // Success codes: 000.000.000, 000.000.100, 000.100.110, 000.100.111, 000.100.112
                    if (in_array($resultCode, ['000.000.000', '000.000.100', '000.100.110', '000.100.111', '000.100.112'])) {
                        $transactionId = $paymentData['id'] ?? null;

                        return \Trinavo\PaymentGateway\Models\CallbackResponse::success(
                            orderCode: $orderCode ?: 'unknown',
                            transactionId: $transactionId ?: 'hyperpay_'.uniqid(),
                            message: $result['description'] ?? 'Payment completed successfully via HyperPay'
                        );
                    } else {
                        // Payment failed or pending
                        return \Trinavo\PaymentGateway\Models\CallbackResponse::failure(
                            orderCode: $orderCode ?: 'unknown',
                            message: $result['description'] ?? 'Payment failed or was rejected',
                            status: $resultCode,
                            additionalData: [
                                'hyperpay_result_code' => $resultCode,
                                'hyperpay_result' => $result,
                            ]
                        );
                    }
                } else {
                    Log::error('HyperPay Payment Status Check Failed', [
                        'resource_path' => $resourcePath,
                        'error' => $paymentStatus['error'] ?? 'Unknown error',
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('HyperPay Payment Status Exception', [
                    'resource_path' => $resourcePath,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Fallback: if we have order code but no resourcePath, try to find payment
        if ($orderCode && ! $resourcePath) {
            // Try to get payment order and check its remote_transaction_id
            $paymentOrder = \Trinavo\PaymentGateway\Models\PaymentOrder::where('order_code', $orderCode)->first();
            if ($paymentOrder && $paymentOrder->remote_transaction_id) {
                $resourcePath = '/v1/checkouts/'.$paymentOrder->remote_transaction_id.'/payment';
                return $this->handleCallback(['resourcePath' => $resourcePath, 'order_code' => $orderCode]);
            }
        }

        if (! $orderCode) {
            Log::error('HyperPay Callback Missing Order Code', [
                'callback_data' => $callbackData,
            ]);

            return \Trinavo\PaymentGateway\Models\CallbackResponse::failure(
                orderCode: 'unknown',
                message: 'Order code is required'
            );
        }

        // Default failure if we can't determine status
        return \Trinavo\PaymentGateway\Models\CallbackResponse::failure(
            orderCode: $orderCode,
            message: 'Unable to verify payment status'
        );
    }

    /**
     * Get the API base URL based on test mode
     */
    private function getApiUrl(): string
    {
        $testMode = $this->paymentMethod->getSetting('test_mode', true);
        
        if ($testMode) {
            return rtrim($this->paymentMethod->getSetting('base_url_test', 'https://eu-test.oppwa.com'), '/');
        }

        return rtrim($this->paymentMethod->getSetting('base_url_production', 'https://eu-prod.oppwa.com'), '/');
    }

    /**
     * Get Entity ID based on test mode
     */
    private function getEntityId(): string
    {
        $testMode = $this->paymentMethod->getSetting('test_mode', true);
        
        return $testMode
            ? $this->paymentMethod->getSetting('entity_id_test')
            : $this->paymentMethod->getSetting('entity_id_production');
    }

    /**
     * Get Access Token based on test mode
     */
    private function getAccessToken(): string
    {
        $testMode = $this->paymentMethod->getSetting('test_mode', true);
        
        return $testMode
            ? $this->paymentMethod->getSetting('access_token_test')
            : $this->paymentMethod->getSetting('access_token_production');
    }

    /**
     * Prepare checkout session with HyperPay
     */
    private function prepareCheckout(PaymentOrder $paymentOrder): array
    {
        $baseUrl = $this->getApiUrl();
        $entityId = $this->getEntityId();
        $accessToken = $this->getAccessToken();

        if (empty($entityId) || empty($accessToken)) {
            throw new \Exception('HyperPay configuration is missing entityId or access token');
        }

        $data = [
            'entityId' => $entityId,
            'amount' => number_format($paymentOrder->amount, 2, '.', ''),
            'currency' => $paymentOrder->currency ?? 'USD',
            'paymentType' => 'DB', // Debit
            'merchantTransactionId' => $paymentOrder->order_code,
            'shopperResultUrl' => $this->getCallbackUrl(),
        ];

        // Add customer information if available
        if ($paymentOrder->customer_email) {
            $data['customer.email'] = $paymentOrder->customer_email;
        }

        if ($paymentOrder->customer_name) {
            $data['customer.givenName'] = $paymentOrder->customer_name;
        }

        if ($paymentOrder->description) {
            $data['descriptor'] = $paymentOrder->description;
        }

        Log::info('HyperPay Prepare Checkout Request', [
            'url' => $baseUrl.'/v1/checkouts',
            'entity_id' => $entityId,
            'data' => array_merge($data, ['entityId' => '[REDACTED]']), // Don't log sensitive data
        ]);

        // HyperPay (OPPWA) uses Bearer token authentication
        $response = Http::withToken($accessToken)
            ->asForm()
            ->post($baseUrl.'/v1/checkouts', $data);

        Log::info('HyperPay Prepare Checkout Response', [
            'status_code' => $response->status(),
            'response_body' => $response->body(),
        ]);

        if (! $response->successful()) {
            $errorBody = $response->body();
            throw new \Exception('HyperPay API request failed: '.$errorBody);
        }

        $responseData = $response->json();

        // Check for errors in response
        if (isset($responseData['result']) && isset($responseData['result']['code'])) {
            $resultCode = $responseData['result']['code'];
            if (! in_array($resultCode, ['000.200.100', '000.200.000'])) {
                $errorMessage = $responseData['result']['description'] ?? 'Failed to create checkout';
                throw new \Exception($errorMessage);
            }
        }

        return $responseData;
    }

    /**
     * Get payment status from HyperPay
     */
    private function getPaymentStatus(string $resourcePath): array
    {
        try {
            $baseUrl = $this->getApiUrl();
            $entityId = $this->getEntityId();
            $accessToken = $this->getAccessToken();

            if (empty($entityId) || empty($accessToken)) {
                return [
                    'success' => false,
                    'error' => 'HyperPay configuration is missing entityId or access token',
                ];
            }

            // Ensure resourcePath starts with /
            if (! str_starts_with($resourcePath, '/')) {
                $resourcePath = '/'.$resourcePath;
            }

            // Ensure baseUrl doesn't end with /
            $baseUrl = rtrim($baseUrl, '/');

            $url = $baseUrl.$resourcePath;

            Log::info('HyperPay Get Payment Status Request', [
                'url' => $url,
                'entity_id' => $entityId,
            ]);

            // Status endpoint requires entityId as query param + Bearer token auth
            $response = Http::withToken($accessToken)
                ->get($url, ['entityId' => $entityId]);

            Log::info('HyperPay Get Payment Status Response', [
                'status_code' => $response->status(),
                'response_body' => $response->body(),
            ]);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'error' => 'API request failed: '.$response->body(),
                ];
            }

            $paymentData = $response->json();

            return [
                'success' => true,
                'data' => $paymentData,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Exception: '.$e->getMessage(),
            ];
        }
    }

    public function refund(PaymentOrder $paymentOrder): RefundResponse
    {
        // HyperPay refunds are done via backoffice API
        // This requires the payment ID from the original transaction
        // For now, return failure as refunds need to be implemented based on specific requirements
        
        return RefundResponse::failure(
            orderCode: $paymentOrder->order_code,
            message: 'Refunds are not yet supported for this payment method'
        );
    }
}

