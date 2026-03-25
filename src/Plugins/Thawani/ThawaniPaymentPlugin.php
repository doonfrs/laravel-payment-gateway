<?php

namespace Trinavo\PaymentGateway\Plugins\Thawani;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Trinavo\PaymentGateway\Configuration\CheckboxField;
use Trinavo\PaymentGateway\Configuration\TextField;
use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\CallbackResponse;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Models\RefundResponse;

class ThawaniPaymentPlugin extends PaymentPluginInterface
{
    private const UAT_API_URL = 'https://uatcheckout.thawani.om/api/v1';

    private const PRODUCTION_API_URL = 'https://checkout.thawani.om/api/v1';

    private const UAT_REDIRECT_URL = 'https://uatcheckout.thawani.om';

    private const PRODUCTION_REDIRECT_URL = 'https://checkout.thawani.om';

    public static function getLogoUrl(): string
    {
        return asset('vendor/payment-gateway/imgs/thawani.png');
    }

    public static function getMoreInfoUrl(): string
    {
        return 'https://thawani.om';
    }

    public function getName(): string
    {
        return __('Thawani');
    }

    public function getDescription(): string
    {
        return __('Accept payments via Thawani Checkout, a payment gateway from Oman supporting card payments.');
    }

    public function getConfigurationFields(): array
    {
        return [
            new TextField(
                name: 'secret_key',
                label: 'Secret Key',
                required: true,
                encrypted: true,
                description: 'Your Thawani secret key for API authentication.'
            ),
            new TextField(
                name: 'publishable_key',
                label: 'Publishable Key',
                required: true,
                encrypted: true,
                description: 'Your Thawani publishable key (used in the checkout redirect URL).'
            ),
            new CheckboxField(
                name: 'test_mode',
                label: 'Test Mode (UAT)',
                default: true,
                description: 'Enable UAT/test mode for Thawani payments.'
            ),
        ];
    }

    public function validateConfiguration(): bool
    {
        return ! empty($this->paymentMethod->getSetting('secret_key'))
            && ! empty($this->paymentMethod->getSetting('publishable_key'));
    }

    public function processPayment(PaymentOrder $paymentOrder)
    {
        Log::info('Thawani Processing Started', [
            'order_code' => $paymentOrder->order_code,
            'amount' => $paymentOrder->amount,
            'currency' => $paymentOrder->currency,
        ]);

        if (! $this->paymentMethod->relationLoaded('settings')) {
            $this->paymentMethod->load('settings');
        }

        try {
            $secretKey = $this->paymentMethod->getSetting('secret_key');
            $publishableKey = $this->paymentMethod->getSetting('publishable_key');

            if (empty($secretKey) || empty($publishableKey)) {
                throw new \Exception('Thawani credentials are not configured.');
            }

            $callbackUrl = $this->getCallbackUrl();
            $failureUrl = $this->getFailureUrl($paymentOrder);
            $amountInBaisa = $this->convertToBaisa($paymentOrder->amount);

            $sessionData = [
                'client_reference_id' => $paymentOrder->order_code,
                'mode' => 'payment',
                'products' => [
                    [
                        'name' => Str::limit($paymentOrder->description ?: 'Payment for order ' . $paymentOrder->order_code, 40),
                        'unit_amount' => $amountInBaisa,
                        'quantity' => 1,
                    ],
                ],
                'success_url' => $callbackUrl,
                'cancel_url' => $failureUrl,
                'metadata' => [
                    'order_code' => $paymentOrder->order_code,
                    'Customer name' => $paymentOrder->customer_name ?: 'Customer',
                    'Customer email' => $paymentOrder->customer_email ?: '',
                    'Customer phone' => $paymentOrder->customer_phone ?: '',
                ],
            ];

            Log::info('Thawani Create Session Request', [
                'order_code' => $paymentOrder->order_code,
            ]);

            $baseUrl = $this->getApiBaseUrl();
            $response = Http::withHeaders([
                'thawani-api-key' => $secretKey,
                'Content-Type' => 'application/json',
            ])->post($baseUrl . '/checkout/session', $sessionData);

            Log::info('Thawani Create Session Response', [
                'status_code' => $response->status(),
                'response_body' => $response->body(),
            ]);

            if (! $response->successful()) {
                $errorData = $response->json();
                $errorMessage = $errorData['description'] ?? $response->body();
                throw new \Exception('Thawani: ' . $errorMessage);
            }

            $responseData = $response->json();
            $success = $responseData['success'] ?? false;

            if (! $success) {
                throw new \Exception('Thawani session creation failed: ' . ($responseData['description'] ?? 'Unknown error'));
            }

            $sessionId = $responseData['data']['session_id'] ?? null;
            $invoice = $responseData['data']['invoice'] ?? null;

            if (empty($sessionId)) {
                throw new \Exception('Thawani did not return a session ID.');
            }

            $paymentOrder->update([
                'payment_data' => array_merge($paymentOrder->payment_data ?? [], [
                    'thawani_session_id' => $sessionId,
                    'thawani_invoice' => $invoice,
                ]),
            ]);

            $redirectBaseUrl = $this->getRedirectBaseUrl();
            $redirectUrl = $redirectBaseUrl . '/pay/' . $sessionId . '?key=' . $publishableKey;

            return redirect()->away($redirectUrl);
        } catch (\Exception $e) {
            report($e);

            return view('payment-gateway::plugins.thawani-payment-error', [
                'paymentOrder' => $paymentOrder,
                'paymentMethod' => $this->paymentMethod,
                'failureUrl' => $this->getFailureUrl($paymentOrder),
                'errorMessage' => $e->getMessage(),
            ]);
        }
    }

    public function handleCallback(array $callbackData): CallbackResponse
    {
        Log::info('Thawani Callback Received', [
            'raw_callback_data' => $callbackData,
        ]);

        $sessionId = $callbackData['session_id']
            ?? $callbackData['data']['session_id']
            ?? null;
        $orderCode = $callbackData['client_reference_id']
            ?? $callbackData['order_code']
            ?? null;

        try {
            $secretKey = $this->paymentMethod->getSetting('secret_key');
            $baseUrl = $this->getApiBaseUrl();

            if (! $sessionId && $orderCode) {
                $refResponse = Http::withHeaders([
                    'thawani-api-key' => $secretKey,
                ])->get($baseUrl . '/checkout/reference/' . $orderCode);

                if ($refResponse->successful()) {
                    $refData = $refResponse->json();
                    $sessionId = $refData['data']['session_id'] ?? null;
                    $orderCode = $refData['data']['client_reference_id'] ?? $orderCode;
                }
            }

            if (! $sessionId) {
                return CallbackResponse::failure(
                    orderCode: $orderCode ?? 'unknown',
                    message: 'Thawani session ID not found in callback data'
                );
            }

            $response = Http::withHeaders([
                'thawani-api-key' => $secretKey,
            ])->get($baseUrl . '/checkout/session/' . $sessionId);

            if (! $response->successful()) {
                return CallbackResponse::failure(
                    orderCode: $orderCode ?? 'unknown',
                    message: 'Failed to verify Thawani session status'
                );
            }

            $sessionData = $response->json();
            $paymentStatus = $sessionData['data']['payment_status'] ?? null;
            $fetchedOrderCode = $sessionData['data']['client_reference_id'] ?? $orderCode ?? 'unknown';
            $invoice = $sessionData['data']['invoice'] ?? null;

            Log::info('Thawani Session Verification', [
                'order_code' => $fetchedOrderCode,
                'session_id' => $sessionId,
                'payment_status' => $paymentStatus,
            ]);

            if ($paymentStatus === 'paid') {
                return CallbackResponse::success(
                    orderCode: (string) $fetchedOrderCode,
                    transactionId: (string) ($invoice ?? $sessionId),
                    message: 'Payment completed successfully via Thawani',
                    additionalData: [
                        'thawani_session_id' => $sessionId,
                        'thawani_invoice' => $invoice,
                        'thawani_payment_status' => $paymentStatus,
                    ]
                );
            }

            return CallbackResponse::failure(
                orderCode: (string) $fetchedOrderCode,
                message: __('payment_failed'),
                status: 'failed',
                additionalData: [
                    'thawani_session_id' => $sessionId,
                    'thawani_invoice' => $invoice,
                    'thawani_payment_status' => $paymentStatus,
                ]
            );
        } catch (\Exception $e) {
            report($e);

            return CallbackResponse::failure(
                orderCode: $orderCode ?? 'unknown',
                message: 'Error verifying Thawani payment'
            );
        }
    }

    public function refund(PaymentOrder $paymentOrder): RefundResponse
    {
        $invoice = $paymentOrder->payment_data['thawani_invoice'] ?? null;

        if (! $invoice) {
            return RefundResponse::failure(
                orderCode: $paymentOrder->order_code,
                message: 'Thawani invoice/payment ID not found in payment data'
            );
        }

        try {
            $secretKey = $this->paymentMethod->getSetting('secret_key');
            $baseUrl = $this->getApiBaseUrl();
            $amountInBaisa = $this->convertToBaisa($paymentOrder->amount);

            $response = Http::withHeaders([
                'thawani-api-key' => $secretKey,
                'Content-Type' => 'application/json',
            ])->post($baseUrl . '/refunds', [
                'payment_id' => $invoice,
                'reason' => 'Merchant initiated refund',
                'metadata' => [
                    'order_code' => $paymentOrder->order_code,
                ],
                'amount' => $amountInBaisa,
            ]);

            Log::info('Thawani Refund Response', [
                'order_code' => $paymentOrder->order_code,
                'invoice' => $invoice,
                'status_code' => $response->status(),
                'response_body' => $response->body(),
            ]);

            if (! $response->successful()) {
                return RefundResponse::failure(
                    orderCode: $paymentOrder->order_code,
                    message: 'Thawani refund request failed'
                );
            }

            $refundData = $response->json();
            $success = $refundData['success'] ?? false;

            if ($success) {
                $refundId = $refundData['data']['refund_id'] ?? null;

                return RefundResponse::success(
                    orderCode: $paymentOrder->order_code,
                    refundedAmount: $paymentOrder->amount,
                    refundTransactionId: $refundId,
                    originalTransactionId: $invoice,
                    message: 'Refund processed successfully via Thawani'
                );
            }

            return RefundResponse::failure(
                orderCode: $paymentOrder->order_code,
                message: 'Thawani refund was declined: ' . ($refundData['description'] ?? 'Unknown')
            );
        } catch (\Exception $e) {
            report($e);

            return RefundResponse::failure(
                orderCode: $paymentOrder->order_code,
                message: 'Error processing Thawani refund'
            );
        }
    }

    private function getApiBaseUrl(): string
    {
        $testMode = $this->paymentMethod->getSetting('test_mode', true);

        return $testMode ? self::UAT_API_URL : self::PRODUCTION_API_URL;
    }

    private function getRedirectBaseUrl(): string
    {
        $testMode = $this->paymentMethod->getSetting('test_mode', true);

        return $testMode ? self::UAT_REDIRECT_URL : self::PRODUCTION_REDIRECT_URL;
    }

    private function convertToBaisa(float $amount): int
    {
        return (int) round($amount * 1000);
    }
}
