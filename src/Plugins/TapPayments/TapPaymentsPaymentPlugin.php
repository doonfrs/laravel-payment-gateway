<?php

namespace Trinavo\PaymentGateway\Plugins\TapPayments;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Trinavo\PaymentGateway\Configuration\CheckboxField;
use Trinavo\PaymentGateway\Configuration\TextField;
use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\CallbackResponse;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Models\RefundResponse;

class TapPaymentsPaymentPlugin extends PaymentPluginInterface
{
    private const API_BASE_URL = 'https://api.tap.company/v2';

    public static function getLogoUrl(): string
    {
        return asset('vendor/payment-gateway/imgs/tap-payments.png');
    }

    public static function getMoreInfoUrl(): string
    {
        return 'https://tap.company';
    }

    public static function getSupportedCountries(): array
    {
        return ['SA', 'AE', 'KW', 'BH', 'QA', 'OM', 'EG', 'JO'];
    }

    public function getName(): string
    {
        return __('Tap Payments');
    }

    public function getDescription(): string
    {
        return __('Accept payments via Tap Payments (cards, KNET, mada, Benefit, Apple Pay, and more across GCC).');
    }

    public function getConfigurationFields(): array
    {
        return [
            new TextField(
                name: 'secret_key_test',
                label: 'Secret Key (Test)',
                required: true,
                encrypted: true,
                description: 'Your Tap Payments test secret key (sk_test_...).'
            ),
            new TextField(
                name: 'secret_key_live',
                label: 'Secret Key (Live)',
                required: false,
                encrypted: true,
                description: 'Your Tap Payments live secret key (sk_live_...).'
            ),
            new CheckboxField(
                name: 'test_mode',
                label: 'Test Mode',
                default: true,
                description: 'Enable test mode for Tap Payments.'
            ),
        ];
    }

    public function validateConfiguration(): bool
    {
        $testMode = $this->paymentMethod->getSetting('test_mode', true);

        if ($testMode) {
            return ! empty($this->paymentMethod->getSetting('secret_key_test'));
        }

        return ! empty($this->paymentMethod->getSetting('secret_key_live'));
    }

    public function processPayment(PaymentOrder $paymentOrder)
    {
        Log::info('Tap Payments Processing Started', [
            'order_code' => $paymentOrder->order_code,
            'amount' => $paymentOrder->amount,
            'currency' => $paymentOrder->currency,
        ]);

        if (! $this->paymentMethod->relationLoaded('settings')) {
            $this->paymentMethod->load('settings');
        }

        try {
            $secretKey = $this->getSecretKey();

            if (empty($secretKey)) {
                throw new \Exception('Tap Payments secret key is not configured.');
            }

            $callbackUrl = $this->getCallbackUrl();

            $chargeData = [
                'amount' => round($paymentOrder->amount, 2),
                'currency' => $paymentOrder->currency ?? 'USD',
                'customer' => [
                    'first_name' => $paymentOrder->customer_name ?: 'Customer',
                    'email' => $paymentOrder->customer_email ?: 'customer@example.com',
                    'phone' => [
                        'number' => $paymentOrder->customer_phone ?: '',
                    ],
                ],
                'source' => [
                    'id' => 'src_all',
                ],
                'redirect' => [
                    'url' => $callbackUrl,
                ],
                'post' => [
                    'url' => $callbackUrl,
                ],
                'reference' => [
                    'transaction' => $paymentOrder->order_code,
                    'order' => $paymentOrder->order_code,
                ],
                'description' => $paymentOrder->description ?: 'Payment for order '.$paymentOrder->order_code,
            ];

            Log::info('Tap Payments Create Charge Request', [
                'order_code' => $paymentOrder->order_code,
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$secretKey,
                'Content-Type' => 'application/json',
            ])->post(self::API_BASE_URL.'/charges/', $chargeData);

            Log::info('Tap Payments Create Charge Response', [
                'status_code' => $response->status(),
                'response_body' => $response->body(),
            ]);

            if (! $response->successful()) {
                $errorData = $response->json();
                $errorMessage = $errorData['errors'][0]['description'] ?? $response->body();
                throw new \Exception('Tap Payments: '.$errorMessage);
            }

            $responseData = $response->json();
            $status = $responseData['status'] ?? null;
            $redirectUrl = $responseData['transaction']['url'] ?? null;
            $chargeId = $responseData['id'] ?? null;

            $paymentOrder->update([
                'payment_data' => array_merge($paymentOrder->payment_data ?? [], [
                    'tap_charge_id' => $chargeId,
                ]),
            ]);

            if ($status === 'INITIATED' && $redirectUrl) {
                return redirect()->away($redirectUrl);
            }

            throw new \Exception('Tap Payments did not return a redirect URL. Status: '.$status);
        } catch (\Exception $e) {
            report($e);

            return view('payment-gateway::plugins.tap-payments-payment-error', [
                'paymentOrder' => $paymentOrder,
                'paymentMethod' => $this->paymentMethod,
                'failureUrl' => $this->getFailureUrl($paymentOrder),
                'errorMessage' => $e->getMessage(),
            ]);
        }
    }

    public function handleCallback(array $callbackData): CallbackResponse
    {
        Log::info('Tap Payments Callback Received', [
            'raw_callback_data' => $callbackData,
        ]);

        $tapId = $callbackData['tap_id'] ?? $callbackData['id'] ?? null;
        $orderCode = $callbackData['reference']['transaction'] ?? $callbackData['order_code'] ?? null;

        if (! $tapId) {
            return CallbackResponse::failure(
                orderCode: $orderCode ?? 'unknown',
                message: 'Tap charge ID (tap_id) is required'
            );
        }

        try {
            $secretKey = $this->getSecretKey();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$secretKey,
            ])->get(self::API_BASE_URL.'/charges/'.$tapId);

            if (! $response->successful()) {
                return CallbackResponse::failure(
                    orderCode: $orderCode ?? 'unknown',
                    message: 'Failed to verify Tap Payments charge'
                );
            }

            $charge = $response->json();
            $chargeStatus = $charge['status'] ?? null;
            $chargeOrderCode = $charge['reference']['transaction'] ?? $orderCode ?? 'unknown';
            $transactionId = $charge['id'] ?? $tapId;

            Log::info('Tap Payments Charge Verification', [
                'order_code' => $chargeOrderCode,
                'status' => $chargeStatus,
                'charge_id' => $transactionId,
            ]);

            if ($chargeStatus === 'CAPTURED') {
                return CallbackResponse::success(
                    orderCode: (string) $chargeOrderCode,
                    transactionId: (string) $transactionId,
                    message: 'Payment completed successfully via Tap Payments',
                    additionalData: [
                        'tap_charge_id' => $transactionId,
                        'tap_receipt_id' => $charge['receipt']['id'] ?? null,
                        'card_brand' => $charge['card']['brand'] ?? null,
                        'card_last_four' => $charge['card']['last_four'] ?? null,
                    ]
                );
            }

            $statusMessage = $chargeStatus === 'ABANDONED' ? 'cancelled' : 'failed';

            return CallbackResponse::failure(
                orderCode: (string) $chargeOrderCode,
                message: __('payment_failed'),
                status: $statusMessage,
                additionalData: [
                    'tap_charge_id' => $transactionId,
                    'tap_status' => $chargeStatus,
                ]
            );
        } catch (\Exception $e) {
            report($e);

            return CallbackResponse::failure(
                orderCode: $orderCode ?? 'unknown',
                message: 'Error verifying Tap Payments charge'
            );
        }
    }

    public function refund(PaymentOrder $paymentOrder): RefundResponse
    {
        $chargeId = $paymentOrder->payment_data['tap_charge_id'] ?? null;

        if (! $chargeId) {
            return RefundResponse::failure(
                orderCode: $paymentOrder->order_code,
                message: 'Tap charge ID not found in payment data'
            );
        }

        try {
            $secretKey = $this->getSecretKey();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$secretKey,
                'Content-Type' => 'application/json',
            ])->post(self::API_BASE_URL.'/refunds', [
                'charge_id' => $chargeId,
                'amount' => round($paymentOrder->amount, 2),
                'currency' => $paymentOrder->currency,
                'reason' => 'Merchant initiated refund',
            ]);

            if (! $response->successful()) {
                return RefundResponse::failure(
                    orderCode: $paymentOrder->order_code,
                    message: 'Tap Payments refund request failed'
                );
            }

            $refundData = $response->json();
            $refundStatus = $refundData['status'] ?? null;

            if (in_array($refundStatus, ['REFUNDED', 'PENDING'])) {
                return RefundResponse::success(
                    orderCode: $paymentOrder->order_code,
                    refundedAmount: $paymentOrder->amount,
                    refundTransactionId: $refundData['id'] ?? null,
                    originalTransactionId: $chargeId,
                    message: 'Refund processed successfully via Tap Payments'
                );
            }

            return RefundResponse::failure(
                orderCode: $paymentOrder->order_code,
                message: 'Tap Payments refund was declined. Status: '.$refundStatus
            );
        } catch (\Exception $e) {
            report($e);

            return RefundResponse::failure(
                orderCode: $paymentOrder->order_code,
                message: 'Error processing Tap Payments refund'
            );
        }
    }

    private function getSecretKey(): string
    {
        $testMode = $this->paymentMethod->getSetting('test_mode', true);

        return $testMode
            ? $this->paymentMethod->getSetting('secret_key_test', '')
            : $this->paymentMethod->getSetting('secret_key_live', '');
    }
}
