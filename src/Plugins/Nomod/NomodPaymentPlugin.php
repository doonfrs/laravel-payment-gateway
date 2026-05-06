<?php

namespace Trinavo\PaymentGateway\Plugins\Nomod;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Trinavo\PaymentGateway\Configuration\CheckboxField;
use Trinavo\PaymentGateway\Configuration\TextField;
use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\CallbackResponse;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Models\RefundResponse;

class NomodPaymentPlugin extends PaymentPluginInterface
{
    private const API_BASE = 'https://api.nomod.com';

    public static function getLogoUrl(): string
    {
        return asset('vendor/payment-gateway/imgs/nomod.svg');
    }

    public static function getMoreInfoUrl(): string
    {
        return 'https://nomod.com';
    }

    public static function getSupportedCountries(): array
    {
        return ['AE'];
    }

    public function getName(): string
    {
        return __('Nomod');
    }

    public function getDescription(): string
    {
        return __('Accept payments via Nomod hosted checkout (cards, popular in UAE).');
    }

    public function getConfigurationFields(): array
    {
        return [
            new TextField(
                name: 'api_key_test',
                label: 'API Key (Test)',
                required: true,
                encrypted: true,
                description: 'Your Nomod test API key (starts with sk_test_). Generate it from the Nomod app under Settings → Connect and Manage Integrations.'
            ),
            new TextField(
                name: 'api_key_production',
                label: 'API Key (Production)',
                required: false,
                encrypted: true,
                description: 'Your Nomod live API key (starts with sk_live_). Generate it from the Nomod app under Settings → Connect and Manage Integrations.'
            ),
            new CheckboxField(
                name: 'test_mode',
                label: 'Test Mode',
                default: true,
                description: 'Enable Nomod test mode. Uses the test API key.'
            ),
        ];
    }

    public function validateConfiguration(): bool
    {
        $testMode = $this->paymentMethod->getSetting('test_mode', true);

        if ($testMode) {
            return ! empty($this->paymentMethod->getSetting('api_key_test'));
        }

        return ! empty($this->paymentMethod->getSetting('api_key_production'));
    }

    public function processPayment(PaymentOrder $paymentOrder)
    {
        Log::info('Nomod Payment Processing Started', [
            'order_code' => $paymentOrder->order_code,
            'amount' => $paymentOrder->amount,
            'currency' => $paymentOrder->currency,
        ]);

        if (! $this->paymentMethod->relationLoaded('settings')) {
            $this->paymentMethod->load('settings');
        }

        try {
            $apiKey = $this->getApiKey();
            if (empty($apiKey)) {
                throw new \Exception('Nomod API key is not configured.');
            }

            $payload = $this->buildCheckoutPayload($paymentOrder);

            $response = Http::withHeaders(['X-API-KEY' => $apiKey])
                ->acceptJson()
                ->asJson()
                ->post(self::API_BASE.'/v1/checkout', $payload);

            Log::info('Nomod Checkout Response', [
                'order_code' => $paymentOrder->order_code,
                'status_code' => $response->status(),
            ]);

            if (! $response->successful()) {
                Log::error('Nomod API request failed', [
                    'order_code' => $paymentOrder->order_code,
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                ]);
                throw new \Exception(__('payment_gateway_error'));
            }

            $checkout = $response->json();

            if (empty($checkout['id']) || empty($checkout['url'])) {
                throw new \Exception('Nomod did not return a valid checkout session.');
            }

            $paymentOrder->update(['remote_transaction_id' => $checkout['id']]);

            return redirect()->away($checkout['url']);
        } catch (\Exception $e) {
            report($e);

            return view('payment-gateway::plugins.nomod-payment-error', [
                'paymentOrder' => $paymentOrder,
                'paymentMethod' => $this->paymentMethod,
                'failureUrl' => $this->getFailureUrl($paymentOrder),
            ]);
        }
    }

    public function handleCallback(array $callbackData): CallbackResponse
    {
        Log::info('Nomod Callback Received', [
            'raw_callback_data' => $callbackData,
        ]);

        $orderCode = $callbackData['reference_id'] ?? $callbackData['order_code'] ?? null;
        $checkoutId = $callbackData['checkout_id'] ?? $callbackData['id'] ?? null;
        $cancelledFlag = ! empty($callbackData['cancelled']);

        if (! $checkoutId && $orderCode) {
            $paymentOrder = PaymentOrder::where('order_code', $orderCode)->first();
            $checkoutId = $paymentOrder?->remote_transaction_id;
        }

        if (! $checkoutId) {
            Log::error('Nomod Callback Missing Checkout ID', [
                'callback_data' => $callbackData,
            ]);

            return CallbackResponse::failure(
                orderCode: (string) ($orderCode ?? 'unknown'),
                message: 'Nomod checkout id is required'
            );
        }

        try {
            $apiKey = $this->getApiKey();
            if (empty($apiKey)) {
                throw new \Exception('Nomod API key is not configured.');
            }

            $response = Http::withHeaders(['X-API-KEY' => $apiKey])
                ->acceptJson()
                ->get(self::API_BASE.'/v1/checkout/'.urlencode($checkoutId));

            if (! $response->successful()) {
                Log::error('Nomod checkout retrieval failed', [
                    'checkout_id' => $checkoutId,
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                ]);

                return CallbackResponse::failure(
                    orderCode: (string) ($orderCode ?? 'unknown'),
                    message: 'Unable to verify payment with Nomod'
                );
            }

            $checkout = $response->json();
            $resolvedOrderCode = (string) ($checkout['reference_id'] ?? $orderCode ?? 'unknown');
            $status = $checkout['status'] ?? null;
            $chargeId = $checkout['charges'][0]['id'] ?? null;

            if ($status === 'cancelled' || ($cancelledFlag && $status !== 'paid')) {
                return CallbackResponse::cancelled(
                    orderCode: $resolvedOrderCode,
                    message: 'Payment cancelled on Nomod checkout'
                );
            }

            if ($status === 'paid') {
                return CallbackResponse::success(
                    orderCode: $resolvedOrderCode,
                    transactionId: $chargeId ?: ('nomod_'.$checkoutId),
                    message: 'Payment completed successfully via Nomod',
                    additionalData: [
                        'nomod_checkout_id' => $checkoutId,
                        'nomod_charge_id' => $chargeId,
                        'nomod_status' => $status,
                    ]
                );
            }

            if ($status === 'created') {
                return CallbackResponse::pending(
                    orderCode: $resolvedOrderCode,
                    transactionId: $checkoutId,
                    message: 'Nomod payment is pending',
                    additionalData: [
                        'nomod_checkout_id' => $checkoutId,
                        'nomod_status' => $status,
                    ]
                );
            }

            return CallbackResponse::failure(
                orderCode: $resolvedOrderCode,
                message: __('payment_failed'),
                status: $status,
                additionalData: [
                    'nomod_checkout_id' => $checkoutId,
                    'nomod_status' => $status,
                ]
            );
        } catch (\Exception $e) {
            report($e);

            return CallbackResponse::failure(
                orderCode: (string) ($orderCode ?? 'unknown'),
                message: 'Unable to verify payment with Nomod'
            );
        }
    }

    public function refund(PaymentOrder $paymentOrder): RefundResponse
    {
        try {
            $apiKey = $this->getApiKey();
            if (empty($apiKey)) {
                throw new \Exception('Nomod API key is not configured.');
            }

            $checkoutId = $paymentOrder->remote_transaction_id;
            if (empty($checkoutId)) {
                return RefundResponse::failure(
                    orderCode: $paymentOrder->order_code,
                    message: 'Missing Nomod checkout id for this order'
                );
            }

            $checkoutResponse = Http::withHeaders(['X-API-KEY' => $apiKey])
                ->acceptJson()
                ->get(self::API_BASE.'/v1/checkout/'.urlencode($checkoutId));

            if (! $checkoutResponse->successful()) {
                return RefundResponse::failure(
                    orderCode: $paymentOrder->order_code,
                    message: 'Unable to fetch Nomod checkout for refund'
                );
            }

            $checkout = $checkoutResponse->json();
            $chargeId = $checkout['charges'][0]['id'] ?? null;

            if (empty($chargeId)) {
                return RefundResponse::failure(
                    orderCode: $paymentOrder->order_code,
                    message: 'No charge found for this Nomod checkout'
                );
            }

            $amount = number_format((float) $paymentOrder->amount, 2, '.', '');

            $refundResponse = Http::withHeaders(['X-API-KEY' => $apiKey])
                ->acceptJson()
                ->asJson()
                ->post(self::API_BASE.'/v1/charges/'.urlencode($chargeId).'/refund', [
                    'amount' => $amount,
                ]);

            if (! $refundResponse->successful()) {
                Log::error('Nomod refund failed', [
                    'order_code' => $paymentOrder->order_code,
                    'charge_id' => $chargeId,
                    'status_code' => $refundResponse->status(),
                    'response_body' => $refundResponse->body(),
                ]);

                return RefundResponse::failure(
                    orderCode: $paymentOrder->order_code,
                    message: 'Nomod rejected the refund request'
                );
            }

            return RefundResponse::success(
                orderCode: $paymentOrder->order_code,
                refundedAmount: (float) $paymentOrder->amount,
                refundTransactionId: 'nomod_refund_'.$chargeId,
                originalTransactionId: $chargeId,
                message: 'Refund completed successfully via Nomod',
            );
        } catch (\Exception $e) {
            report($e);

            return RefundResponse::failure(
                orderCode: $paymentOrder->order_code,
                message: 'Refund could not be processed'
            );
        }
    }

    private function getApiKey(): string
    {
        $testMode = $this->paymentMethod->getSetting('test_mode', true);

        return $testMode
            ? (string) $this->paymentMethod->getSetting('api_key_test', '')
            : (string) $this->paymentMethod->getSetting('api_key_production', '');
    }

    private function buildCheckoutPayload(PaymentOrder $paymentOrder): array
    {
        $callbackUrl = $this->getCallbackUrl();
        $cancelUrl = $callbackUrl.(str_contains($callbackUrl, '?') ? '&' : '?').'cancelled=1';
        $amount = number_format((float) $paymentOrder->amount, 2, '.', '');
        $currency = strtoupper((string) ($paymentOrder->currency ?? 'AED'));
        $description = $paymentOrder->description ?: ('Order '.$paymentOrder->order_code);

        $payload = [
            'reference_id' => $paymentOrder->order_code,
            'amount' => $amount,
            'currency' => $currency,
            'success_url' => $callbackUrl,
            'failure_url' => $callbackUrl,
            'cancelled_url' => $cancelUrl,
            'items' => [
                [
                    'item_id' => $paymentOrder->order_code,
                    'name' => $description,
                    'quantity' => 1,
                    'unit_amount' => $amount,
                    'discount_type' => 'flat',
                    'discount_amount' => '0.00',
                    'total_amount' => $amount,
                    'net_amount' => $amount,
                ],
            ],
            'metadata' => [
                'order_code' => $paymentOrder->order_code,
            ],
        ];

        $customer = array_filter([
            'first_name' => $paymentOrder->customer_name,
            'email' => $paymentOrder->customer_email,
            'phone_number' => $paymentOrder->customer_phone ?? null,
        ], static fn ($v) => ! empty($v));

        if (! empty($customer)) {
            $payload['customer'] = $customer;
        }

        return $payload;
    }
}
