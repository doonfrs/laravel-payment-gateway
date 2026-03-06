<?php

namespace Trinavo\PaymentGateway\Plugins\Fawry;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Trinavo\PaymentGateway\Configuration\CheckboxField;
use Trinavo\PaymentGateway\Configuration\TextField;
use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\CallbackResponse;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Models\RefundResponse;

class FawryPaymentPlugin extends PaymentPluginInterface
{
    private const STAGING_URL = 'https://atfawry.fawrystaging.com';

    private const PRODUCTION_URL = 'https://www.atfawry.com';

    public function getName(): string
    {
        return __('Fawry Payment Plugin');
    }

    public function getDescription(): string
    {
        return __('Accept payments via Fawry (PayAtFawry, cards, e-wallets). Popular in Egypt.');
    }

    public function getConfigurationFields(): array
    {
        return [
            new TextField(
                name: 'merchant_code',
                label: 'Merchant Code',
                required: true,
                encrypted: false,
                description: 'Your Fawry merchant code.'
            ),
            new TextField(
                name: 'secure_key_test',
                label: 'Secure Key (Test)',
                required: true,
                encrypted: true,
                description: 'Your Fawry secure key for staging/test environment.'
            ),
            new TextField(
                name: 'secure_key_live',
                label: 'Secure Key (Live)',
                required: false,
                encrypted: true,
                description: 'Your Fawry secure key for production environment.'
            ),
            new CheckboxField(
                name: 'test_mode',
                label: 'Test Mode',
                default: true,
                description: 'Enable test/staging mode for Fawry payments.'
            ),
            new TextField(
                name: 'payment_expiry_hours',
                label: 'Payment Expiry (Hours)',
                required: false,
                encrypted: false,
                description: 'Hours before a PayAtFawry reference expires. Default: 24.'
            ),
        ];
    }

    public function validateConfiguration(): bool
    {
        $merchantCode = $this->paymentMethod->getSetting('merchant_code');
        if (empty($merchantCode)) {
            return false;
        }

        $testMode = $this->paymentMethod->getSetting('test_mode', true);

        if ($testMode) {
            return ! empty($this->paymentMethod->getSetting('secure_key_test'));
        }

        return ! empty($this->paymentMethod->getSetting('secure_key_live'));
    }

    public function processPayment(PaymentOrder $paymentOrder)
    {
        Log::info('Fawry Payment Processing Started', [
            'order_code' => $paymentOrder->order_code,
            'amount' => $paymentOrder->amount,
            'currency' => $paymentOrder->currency,
        ]);

        if (! $this->paymentMethod->relationLoaded('settings')) {
            $this->paymentMethod->load('settings');
        }

        try {
            $merchantCode = $this->paymentMethod->getSetting('merchant_code');
            $secureKey = $this->getSecureKey();

            if (empty($merchantCode) || empty($secureKey)) {
                throw new \Exception('Fawry merchant code or secure key is not configured.');
            }

            $baseUrl = $this->getBaseUrl();
            $callbackUrl = $this->getCallbackUrl();
            $amount = number_format(round($paymentOrder->amount, 2), 2, '.', '');
            $expiryHours = (int) $this->paymentMethod->getSetting('payment_expiry_hours', 24);
            $paymentExpiry = (int) ((now()->addHours($expiryHours)->timestamp) * 1000);

            $signature = $this->generateChargeSignature($merchantCode, $paymentOrder->order_code, $secureKey);

            $payload = [
                'merchantCode' => $merchantCode,
                'merchantRefNum' => $paymentOrder->order_code,
                'paymentMethod' => 'PayAtFawry',
                'customerMobile' => $paymentOrder->customer_phone ?: '01000000000',
                'customerEmail' => $paymentOrder->customer_email ?: 'customer@example.com',
                'customerName' => $paymentOrder->customer_name ?: 'Customer',
                'amount' => $amount,
                'description' => $paymentOrder->description ?: 'Payment for order '.$paymentOrder->order_code,
                'language' => 'en-gb',
                'paymentExpiry' => $paymentExpiry,
                'orderWebHookUrl' => $callbackUrl,
                'signature' => $signature,
                'chargeItems' => [
                    [
                        'itemId' => $paymentOrder->order_code,
                        'description' => $paymentOrder->description ?: 'Order '.$paymentOrder->order_code,
                        'price' => $amount,
                        'quantity' => 1,
                    ],
                ],
            ];

            Log::info('Fawry Create Charge Request', [
                'url' => $baseUrl.'/ECommerceWeb/Fawry/payments/charge',
                'order_code' => $paymentOrder->order_code,
            ]);

            $response = Http::post($baseUrl.'/ECommerceWeb/Fawry/payments/charge', $payload);

            Log::info('Fawry Create Charge Response', [
                'status_code' => $response->status(),
                'response_body' => $response->body(),
            ]);

            if (! $response->successful()) {
                throw new \Exception('Fawry API request failed: '.$response->body());
            }

            $responseData = $response->json();
            $statusCode = $responseData['statusCode'] ?? null;

            if ($statusCode !== 200) {
                throw new \Exception('Fawry: '.($responseData['statusDescription'] ?? 'Unknown error'));
            }

            $referenceNumber = $responseData['referenceNumber'] ?? null;

            $paymentOrder->update([
                'payment_data' => array_merge($paymentOrder->payment_data ?? [], [
                    'fawry_reference_number' => $referenceNumber,
                    'fawry_payment_expiry' => $paymentExpiry,
                ]),
            ]);

            Log::info('Fawry Reference Number Generated', [
                'order_code' => $paymentOrder->order_code,
                'reference_number' => $referenceNumber,
            ]);

            return view('payment-gateway::plugins.fawry-payment-pending', [
                'paymentOrder' => $paymentOrder,
                'paymentMethod' => $this->paymentMethod,
                'referenceNumber' => $referenceNumber,
                'expiryHours' => $expiryHours,
                'successUrl' => $this->getSuccessUrl($paymentOrder),
                'failureUrl' => $this->getFailureUrl($paymentOrder),
            ]);
        } catch (\Exception $e) {
            report($e);

            return view('payment-gateway::plugins.fawry-payment-error', [
                'paymentOrder' => $paymentOrder,
                'paymentMethod' => $this->paymentMethod,
                'failureUrl' => $this->getFailureUrl($paymentOrder),
                'errorMessage' => $e->getMessage(),
            ]);
        }
    }

    public function handleCallback(array $callbackData): CallbackResponse
    {
        Log::info('Fawry Callback Received', [
            'raw_callback_data' => $callbackData,
        ]);

        $orderCode = $callbackData['merchantRefNumber'] ?? $callbackData['MerchantRefNo'] ?? null;
        $fawryRefNumber = $callbackData['fawryRefNumber'] ?? $callbackData['FawryRefNo'] ?? null;
        $orderStatus = $callbackData['orderStatus'] ?? $callbackData['OrderStatus'] ?? null;
        $messageSignature = $callbackData['messageSignature'] ?? null;

        if (! $orderCode) {
            return CallbackResponse::failure(
                orderCode: 'unknown',
                message: 'Merchant reference number is required'
            );
        }

        if ($messageSignature && ! $this->isValidWebhookSignature($callbackData, $messageSignature)) {
            Log::warning('Fawry Callback Signature Invalid', [
                'order_code' => $orderCode,
            ]);

            return CallbackResponse::failure(
                orderCode: (string) $orderCode,
                message: 'Invalid callback signature from Fawry'
            );
        }

        Log::info('Fawry Payment Status', [
            'order_code' => $orderCode,
            'fawry_ref' => $fawryRefNumber,
            'status' => $orderStatus,
        ]);

        if ($orderStatus === 'PAID' || $orderStatus === 'DELIVERED') {
            return CallbackResponse::success(
                orderCode: (string) $orderCode,
                transactionId: $fawryRefNumber ? (string) $fawryRefNumber : 'fawry_'.uniqid(),
                message: 'Payment completed successfully via Fawry',
                additionalData: [
                    'fawry_reference_number' => $fawryRefNumber,
                    'fawry_order_status' => $orderStatus,
                    'payment_method' => $callbackData['paymentMethod'] ?? null,
                    'payment_ref_number' => $callbackData['paymentRefNumber'] ?? null,
                ]
            );
        }

        if ($orderStatus === 'CANCELLED' || $orderStatus === 'EXPIRED') {
            return CallbackResponse::cancelled(
                orderCode: (string) $orderCode,
                message: 'Payment was '.strtolower($orderStatus).' via Fawry',
                additionalData: [
                    'fawry_reference_number' => $fawryRefNumber,
                    'fawry_order_status' => $orderStatus,
                ]
            );
        }

        return CallbackResponse::failure(
            orderCode: (string) $orderCode,
            message: __('payment_failed'),
            status: $orderStatus,
            additionalData: [
                'fawry_reference_number' => $fawryRefNumber,
                'fawry_order_status' => $orderStatus,
            ]
        );
    }

    public function refund(PaymentOrder $paymentOrder): RefundResponse
    {
        $referenceNumber = $paymentOrder->payment_data['fawry_reference_number'] ?? null;

        if (! $referenceNumber) {
            return RefundResponse::failure(
                orderCode: $paymentOrder->order_code,
                message: 'Fawry reference number not found in payment data'
            );
        }

        try {
            $merchantCode = $this->paymentMethod->getSetting('merchant_code');
            $secureKey = $this->getSecureKey();
            $baseUrl = $this->getBaseUrl();
            $refundAmount = number_format(round($paymentOrder->amount, 2), 2, '.', '');
            $reason = 'Merchant initiated refund';

            $signature = hash('sha256', $merchantCode.$referenceNumber.$refundAmount.$reason.$secureKey);

            $response = Http::post($baseUrl.'/ECommerceWeb/Fawry/payments/refund', [
                'merchantCode' => $merchantCode,
                'referenceNumber' => $referenceNumber,
                'refundAmount' => $refundAmount,
                'reason' => $reason,
                'signature' => $signature,
            ]);

            if (! $response->successful()) {
                return RefundResponse::failure(
                    orderCode: $paymentOrder->order_code,
                    message: 'Fawry refund request failed'
                );
            }

            $refundData = $response->json();

            if (($refundData['statusCode'] ?? null) === 200) {
                return RefundResponse::success(
                    orderCode: $paymentOrder->order_code,
                    refundedAmount: $paymentOrder->amount,
                    refundTransactionId: $refundData['refundId'] ?? null,
                    originalTransactionId: $referenceNumber,
                    message: 'Refund processed successfully via Fawry'
                );
            }

            return RefundResponse::failure(
                orderCode: $paymentOrder->order_code,
                message: 'Fawry refund failed: '.($refundData['statusDescription'] ?? 'Unknown error')
            );
        } catch (\Exception $e) {
            report($e);

            return RefundResponse::failure(
                orderCode: $paymentOrder->order_code,
                message: 'Error processing Fawry refund'
            );
        }
    }

    /**
     * Generate SHA-256 signature for charge request
     */
    public function generateChargeSignature(string $merchantCode, string $merchantRefNum, string $secureKey): string
    {
        return hash('sha256', $merchantCode.$merchantRefNum.$secureKey);
    }

    private function getSecureKey(): string
    {
        $testMode = $this->paymentMethod->getSetting('test_mode', true);

        return $testMode
            ? $this->paymentMethod->getSetting('secure_key_test', '')
            : $this->paymentMethod->getSetting('secure_key_live', '');
    }

    private function getBaseUrl(): string
    {
        $testMode = $this->paymentMethod->getSetting('test_mode', true);

        return $testMode ? self::STAGING_URL : self::PRODUCTION_URL;
    }

    private function isValidWebhookSignature(array $data, string $receivedSignature): bool
    {
        $secureKey = $this->getSecureKey();
        if (empty($secureKey)) {
            return false;
        }

        $fawryRefNumber = $data['fawryRefNumber'] ?? $data['FawryRefNo'] ?? '';
        $merchantRefNumber = $data['merchantRefNumber'] ?? $data['MerchantRefNo'] ?? '';
        $paymentAmount = $data['paymentAmount'] ?? $data['Amount'] ?? '';
        $orderAmount = $data['orderAmount'] ?? $data['Amount'] ?? '';
        $orderStatus = $data['orderStatus'] ?? $data['OrderStatus'] ?? '';
        $paymentMethod = $data['paymentMethod'] ?? $data['PaymentMethod'] ?? '';
        $paymentRefNumber = $data['paymentRefNumber'] ?? '';

        $expectedSignature = hash('sha256',
            $fawryRefNumber.
            $merchantRefNumber.
            $paymentAmount.
            $orderAmount.
            $orderStatus.
            $paymentMethod.
            $paymentRefNumber.
            $secureKey
        );

        return hash_equals($expectedSignature, $receivedSignature);
    }
}
