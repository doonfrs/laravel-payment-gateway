<?php

namespace Trinavo\PaymentGateway\Plugins\Geidea;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Trinavo\PaymentGateway\Configuration\CheckboxField;
use Trinavo\PaymentGateway\Configuration\PasswordField;
use Trinavo\PaymentGateway\Configuration\SelectField;
use Trinavo\PaymentGateway\Configuration\TextField;
use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\CallbackResponse;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Models\RefundResponse;

class GeideaPaymentPlugin extends PaymentPluginInterface
{
    private const API_URLS = [
        'KSA' => 'https://api.ksamerchant.geidea.net',
        'Egypt' => 'https://api.merchant.geidea.net',
        'UAE' => 'https://api.geidea.ae',
    ];

    private const HPP_URLS = [
        'KSA' => 'https://www.ksamerchant.geidea.net',
        'Egypt' => 'https://www.merchant.geidea.net',
        'UAE' => 'https://payments.geidea.ae',
    ];

    public static function getLogoUrl(): string
    {
        return asset('vendor/payment-gateway/imgs/geidea.png');
    }

    public static function getMoreInfoUrl(): string
    {
        return 'https://geidea.net';
    }

    public static function getSupportedCountries(): array
    {
        return ['SA', 'EG', 'AE'];
    }

    public function getName(): string
    {
        return __('Geidea');
    }

    public function getDescription(): string
    {
        return __('Accept payments via Geidea (cards, mada, Apple Pay, Google Pay across KSA, Egypt, UAE).');
    }

    public function getConfigurationFields(): array
    {
        return [
            new TextField(
                name: 'public_key_test',
                label: 'Merchant Public Key (Test)',
                required: true,
                encrypted: true,
                description: 'Your Geidea test merchant public key (used as username for API authentication in test mode).'
            ),
            new PasswordField(
                name: 'api_password_test',
                label: 'API Password (Test)',
                required: true,
                description: 'Your Geidea test API password (used as password for API authentication in test mode).'
            ),
            new TextField(
                name: 'mid_test',
                label: 'MID (Test)',
                required: false,
                encrypted: false,
                description: 'Your Geidea test Merchant ID (stored for reference).'
            ),
            new TextField(
                name: 'public_key_live',
                label: 'Merchant Public Key (Live)',
                required: false,
                encrypted: true,
                description: 'Your Geidea live merchant public key (used as username for API authentication in live mode).'
            ),
            new PasswordField(
                name: 'api_password_live',
                label: 'API Password (Live)',
                required: false,
                description: 'Your Geidea live API password (used as password for API authentication in live mode).'
            ),
            new TextField(
                name: 'mid_live',
                label: 'MID (Live)',
                required: false,
                encrypted: false,
                description: 'Your Geidea live Merchant ID (stored for reference).'
            ),
            new SelectField(
                name: 'region',
                label: 'Region',
                options: [
                    'KSA' => 'Saudi Arabia (KSA)',
                    'Egypt' => 'Egypt',
                    'UAE' => 'United Arab Emirates (UAE)',
                ],
                required: true,
                default: 'KSA',
                description: 'Select the Geidea region for your merchant account.'
            ),
            new CheckboxField(
                name: 'test_mode',
                label: 'Test Mode',
                default: true,
                description: 'Enable test mode for Geidea payments.'
            ),
        ];
    }

    public function validateConfiguration(): bool
    {
        $testMode = $this->paymentMethod->getSetting('test_mode', true);

        if ($testMode) {
            return ! empty($this->paymentMethod->getSetting('public_key_test'))
                && ! empty($this->paymentMethod->getSetting('api_password_test'));
        }

        return ! empty($this->paymentMethod->getSetting('public_key_live'))
            && ! empty($this->paymentMethod->getSetting('api_password_live'));
    }

    public function processPayment(PaymentOrder $paymentOrder)
    {
        Log::info('Geidea Processing Started', [
            'order_code' => $paymentOrder->order_code,
            'amount' => $paymentOrder->amount,
            'currency' => $paymentOrder->currency,
        ]);

        if (! $this->paymentMethod->relationLoaded('settings')) {
            $this->paymentMethod->load('settings');
        }

        try {
            $publicKey = $this->getPublicKey();
            $apiPassword = $this->getApiPassword();

            if (empty($publicKey) || empty($apiPassword)) {
                throw new \Exception('Geidea credentials are not configured.');
            }

            $amount = round($paymentOrder->amount, 2);
            $currency = $paymentOrder->currency ?? 'SAR';
            $merchantReferenceId = $paymentOrder->order_code;
            $timestamp = now()->format('m/d/Y h:i:s A');
            $callbackUrl = $this->getCallbackUrl();

            $signature = $this->generateSignature(
                $publicKey,
                number_format($amount, 2, '.', ''),
                $currency,
                $merchantReferenceId,
                $timestamp,
                $apiPassword
            );

            $sessionData = [
                'amount' => $amount,
                'currency' => $currency,
                'timestamp' => $timestamp,
                'signature' => $signature,
                'merchantReferenceId' => $merchantReferenceId,
                'callbackUrl' => $callbackUrl,
                'returnUrl' => $callbackUrl,
                'language' => app()->getLocale() === 'ar' ? 'ar' : 'en',
            ];

            if ($paymentOrder->customer_email) {
                $sessionData['customer'] = [
                    'email' => $paymentOrder->customer_email,
                ];
                if ($paymentOrder->customer_phone) {
                    $sessionData['customer']['phoneNumber'] = $paymentOrder->customer_phone;
                }
            }

            Log::info('Geidea Create Session Request', [
                'order_code' => $paymentOrder->order_code,
            ]);

            $baseUrl = $this->getApiBaseUrl();
            $response = Http::withBasicAuth($publicKey, $apiPassword)
                ->post($baseUrl . '/payment-intent/api/v2/direct/session', $sessionData);

            Log::info('Geidea Create Session Response', [
                'status_code' => $response->status(),
                'response_body' => $response->body(),
            ]);

            if (! $response->successful()) {
                $errorData = $response->json();
                $errorMessage = $errorData['detailedResponseMessage'] ?? $errorData['responseMessage'] ?? $response->body();
                throw new \Exception('Geidea: ' . $errorMessage);
            }

            $responseData = $response->json();
            $responseCode = $responseData['responseCode'] ?? null;

            if ($responseCode !== '000') {
                throw new \Exception('Geidea session creation failed: ' . ($responseData['responseMessage'] ?? 'Unknown error'));
            }

            $sessionId = $responseData['session']['id'] ?? null;

            if (empty($sessionId)) {
                throw new \Exception('Geidea did not return a session ID.');
            }

            $paymentOrder->update([
                'payment_data' => array_merge($paymentOrder->payment_data ?? [], [
                    'geidea_session_id' => $sessionId,
                    'geidea_merchant_reference_id' => $merchantReferenceId,
                ]),
            ]);

            $hppUrl = $this->getHppBaseUrl() . '/hpp/checkout/?' . $sessionId;

            return redirect()->away($hppUrl);
        } catch (\Exception $e) {
            report($e);

            return view('payment-gateway::plugins.geidea-payment-error', [
                'paymentOrder' => $paymentOrder,
                'paymentMethod' => $this->paymentMethod,
                'failureUrl' => $this->getFailureUrl($paymentOrder),
                'errorMessage' => $e->getMessage(),
            ]);
        }
    }

    public function handleCallback(array $callbackData): CallbackResponse
    {
        Log::info('Geidea Callback Received', [
            'raw_callback_data' => $callbackData,
        ]);

        $orderId = $callbackData['order']['orderId']
            ?? $callbackData['orderId']
            ?? null;
        $merchantReferenceId = $callbackData['order']['merchantReferenceId']
            ?? $callbackData['merchantReferenceId']
            ?? null;
        $orderCode = $merchantReferenceId ?? 'unknown';

        try {
            $publicKey = $this->getPublicKey();
            $apiPassword = $this->getApiPassword();

            if ($orderId) {
                $baseUrl = $this->getApiBaseUrl();
                $response = Http::withBasicAuth($publicKey, $apiPassword)
                    ->get($baseUrl . '/pgw/api/v1/direct/order/' . $orderId);

                if ($response->successful()) {
                    $orderData = $response->json();
                    $orderStatus = $orderData['order']['status'] ?? $orderData['status'] ?? null;
                    $detailedStatus = $orderData['order']['detailedStatus'] ?? $orderData['detailedStatus'] ?? null;
                    $fetchedMerchantRef = $orderData['order']['merchantReferenceId'] ?? $orderData['merchantReferenceId'] ?? $orderCode;

                    Log::info('Geidea Order Verification', [
                        'order_code' => $fetchedMerchantRef,
                        'order_id' => $orderId,
                        'status' => $orderStatus,
                        'detailed_status' => $detailedStatus,
                    ]);

                    if ($orderStatus === 'Success') {
                        return CallbackResponse::success(
                            orderCode: (string) $fetchedMerchantRef,
                            transactionId: (string) $orderId,
                            message: 'Payment completed successfully via Geidea',
                            additionalData: [
                                'geidea_order_id' => $orderId,
                                'geidea_status' => $orderStatus,
                                'geidea_detailed_status' => $detailedStatus,
                            ]
                        );
                    }

                    return CallbackResponse::failure(
                        orderCode: (string) $fetchedMerchantRef,
                        message: __('payment_failed'),
                        status: 'failed',
                        additionalData: [
                            'geidea_order_id' => $orderId,
                            'geidea_status' => $orderStatus,
                            'geidea_detailed_status' => $detailedStatus,
                        ]
                    );
                }
            }

            $responseCode = $callbackData['responseCode'] ?? $callbackData['order']['responseCode'] ?? null;

            if ($responseCode === '000') {
                return CallbackResponse::success(
                    orderCode: (string) $orderCode,
                    transactionId: (string) ($orderId ?? 'unknown'),
                    message: 'Payment completed successfully via Geidea',
                    additionalData: [
                        'geidea_order_id' => $orderId,
                    ]
                );
            }

            return CallbackResponse::failure(
                orderCode: (string) $orderCode,
                message: __('payment_failed'),
                status: 'failed',
                additionalData: [
                    'geidea_order_id' => $orderId,
                    'geidea_response_code' => $responseCode,
                ]
            );
        } catch (\Exception $e) {
            report($e);

            return CallbackResponse::failure(
                orderCode: (string) $orderCode,
                message: 'Error verifying Geidea payment'
            );
        }
    }

    public function refund(PaymentOrder $paymentOrder): RefundResponse
    {
        $geideaOrderId = $paymentOrder->payment_data['geidea_order_id'] ?? null;

        if (! $geideaOrderId) {
            return RefundResponse::failure(
                orderCode: $paymentOrder->order_code,
                message: 'Geidea order ID not found in payment data'
            );
        }

        try {
            $publicKey = $this->getPublicKey();
            $apiPassword = $this->getApiPassword();
            $baseUrl = $this->getApiBaseUrl();

            $response = Http::withBasicAuth($publicKey, $apiPassword)
                ->post($baseUrl . '/pgw/api/v1/direct/refund', [
                    'orderId' => $geideaOrderId,
                ]);

            Log::info('Geidea Refund Response', [
                'order_code' => $paymentOrder->order_code,
                'geidea_order_id' => $geideaOrderId,
                'status_code' => $response->status(),
                'response_body' => $response->body(),
            ]);

            if (! $response->successful()) {
                return RefundResponse::failure(
                    orderCode: $paymentOrder->order_code,
                    message: 'Geidea refund request failed'
                );
            }

            $refundData = $response->json();
            $responseCode = $refundData['responseCode'] ?? null;

            if ($responseCode === '000') {
                $refundOrderId = $refundData['order']['orderId'] ?? $refundData['orderId'] ?? null;

                return RefundResponse::success(
                    orderCode: $paymentOrder->order_code,
                    refundedAmount: $paymentOrder->amount,
                    refundTransactionId: $refundOrderId,
                    originalTransactionId: $geideaOrderId,
                    message: 'Refund processed successfully via Geidea'
                );
            }

            return RefundResponse::failure(
                orderCode: $paymentOrder->order_code,
                message: 'Geidea refund was declined. Response: ' . ($refundData['responseMessage'] ?? 'Unknown')
            );
        } catch (\Exception $e) {
            report($e);

            return RefundResponse::failure(
                orderCode: $paymentOrder->order_code,
                message: 'Error processing Geidea refund'
            );
        }
    }

    private function getPublicKey(): string
    {
        $testMode = $this->paymentMethod->getSetting('test_mode', true);

        return (string) $this->paymentMethod->getSetting(
            $testMode ? 'public_key_test' : 'public_key_live',
            ''
        );
    }

    private function getApiPassword(): string
    {
        $testMode = $this->paymentMethod->getSetting('test_mode', true);

        return (string) $this->paymentMethod->getSetting(
            $testMode ? 'api_password_test' : 'api_password_live',
            ''
        );
    }

    private function getMid(): string
    {
        $testMode = $this->paymentMethod->getSetting('test_mode', true);

        return (string) $this->paymentMethod->getSetting(
            $testMode ? 'mid_test' : 'mid_live',
            ''
        );
    }

    private function getApiBaseUrl(): string
    {
        $region = $this->paymentMethod->getSetting('region', 'KSA');

        return self::API_URLS[$region] ?? self::API_URLS['KSA'];
    }

    private function getHppBaseUrl(): string
    {
        $region = $this->paymentMethod->getSetting('region', 'KSA');

        return self::HPP_URLS[$region] ?? self::HPP_URLS['KSA'];
    }

    private function generateSignature(
        string $publicKey,
        string $amount,
        string $currency,
        string $merchantReferenceId,
        string $timestamp,
        string $apiPassword
    ): string {
        $data = $publicKey . $amount . $currency . $merchantReferenceId . $timestamp;
        $hash = hash_hmac('sha256', $data, $apiPassword, true);

        return base64_encode($hash);
    }
}
