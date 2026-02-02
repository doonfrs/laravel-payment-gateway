<?php

namespace Trinavo\PaymentGateway\Plugins\PayTabs;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Trinavo\PaymentGateway\Configuration\CheckboxField;
use Trinavo\PaymentGateway\Configuration\SelectField;
use Trinavo\PaymentGateway\Configuration\TextField;
use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Models\RefundResponse;

class PayTabsPaymentPlugin extends PaymentPluginInterface
{
    public function getName(): string
    {
        return __('PayTabs Payment Plugin');
    }

    public function getDescription(): string
    {
        return __('Integrate PayTabs payment gateway using Hosted Payment Page.');
    }

    public function getConfigurationFields(): array
    {
        return [
            new SelectField(
                name: 'region',
                label: 'Region',
                required: true,
                options: [
                    'ae' => 'UAE',
                    'sa' => 'Saudi Arabia (KSA)',
                    'eg' => 'Egypt',
                    'om' => 'Oman',
                    'jo' => 'Jordan',
                    'kw' => 'Kuwait',
                    'iq' => 'Iraq',
                    'ma' => 'Morocco',
                    'qa' => 'Qatar',
                    'global' => 'Global',
                ],
                default: 'ae',
                description: 'Select the region for PayTabs. Each region has a different API base URL.'
            ),

            new TextField(
                name: 'profile_id',
                label: 'Profile ID',
                required: true,
                encrypted: false,
                description: 'Your PayTabs Profile ID from the dashboard.'
            ),

            new TextField(
                name: 'server_key',
                label: 'Server Key',
                required: true,
                encrypted: true,
                description: 'Your PayTabs Server Key (API key) for server-side requests.'
            ),

            new SelectField(
                name: 'paypage_lang',
                label: 'Payment Page Language',
                required: false,
                options: [
                    'en' => 'English',
                    'ar' => 'Arabic',
                    'fr' => 'French',
                ],
                default: 'en',
                description: 'The language of the hosted payment page.'
            ),

            new SelectField(
                name: 'cart_currency',
                label: 'Cart Currency',
                required: false,
                options: [
                    '_order' => 'Use order currency',
                    'AED' => 'AED - UAE Dirham',
                    'SAR' => 'SAR - Saudi Riyal',
                    'EGP' => 'EGP - Egyptian Pound',
                    'JOD' => 'JOD - Jordanian Dinar',
                    'KWD' => 'KWD - Kuwaiti Dinar',
                    'BHD' => 'BHD - Bahraini Dinar',
                    'OMR' => 'OMR - Omani Rial',
                    'QAR' => 'QAR - Qatari Riyal',
                    'USD' => 'USD - US Dollar',
                    'EUR' => 'EUR - Euro',
                    'GBP' => 'GBP - British Pound',
                    'MAD' => 'MAD - Moroccan Dirham',
                    'IQD' => 'IQD - Iraqi Dinar',
                ],
                default: '_order',
                description: 'Override currency for PayTabs. Use this if you get "Currency not available" error. Must match a currency enabled on your PayTabs profile. Select "Use order currency" to use the order currency.'
            ),

            new CheckboxField(
                name: 'test_mode',
                label: 'Test Mode',
                default: true,
                description: 'Enable test mode for PayTabs payments (use test credentials from PayTabs dashboard).'
            ),
        ];
    }

    public function validateConfiguration(): bool
    {
        return ! empty($this->paymentMethod->getSetting('region')) &&
               ! empty($this->paymentMethod->getSetting('profile_id')) &&
               ! empty($this->paymentMethod->getSetting('server_key'));
    }

    public function processPayment(PaymentOrder $paymentOrder)
    {
        Log::info('PayTabs Payment Processing Started', [
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
            $paymentRequest = $this->createPaymentRequest($paymentOrder);

            if (empty($paymentRequest['redirect_url'])) {
                throw new \Exception('PayTabs did not return a redirect URL');
            }

            $paymentOrder->update([
                'remote_transaction_id' => $paymentRequest['tran_ref'] ?? null,
            ]);

            Log::info('PayTabs Payment Redirect URL Generated', [
                'order_code' => $paymentOrder->order_code,
                'tran_ref' => $paymentRequest['tran_ref'] ?? null,
            ]);

            return redirect()->away($paymentRequest['redirect_url']);
        } catch (\Exception $e) {
            report($e);

            return view('payment-gateway::plugins.paytabs-payment-error', [
                'paymentOrder' => $paymentOrder,
                'paymentMethod' => $this->paymentMethod,
                'failureUrl' => $this->getFailureUrl($paymentOrder),
                'errorMessage' => $e->getMessage(),
            ]);
        }
    }

    public function handleCallback(array $callbackData): \Trinavo\PaymentGateway\Models\CallbackResponse
    {
        Log::info('PayTabs Callback Received', [
            'raw_callback_data' => $callbackData,
            'callback_keys' => array_keys($callbackData),
        ]);

        // Use only PayTabs fields - exclude Laravel session keys (_token, etc.) that get merged in
        $paytabsData = $this->extractPayTabsCallbackData($callbackData);

        $signature = $paytabsData['signature'] ?? null;

        if (! $signature || ! $this->isValidRedirectSignature($paytabsData, $signature)) {
            Log::warning('PayTabs Callback Signature Invalid', [
                'callback_data' => $paytabsData,
            ]);

            $orderCode = $paytabsData['cartId'] ?? $paytabsData['cart_id'] ?? 'unknown';

            return \Trinavo\PaymentGateway\Models\CallbackResponse::failure(
                orderCode: (string) $orderCode,
                message: 'Invalid callback signature from PayTabs'
            );
        }

        // PayTabs return uses camelCase: cartId, tranRef
        $respStatus = $paytabsData['respStatus'] ?? null;
        $orderCode = $paytabsData['cartId'] ?? $paytabsData['cart_id'] ?? null;
        $tranRef = $paytabsData['tranRef'] ?? $paytabsData['tran_ref'] ?? null;

        if (! $orderCode) {
            Log::error('PayTabs Callback Missing Order Code', [
                'callback_data' => $callbackData,
            ]);

            return \Trinavo\PaymentGateway\Models\CallbackResponse::failure(
                orderCode: 'unknown',
                message: 'Order code (cart_id) is required'
            );
        }

        // respStatus 'A' = Authorized/Success
        if ($respStatus === 'A') {
            return \Trinavo\PaymentGateway\Models\CallbackResponse::success(
                orderCode: (string) $orderCode,
                transactionId: $tranRef ? (string) $tranRef : 'paytabs_'.uniqid(),
                message: 'Payment completed successfully via PayTabs',
                additionalData: [
                    'paytabs_tran_ref' => $tranRef,
                ]
            );
        }

        // Optional: verify via payment/query API for extra security
        if ($tranRef) {
            $verifyResult = $this->verifyPayment($tranRef);
            if ($verifyResult !== null && $verifyResult['success']) {
                return \Trinavo\PaymentGateway\Models\CallbackResponse::success(
                    orderCode: (string) $orderCode,
                    transactionId: (string) $tranRef,
                    message: 'Payment verified successfully via PayTabs',
                    additionalData: [
                        'paytabs_tran_ref' => $tranRef,
                    ]
                );
            }
        }

        return \Trinavo\PaymentGateway\Models\CallbackResponse::failure(
            orderCode: (string) $orderCode,
            message: __('payment_failed'),
            status: $respStatus,
            additionalData: [
                'paytabs_tran_ref' => $tranRef,
                'paytabs_resp_status' => $respStatus,
            ]
        );
    }

    public function refund(PaymentOrder $paymentOrder): RefundResponse
    {
        return RefundResponse::failure(
            orderCode: $paymentOrder->order_code,
            message: 'Refunds are not yet implemented for PayTabs API'
        );
    }

    /**
     * Create payment request and get redirect URL
     */
    private function createPaymentRequest(PaymentOrder $paymentOrder): array
    {
        $baseUrl = $this->getBaseUrl();
        $url = rtrim($baseUrl, '/').'/payment/request';

        $profileId = (int) $this->paymentMethod->getSetting('profile_id');
        $serverKey = $this->paymentMethod->getSetting('server_key');

        if (empty($serverKey)) {
            throw new \Exception('PayTabs Server Key is not configured.');
        }

        $currencyOverride = $this->paymentMethod->getSetting('cart_currency');
        $currency = ! empty($currencyOverride) && $currencyOverride !== '_order'
            ? $currencyOverride
            : ($paymentOrder->currency ?? $this->getDefaultCurrencyForRegion());
        $amount = round($paymentOrder->amount, 2);
        $callbackUrl = $this->getCallbackUrl();

        $customerDetails = [
            'name' => $paymentOrder->customer_name ?: 'Customer',
            'email' => $paymentOrder->customer_email ?: 'customer@example.com',
            'phone' => $paymentOrder->customer_phone ?: '+971500000000',
            'street1' => $paymentOrder->customer_data['street1'] ?? 'N/A',
            'city' => $paymentOrder->customer_data['city'] ?? 'Dubai',
            'state' => $paymentOrder->customer_data['state'] ?? 'DU',
            'country' => $paymentOrder->customer_data['country'] ?? $this->getCountryCodeForRegion(),
            'zip' => $paymentOrder->customer_data['zip'] ?? $paymentOrder->customer_data['postal_code'] ?? '00000',
        ];

        $data = [
            'profile_id' => $profileId,
            'tran_type' => 'sale',
            'tran_class' => 'ecom',
            'cart_id' => $paymentOrder->order_code,
            'cart_currency' => $currency,
            'cart_amount' => $amount,
            'cart_description' => $paymentOrder->description ?: 'Payment for order '.$paymentOrder->order_code,
            'paypage_lang' => $this->paymentMethod->getSetting('paypage_lang', 'en'),
            'return' => $callbackUrl,
            'callback' => $callbackUrl,
            'customer_details' => $customerDetails,
            'user_defined' => [
                'udf1' => $paymentOrder->order_code,
            ],
        ];

        Log::info('PayTabs Create Payment Request', [
            'url' => $url,
            'cart_id' => $data['cart_id'],
            'cart_amount' => $data['cart_amount'],
        ]);

        $response = Http::withHeaders([
            'Authorization' => $serverKey,
            'Content-Type' => 'application/json',
        ])->post($url, $data);

        Log::info('PayTabs Create Payment Response', [
            'status_code' => $response->status(),
            'response_body' => $response->body(),
        ]);

        if (! $response->successful()) {
            $responseData = $response->json();
            $errorCode = $responseData['code'] ?? null;
            $errorMessage = $responseData['message'] ?? $response->body();

            Log::error('PayTabs create payment request failed', [
                'order_code' => $paymentOrder->order_code,
                'status_code' => $response->status(),
                'response_body' => $response->body(),
            ]);

            if ($errorCode === 206) {
                throw new \Exception(
                    __('paytabs_currency_not_available', ['currency' => $currency])
                );
            }

            throw new \Exception(__('payment_gateway_error').': '.$errorMessage);
        }

        $responseData = $response->json();

        if (isset($responseData['message']) && ! empty($responseData['message'])) {
            throw new \Exception('PayTabs: '.$responseData['message']);
        }

        return $responseData;
    }

    /**
     * Verify payment status via payment/query API
     */
    private function verifyPayment(string $tranRef): ?array
    {
        try {
            $baseUrl = $this->getBaseUrl();
            $url = rtrim($baseUrl, '/').'/payment/query';
            $serverKey = $this->paymentMethod->getSetting('server_key');

            $response = Http::withHeaders([
                'Authorization' => $serverKey,
                'Content-Type' => 'application/json',
            ])->post($url, [
                'tran_ref' => $tranRef,
            ]);

            if (! $response->successful()) {
                return null;
            }

            $result = $response->json();
            $paymentResult = $result['payment_result'] ?? [];
            $responseStatus = $paymentResult['response_status'] ?? null;

            return [
                'success' => $responseStatus === 'A',
                'response_status' => $responseStatus,
            ];
        } catch (\Exception $e) {
            Log::warning('PayTabs verify payment failed', [
                'tran_ref' => $tranRef,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Extract only PayTabs callback fields - exclude Laravel/session keys merged by PaymentController
     */
    private function extractPayTabsCallbackData(array $callbackData): array
    {
        $paytabsKeys = [
            'acquirerMessage', 'acquirerRRN', 'cartId', 'customerEmail', 'respCode',
            'respMessage', 'respStatus', 'signature', 'token', 'tranRef',
            'cart_id', 'tran_ref', // legacy snake_case
        ];

        return array_intersect_key($callbackData, array_flip($paytabsKeys));
    }

    /**
     * Validate PayTabs redirect signature (HMAC-SHA256)
     * Per PayTabs docs: remove signature, filter empty, ksort, http_build_query, HMAC-SHA256
     */
    private function isValidRedirectSignature(array $postValues, string $receivedSignature): bool
    {
        $serverKey = $this->paymentMethod->getSetting('server_key');

        if (empty($serverKey)) {
            return false;
        }

        $values = $postValues;
        unset($values['signature']);
        $fields = array_filter($values); // Remove empty/null per PayTabs docs
        ksort($fields);
        $query = http_build_query($fields);
        $signature = hash_hmac('sha256', $query, $serverKey);

        return hash_equals($signature, $receivedSignature);
    }

    /**
     * Get PayTabs base URL based on region
     */
    private function getBaseUrl(): string
    {
        $region = strtolower($this->paymentMethod->getSetting('region', 'ae'));

        $map = [
            'ae' => 'https://secure.paytabs.com',
            'sa' => 'https://secure.paytabs.sa',
            'eg' => 'https://secure-egypt.paytabs.com',
            'om' => 'https://secure-oman.paytabs.com',
            'jo' => 'https://secure-jordan.paytabs.com',
            'kw' => 'https://secure-kuwait.paytabs.com',
            'iq' => 'https://secure-iraq.paytabs.com',
            'ma' => 'https://secure-morocco.paytabs.com',
            'qa' => 'https://secure-doha.paytabs.com',
            'global' => 'https://secure-global.paytabs.com',
        ];

        return $map[$region] ?? 'https://secure.paytabs.com';
    }

    /**
     * Get default currency for region (used when order currency is empty)
     */
    private function getDefaultCurrencyForRegion(): string
    {
        $region = strtolower($this->paymentMethod->getSetting('region', 'ae'));

        $map = [
            'ae' => 'AED',
            'sa' => 'SAR',
            'eg' => 'EGP',
            'om' => 'OMR',
            'jo' => 'JOD',
            'kw' => 'KWD',
            'iq' => 'IQD',
            'ma' => 'MAD',
            'qa' => 'QAR',
            'global' => 'USD',
        ];

        return $map[$region] ?? 'AED';
    }

    /**
     * Get ISO country code for billing based on region
     */
    private function getCountryCodeForRegion(): string
    {
        $region = strtolower($this->paymentMethod->getSetting('region', 'ae'));

        $map = [
            'ae' => 'AE',
            'sa' => 'SA',
            'eg' => 'EG',
            'om' => 'OM',
            'jo' => 'JO',
            'kw' => 'KW',
            'iq' => 'IQ',
            'ma' => 'MA',
            'qa' => 'QA',
            'global' => 'AE',
        ];

        return $map[$region] ?? 'AE';
    }
}
