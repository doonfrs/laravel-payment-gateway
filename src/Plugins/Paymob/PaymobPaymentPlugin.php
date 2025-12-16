<?php

namespace Trinavo\PaymentGateway\Plugins\Paymob;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Trinavo\PaymentGateway\Configuration\CheckboxField;
use Trinavo\PaymentGateway\Configuration\SelectField;
use Trinavo\PaymentGateway\Configuration\TextField;
use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Models\RefundResponse;

class PaymobPaymentPlugin extends PaymentPluginInterface
{
    public function getName(): string
    {
        return __('Paymob Payment Plugin');
    }

    public function getDescription(): string
    {
        return __('Integrate Paymob payment gateway (cards via iframe) with country-specific API endpoints.');
    }

    public function getConfigurationFields(): array
    {
        return [
            new SelectField(
                name: 'country',
                label: 'Country',
                required: true,
                options: [
                    'eg' => 'Egypt',
                    'sa' => 'Saudi Arabia',
                    'om' => 'Oman',
                    'ae' => 'United Arab Emirates',
                ],
                default: 'eg',
                description: 'Select the country for this Paymob integration. Each country has a different API base URL.'
            ),

            new TextField(
                name: 'api_key',
                label: 'API Key',
                required: true,
                encrypted: true,
                description: 'Your Paymob API key for the selected country. You can find it in the Paymob dashboard under API keys.'
            ),

            new TextField(
                name: 'iframe_id',
                label: 'Iframe ID',
                required: true,
                encrypted: false,
                description: 'The Paymob iframe integration ID used to render the card payment form.'
            ),

            new TextField(
                name: 'card_integration_id',
                label: 'Card Integration ID',
                required: true,
                encrypted: false,
                description: 'The Paymob integration ID for card payments (used when generating the payment key).'
            ),

            new TextField(
                name: 'hmac_secret',
                label: 'HMAC Secret',
                required: true,
                encrypted: true,
                description: 'The HMAC secret used to verify Paymob callback signatures.'
            ),

            new CheckboxField(
                name: 'test_mode',
                label: 'Test Mode',
                default: true,
                description: 'Enable test mode for Paymob payments (use test credentials from Paymob dashboard).'
            ),
        ];
    }

    public function validateConfiguration(): bool
    {
        return ! empty($this->paymentMethod->getSetting('country')) &&
               ! empty($this->paymentMethod->getSetting('api_key')) &&
               ! empty($this->paymentMethod->getSetting('iframe_id')) &&
               ! empty($this->paymentMethod->getSetting('card_integration_id')) &&
               ! empty($this->paymentMethod->getSetting('hmac_secret'));
    }

    public function processPayment(PaymentOrder $paymentOrder)
    {
        Log::info('Paymob Payment Processing Started', [
            'order_code' => $paymentOrder->order_code,
            'amount' => $paymentOrder->amount,
            'currency' => $paymentOrder->currency,
            'customer_email' => $paymentOrder->customer_email,
            'payment_method_id' => $this->paymentMethod->id ?? 'unknown',
        ]);

        if (! $this->paymentMethod->relationLoaded('settings')) {
            $this->paymentMethod->load('settings');
        }

        try {
            // Check if we already have a Paymob order ID for this payment order
            $paymobOrderId = null;
            if ($paymentOrder->remote_transaction_id) {
                $paymobOrderId = (int) $paymentOrder->remote_transaction_id;
                Log::info('Paymob Using Existing Order ID', [
                    'order_code' => $paymentOrder->order_code,
                    'paymob_order_id' => $paymobOrderId,
                ]);
            }

            // If no existing order ID, create a new one
            if (! $paymobOrderId) {
                $authToken = $this->authenticate();

                $paymobOrderId = $this->createOrder(
                    paymentOrder: $paymentOrder,
                    authToken: $authToken
                );

                $paymentOrder->update([
                    'remote_transaction_id' => $paymobOrderId,
                ]);
            }

            // Generate payment key (always need fresh auth token for this)
            $authToken = $this->authenticate();

            $paymentKey = $this->generatePaymentKey(
                paymentOrder: $paymentOrder,
                authToken: $authToken,
                paymobOrderId: $paymobOrderId
            );

            $country = $this->paymentMethod->getSetting('country', 'eg');
            $baseUrl = $this->getBaseUrlForCountry($country);

            return view('payment-gateway::plugins.paymob-payment', [
                'paymentOrder' => $paymentOrder,
                'paymentMethod' => $this->paymentMethod,
                'paymentKey' => $paymentKey,
                'iframeId' => $this->paymentMethod->getSetting('iframe_id'),
                'baseUrl' => $baseUrl,
                'successUrl' => $this->getSuccessUrl($paymentOrder),
                'failureUrl' => $this->getFailureUrl($paymentOrder),
                'callbackUrl' => $this->getCallbackUrl(),
                'country' => $country,
            ]);
        } catch (\Exception $e) {
            Log::error('Paymob Payment Failed', [
                'order_code' => $paymentOrder->order_code,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ]);

            return view('payment-gateway::plugins.paymob-payment-error', [
                'paymentOrder' => $paymentOrder,
                'paymentMethod' => $this->paymentMethod,
                'errorMessage' => $e->getMessage(),
                'failureUrl' => $this->getFailureUrl($paymentOrder),
            ]);
        }
    }

    public function handleCallback(array $callbackData): \Trinavo\PaymentGateway\Models\CallbackResponse
    {
        Log::info('Paymob Callback Received', [
            'raw_callback_data' => $callbackData,
            'callback_keys' => array_keys($callbackData),
        ]);

        $hmac = $callbackData['hmac'] ?? null;

        if (! $hmac || ! $this->isValidHmac($callbackData, $hmac)) {
            Log::warning('Paymob Callback HMAC Invalid', [
                'callback_data' => $callbackData,
            ]);

            $orderCode = $callbackData['merchant_order_id'] ?? $callbackData['order'] ?? 'unknown';

            return \Trinavo\PaymentGateway\Models\CallbackResponse::failure(
                orderCode: (string) $orderCode,
                message: 'Invalid callback signature from Paymob'
            );
        }

        $success = ($callbackData['success'] ?? 'false') === 'true';
        $orderCode = $callbackData['merchant_order_id'] ?? $callbackData['order'] ?? null;
        $transactionId = $callbackData['id'] ?? $callbackData['transaction_id'] ?? null;
        $paymobStatus = $callbackData['data']['txn_response_code'] ?? $callbackData['txn_response_code'] ?? null;

        if (! $orderCode) {
            Log::error('Paymob Callback Missing Order Code', [
                'callback_data' => $callbackData,
            ]);

            return \Trinavo\PaymentGateway\Models\CallbackResponse::failure(
                orderCode: 'unknown',
                message: 'Order code is required'
            );
        }

        if ($success) {
            return \Trinavo\PaymentGateway\Models\CallbackResponse::success(
                orderCode: (string) $orderCode,
                transactionId: $transactionId ? (string) $transactionId : 'paymob_'.uniqid(),
                message: 'Payment completed successfully via Paymob',
                additionalData: [
                    'paymob_txn_response_code' => $paymobStatus,
                ]
            );
        }

        return \Trinavo\PaymentGateway\Models\CallbackResponse::failure(
            orderCode: (string) $orderCode,
            message: $callbackData['data']['message'] ?? $callbackData['message'] ?? 'Payment failed or was rejected',
            status: $paymobStatus,
            additionalData: [
                'paymob_txn_response_code' => $paymobStatus,
            ]
        );
    }

    public function refund(PaymentOrder $paymentOrder): RefundResponse
    {
        return RefundResponse::failure(
            orderCode: $paymentOrder->order_code,
            message: 'Refunds are not supported for this payment method'
        );
    }

    /**
     * Authenticate with Paymob to get auth token
     */
    private function authenticate(): string
    {
        $baseUrl = $this->getBaseUrlForCountry(
            $this->paymentMethod->getSetting('country', 'eg')
        );

        $apiKey = $this->paymentMethod->getSetting('api_key');

        if (empty($apiKey)) {
            throw new \Exception('Paymob API key is not configured.');
        }

        $url = $baseUrl.'/api/auth/tokens';

        Log::info('Paymob Auth Request', [
            'url' => $url,
            'country' => $this->paymentMethod->getSetting('country', 'eg'),
        ]);

        $response = Http::post($url, [
            'api_key' => $apiKey,
        ]);

        Log::info('Paymob Auth Response', [
            'status_code' => $response->status(),
            'response_body' => $response->body(),
        ]);

        if (! $response->successful()) {
            throw new \Exception('Paymob auth request failed: '.$response->body());
        }

        $data = $response->json();

        if (! isset($data['token'])) {
            throw new \Exception('Paymob auth response missing token.');
        }

        return $data['token'];
    }

    /**
     * Create Paymob order
     */
    private function createOrder(PaymentOrder $paymentOrder, string $authToken, bool $isRetry = false): int
    {
        $baseUrl = $this->getBaseUrlForCountry(
            $this->paymentMethod->getSetting('country', 'eg')
        );

        $url = $baseUrl.'/api/ecommerce/orders';

        $amountCents = (int) round($paymentOrder->amount * 100);

        $country = $this->paymentMethod->getSetting('country', 'eg');
        $currency = $this->getCurrencyForCountry($country);

        // If retry, add a unique suffix to merchant_order_id to avoid duplicate error
        $merchantOrderId = $paymentOrder->order_code;
        if ($isRetry) {
            $merchantOrderId = $paymentOrder->order_code.'-'.time().'-'.substr(uniqid(), -6);
            Log::info('Paymob Retry with Modified Merchant Order ID', [
                'original_order_code' => $paymentOrder->order_code,
                'modified_merchant_order_id' => $merchantOrderId,
            ]);
        }

        $data = [
            'auth_token' => $authToken,
            'delivery_needed' => false,
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'merchant_order_id' => $merchantOrderId,
            'items' => [],
        ];

        Log::info('Paymob Create Order Request', [
            'url' => $url,
            'data' => $data,
            'is_retry' => $isRetry,
        ]);

        $response = Http::post($url, $data);

        Log::info('Paymob Create Order Response', [
            'status_code' => $response->status(),
            'response_body' => $response->body(),
        ]);

        if (! $response->successful()) {
            $responseBody = $response->body();
            $responseData = $response->json();

            // Check if it's a duplicate error and we haven't retried yet
            if (isset($responseData['message']) && 
                strtolower($responseData['message']) === 'duplicate' && 
                ! $isRetry) {
                Log::warning('Paymob Duplicate Order Detected, Retrying with Modified ID', [
                    'order_code' => $paymentOrder->order_code,
                ]);

                // Retry once with modified merchant_order_id
                return $this->createOrder($paymentOrder, $authToken, isRetry: true);
            }

            throw new \Exception('Paymob create order request failed: '.$responseBody);
        }

        $responseData = $response->json();

        if (! isset($responseData['id'])) {
            throw new \Exception('Paymob create order response missing id.');
        }

        return (int) $responseData['id'];
    }

    /**
     * Generate Paymob payment key for card payments
     */
    private function generatePaymentKey(PaymentOrder $paymentOrder, string $authToken, int $paymobOrderId): string
    {
        $baseUrl = $this->getBaseUrlForCountry(
            $this->paymentMethod->getSetting('country', 'eg')
        );

        $url = $baseUrl.'/api/acceptance/payment_keys';

        $amountCents = (int) round($paymentOrder->amount * 100);
        $country = $this->paymentMethod->getSetting('country', 'eg');
        $currency = $this->getCurrencyForCountry($country);
        $countryCode = $this->getCountryCodeForCountry($country);

        $billingData = [
            'apartment' => 'N/A',
            'email' => $paymentOrder->customer_email ?: 'customer@example.com',
            'floor' => 'N/A',
            'first_name' => $paymentOrder->customer_name ?: 'Customer',
            'street' => 'N/A',
            'building' => 'N/A',
            'phone_number' => $paymentOrder->customer_phone ?: '0000000000',
            'shipping_method' => 'N/A',
            'postal_code' => $paymentOrder->customer_data['postal_code'] ?? '00000',
            'city' => $paymentOrder->customer_data['city'] ?? $this->getDefaultCityForCountry($country),
            'country' => $paymentOrder->customer_data['country'] ?? $countryCode,
            'last_name' => $paymentOrder->customer_name ?: 'Customer',
            'state' => $paymentOrder->customer_data['state'] ?? 'N/A',
        ];

        $data = [
            'auth_token' => $authToken,
            'amount_cents' => $amountCents,
            'expiration' => 3600,
            'order_id' => $paymobOrderId,
            'currency' => $currency,
            'integration_id' => (int) $this->paymentMethod->getSetting('card_integration_id'),
            'lock_order_when_paid' => true,
            'billing_data' => $billingData,
        ];

        Log::info('Paymob Payment Key Request', [
            'url' => $url,
            'data' => $data,
        ]);

        $response = Http::post($url, $data);

        Log::info('Paymob Payment Key Response', [
            'status_code' => $response->status(),
            'response_body' => $response->body(),
        ]);

        if (! $response->successful()) {
            throw new \Exception('Paymob payment key request failed: '.$response->body());
        }

        $responseData = $response->json();

        if (! isset($responseData['token'])) {
            throw new \Exception('Paymob payment key response missing token.');
        }

        return $responseData['token'];
    }

    /**
     * Get Paymob base URL based on country
     *
     * The mapping is based on Paymob documentation for different regions.
     * See: https://developers.paymob.com/egypt/getting-started-egypt
     */
    private function getBaseUrlForCountry(string $country): string
    {
        $country = strtolower($country);

        $map = [
            'eg' => 'https://accept.paymob.com',
            'sa' => 'https://ksa.paymob.com',
            'om' => 'https://om.paymob.com',
            'ae' => 'https://uae.paymob.com',
        ];

        return $map[$country] ?? 'https://accept.paymob.com';
    }

    /**
     * Get currency code for Paymob based on country
     *
     * Each Paymob region supports specific currencies:
     * - Egypt (eg): EGP
     * - Saudi Arabia (sa): SAR
     * - Oman (om): OMR
     * - UAE (ae): AED
     */
    private function getCurrencyForCountry(string $country): string
    {
        $country = strtolower($country);

        $map = [
            'eg' => 'EGP',
            'sa' => 'SAR',
            'om' => 'OMR',
            'ae' => 'AED',
        ];

        return $map[$country] ?? 'EGP';
    }

    /**
     * Get ISO country code for billing data based on country setting
     */
    private function getCountryCodeForCountry(string $country): string
    {
        $country = strtolower($country);

        $map = [
            'eg' => 'EG',
            'sa' => 'SA',
            'om' => 'OM',
            'ae' => 'AE',
        ];

        return $map[$country] ?? 'EG';
    }

    /**
     * Get default city name for billing data based on country
     */
    private function getDefaultCityForCountry(string $country): string
    {
        $country = strtolower($country);

        $map = [
            'eg' => 'Cairo',
            'sa' => 'Riyadh',
            'om' => 'Muscat',
            'ae' => 'Dubai',
        ];

        return $map[$country] ?? 'Cairo';
    }

    /**
     * Validate Paymob callback HMAC
     *
     * Implementation based on Paymob HMAC documentation.
     */
    private function isValidHmac(array $callbackData, string $receivedHmac): bool
    {
        $hmacSecret = $this->paymentMethod->getSetting('hmac_secret');

        if (empty($hmacSecret)) {
            return false;
        }

        // Paymob defines a specific list and order of fields for HMAC calculation.
        // Here we follow the typical transaction callback fields.
        $hmacFields = [
            'amount_cents',
            'created_at',
            'currency',
            'error_occured',
            'has_parent_transaction',
            'id',
            'integration_id',
            'is_3d_secure',
            'is_auth',
            'is_capture',
            'is_refunded',
            'is_standalone_payment',
            'is_voided',
            'order',
            'owner',
            'pending',
            'source_data_pan',
            'source_data_sub_type',
            'source_data_type',
            'success',
        ];

        $concatenated = '';

        foreach ($hmacFields as $field) {
            if (isset($callbackData[$field])) {
                $concatenated .= (string) $callbackData[$field];
            }
        }

        $calculatedHmac = hash_hmac('sha512', $concatenated, $hmacSecret);

        return hash_equals($calculatedHmac, $receivedHmac);
    }
}


