<?php

namespace Trinavo\PaymentGateway\Plugins\PaymobIntention;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Trinavo\PaymentGateway\Configuration\CheckboxField;
use Trinavo\PaymentGateway\Configuration\SelectField;
use Trinavo\PaymentGateway\Configuration\TextField;
use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Models\RefundResponse;

class PaymobIntentionPaymentPlugin extends PaymentPluginInterface
{
    public function getName(): string
    {
        return __('Paymob Intention Payment Plugin');
    }

    public function getDescription(): string
    {
        return __('Integrate Paymob payment gateway using the new Intention API with redirect-based checkout.');
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
                name: 'secret_key',
                label: 'Secret Key',
                required: true,
                encrypted: true,
                description: 'Your Paymob Secret Key (starts with egy_sk_ or similar). Used for server-side API calls.'
            ),

            new TextField(
                name: 'public_key',
                label: 'Public Key',
                required: true,
                encrypted: false,
                description: 'Your Paymob Public Key (starts with egy_pk_ or similar). Used for client-side checkout.'
            ),

            new TextField(
                name: 'integration_id',
                label: 'Integration ID',
                required: true,
                encrypted: false,
                description: 'The Paymob integration ID for card payments.'
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
               ! empty($this->paymentMethod->getSetting('secret_key')) &&
               ! empty($this->paymentMethod->getSetting('public_key')) &&
               ! empty($this->paymentMethod->getSetting('integration_id')) &&
               ! empty($this->paymentMethod->getSetting('hmac_secret'));
    }

    public function processPayment(PaymentOrder $paymentOrder)
    {
        Log::info('Paymob Intention Payment Processing Started', [
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
            // Create intention and get client_secret
            $intentionData = $this->createIntention($paymentOrder);

            $country = $this->paymentMethod->getSetting('country', 'eg');
            $baseUrl = $this->getBaseUrlForCountry($country);
            $publicKey = $this->paymentMethod->getSetting('public_key');
            $clientSecret = $intentionData['client_secret'];

            // Store the intention ID for later reference
            $paymentOrder->update([
                'remote_transaction_id' => $intentionData['id'] ?? $clientSecret,
            ]);

            // Build the unified checkout URL
            $checkoutUrl = $baseUrl.'/unifiedcheckout/?publicKey='.urlencode($publicKey).'&clientSecret='.urlencode($clientSecret);

            Log::info('Paymob Intention Checkout URL Generated', [
                'order_code' => $paymentOrder->order_code,
                'checkout_url' => $checkoutUrl,
            ]);

            // Redirect to Paymob checkout
            return redirect()->away($checkoutUrl);

        } catch (\Exception $e) {
            Log::error('Paymob Intention Payment Failed', [
                'order_code' => $paymentOrder->order_code,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
            ]);

            report($e);

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
        Log::info('Paymob Intention Callback Received', [
            'raw_callback_data' => $callbackData,
            'callback_keys' => array_keys($callbackData),
        ]);

        $hmac = $callbackData['hmac'] ?? null;

        if (! $hmac || ! $this->isValidHmac($callbackData, $hmac)) {
            Log::warning('Paymob Intention Callback HMAC Invalid', [
                'callback_data' => $callbackData,
            ]);

            $orderCode = $callbackData['merchant_order_id'] ?? $callbackData['special_reference'] ?? $callbackData['order'] ?? 'unknown';

            return \Trinavo\PaymentGateway\Models\CallbackResponse::failure(
                orderCode: (string) $orderCode,
                message: 'Invalid callback signature from Paymob'
            );
        }

        // Handle both old and new API response formats
        $success = ($callbackData['success'] ?? 'false') === 'true' || ($callbackData['success'] ?? false) === true;
        $orderCode = $callbackData['merchant_order_id'] ?? $callbackData['special_reference'] ?? $callbackData['order'] ?? null;
        $transactionId = $callbackData['id'] ?? $callbackData['transaction_id'] ?? null;
        $paymobStatus = $callbackData['data']['txn_response_code'] ?? $callbackData['txn_response_code'] ?? null;

        if (! $orderCode) {
            Log::error('Paymob Intention Callback Missing Order Code', [
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
                transactionId: $transactionId ? (string) $transactionId : 'paymob_intention_'.uniqid(),
                message: 'Payment completed successfully via Paymob Intention API',
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
            message: 'Refunds are not yet implemented for Paymob Intention API'
        );
    }

    /**
     * Create a payment intention using the new Paymob API
     */
    private function createIntention(PaymentOrder $paymentOrder): array
    {
        $baseUrl = $this->getBaseUrlForCountry(
            $this->paymentMethod->getSetting('country', 'eg')
        );

        $url = $baseUrl.'/v1/intention/';

        $secretKey = $this->paymentMethod->getSetting('secret_key');

        if (empty($secretKey)) {
            throw new \Exception('Paymob Secret Key is not configured.');
        }

        $amountCents = (int) round($paymentOrder->amount * 100);
        $country = $this->paymentMethod->getSetting('country', 'eg');
        $currency = $this->getCurrencyForCountry($country);
        $countryCode = $this->getCountryCodeForCountry($country);
        $integrationId = (int) $this->paymentMethod->getSetting('integration_id');

        $billingData = [
            'apartment' => 'N/A',
            'email' => $paymentOrder->customer_email ?: 'customer@example.com',
            'floor' => 'N/A',
            'first_name' => $this->extractFirstName($paymentOrder->customer_name),
            'last_name' => $this->extractLastName($paymentOrder->customer_name),
            'street' => 'N/A',
            'building' => 'N/A',
            'phone_number' => $paymentOrder->customer_phone ?: '+20000000000',
            'shipping_method' => 'N/A',
            'postal_code' => $paymentOrder->customer_data['postal_code'] ?? '00000',
            'city' => $paymentOrder->customer_data['city'] ?? $this->getDefaultCityForCountry($country),
            'country' => $paymentOrder->customer_data['country'] ?? $countryCode,
            'state' => $paymentOrder->customer_data['state'] ?? 'N/A',
        ];

        $data = [
            'amount' => $amountCents,
            'currency' => $currency,
            'payment_methods' => [$integrationId],
            'billing_data' => $billingData,
            'special_reference' => $paymentOrder->order_code,
            'redirection_url' => $this->getCallbackUrl(),
            'notification_url' => $this->getCallbackUrl(),
            'items' => [
                [
                    'name' => $paymentOrder->description ?: 'Order '.$paymentOrder->order_code,
                    'amount' => $amountCents,
                    'description' => $paymentOrder->description ?: 'Payment for order '.$paymentOrder->order_code,
                    'quantity' => 1,
                ],
            ],
        ];

        Log::info('Paymob Create Intention Request', [
            'url' => $url,
            'data' => $data,
        ]);

        $response = Http::withHeaders([
            'Authorization' => 'Token '.$secretKey,
            'Content-Type' => 'application/json',
        ])->post($url, $data);

        Log::info('Paymob Create Intention Response', [
            'status_code' => $response->status(),
            'response_body' => $response->body(),
        ]);

        if (! $response->successful()) {
            throw new \Exception('Paymob create intention request failed: '.$response->body());
        }

        $responseData = $response->json();

        if (! isset($responseData['client_secret'])) {
            throw new \Exception('Paymob create intention response missing client_secret.');
        }

        return $responseData;
    }

    /**
     * Get Paymob base URL based on country
     */
    private function getBaseUrlForCountry(string $country): string
    {
        $country = strtolower($country);

        $map = [
            'eg' => 'https://accept.paymob.com',
            'sa' => 'https://ksa.paymob.com',
            'om' => 'https://oman.paymob.com',
            'ae' => 'https://uae.paymob.com',
        ];

        return $map[$country] ?? 'https://accept.paymob.com';
    }

    /**
     * Get currency code for Paymob based on country
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
     * Extract first name from full name
     */
    private function extractFirstName(?string $fullName): string
    {
        if (empty($fullName)) {
            return 'Customer';
        }

        $parts = explode(' ', trim($fullName), 2);

        return $parts[0] ?: 'Customer';
    }

    /**
     * Extract last name from full name
     */
    private function extractLastName(?string $fullName): string
    {
        if (empty($fullName)) {
            return 'Customer';
        }

        $parts = explode(' ', trim($fullName), 2);

        return $parts[1] ?? $parts[0] ?? 'Customer';
    }

    /**
     * Validate Paymob callback HMAC
     */
    private function isValidHmac(array $callbackData, string $receivedHmac): bool
    {
        $hmacSecret = $this->paymentMethod->getSetting('hmac_secret');

        if (empty($hmacSecret)) {
            return false;
        }

        // Paymob defines a specific list and order of fields for HMAC calculation.
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
