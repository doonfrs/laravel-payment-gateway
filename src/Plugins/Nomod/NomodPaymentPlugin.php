<?php

namespace Trinavo\PaymentGateway\Plugins\Nomod;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
                Log::error('Nomod API key is not configured', [
                    'order_code' => $paymentOrder->order_code,
                    'test_mode' => (bool) $this->paymentMethod->getSetting('test_mode', true),
                ]);
                throw new \Exception('Nomod API key is not configured.');
            }

            $payload = $this->buildCheckoutPayload($paymentOrder);

            Log::info('Nomod Create Checkout Request', [
                'order_code' => $paymentOrder->order_code,
                'url' => self::API_BASE.'/v1/checkout',
                'payload' => $payload,
            ]);

            $response = Http::withHeaders(['X-API-KEY' => $apiKey])
                ->acceptJson()
                ->asJson()
                ->post(self::API_BASE.'/v1/checkout', $payload);

            Log::info('Nomod Create Checkout Response', [
                'order_code' => $paymentOrder->order_code,
                'status_code' => $response->status(),
                'response_body' => $response->body(),
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
                Log::error('Nomod returned an invalid checkout session', [
                    'order_code' => $paymentOrder->order_code,
                    'response_body' => $response->body(),
                ]);
                throw new \Exception('Nomod did not return a valid checkout session.');
            }

            $paymentOrder->update(['external_transaction_id' => $checkout['id']]);

            Log::info('Nomod Checkout Created', [
                'order_code' => $paymentOrder->order_code,
                'checkout_id' => $checkout['id'],
                'status' => $checkout['status'] ?? null,
                'redirect_url' => $checkout['url'],
            ]);

            return redirect()->away($checkout['url']);
        } catch (\Exception $e) {
            Log::error('Nomod processPayment exception', [
                'order_code' => $paymentOrder->order_code,
                'message' => $e->getMessage(),
            ]);
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
            $checkoutId = $paymentOrder?->external_transaction_id;
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
                Log::error('Nomod API key is not configured', [
                    'order_code' => $orderCode,
                    'checkout_id' => $checkoutId,
                ]);
                throw new \Exception('Nomod API key is not configured.');
            }

            Log::info('Nomod Get Checkout Request', [
                'checkout_id' => $checkoutId,
                'url' => self::API_BASE.'/v1/checkout/'.$checkoutId,
            ]);

            $response = Http::withHeaders(['X-API-KEY' => $apiKey])
                ->acceptJson()
                ->get(self::API_BASE.'/v1/checkout/'.urlencode($checkoutId));

            Log::info('Nomod Get Checkout Response', [
                'checkout_id' => $checkoutId,
                'status_code' => $response->status(),
                'response_body' => $response->body(),
            ]);

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
                Log::info('Nomod Payment Cancelled', [
                    'order_code' => $resolvedOrderCode,
                    'checkout_id' => $checkoutId,
                    'nomod_status' => $status,
                    'cancelled_flag' => $cancelledFlag,
                ]);

                return CallbackResponse::cancelled(
                    orderCode: $resolvedOrderCode,
                    message: 'Payment cancelled on Nomod checkout'
                );
            }

            if ($status === 'paid') {
                Log::info('Nomod Payment Successful', [
                    'order_code' => $resolvedOrderCode,
                    'checkout_id' => $checkoutId,
                    'charge_id' => $chargeId,
                ]);

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

            // Nomod's live API returns "enabled" for an active, not-yet-paid
            // checkout (the public docs call this state "created"). Treat both
            // as pending so the customer can still complete the same checkout.
            if ($status === 'created' || $status === 'enabled') {
                Log::info('Nomod Payment Pending', [
                    'order_code' => $resolvedOrderCode,
                    'checkout_id' => $checkoutId,
                    'nomod_status' => $status,
                ]);

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

            Log::warning('Nomod Payment Failed With Unhandled Status', [
                'order_code' => $resolvedOrderCode,
                'checkout_id' => $checkoutId,
                'nomod_status' => $status,
            ]);

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
            Log::error('Nomod handleCallback exception', [
                'order_code' => $orderCode,
                'checkout_id' => $checkoutId,
                'message' => $e->getMessage(),
            ]);
            report($e);

            return CallbackResponse::failure(
                orderCode: (string) ($orderCode ?? 'unknown'),
                message: 'Unable to verify payment with Nomod'
            );
        }
    }

    public function refund(PaymentOrder $paymentOrder): RefundResponse
    {
        Log::info('Nomod Refund Started', [
            'order_code' => $paymentOrder->order_code,
            'amount' => $paymentOrder->amount,
        ]);

        try {
            $apiKey = $this->getApiKey();
            if (empty($apiKey)) {
                Log::error('Nomod API key is not configured', [
                    'order_code' => $paymentOrder->order_code,
                ]);
                throw new \Exception('Nomod API key is not configured.');
            }

            $checkoutId = $paymentOrder->external_transaction_id;
            if (empty($checkoutId)) {
                Log::warning('Nomod refund aborted: missing checkout id', [
                    'order_code' => $paymentOrder->order_code,
                ]);

                return RefundResponse::failure(
                    orderCode: $paymentOrder->order_code,
                    message: 'Missing Nomod checkout id for this order'
                );
            }

            $checkoutResponse = Http::withHeaders(['X-API-KEY' => $apiKey])
                ->acceptJson()
                ->get(self::API_BASE.'/v1/checkout/'.urlencode($checkoutId));

            Log::info('Nomod Get Checkout For Refund Response', [
                'order_code' => $paymentOrder->order_code,
                'checkout_id' => $checkoutId,
                'status_code' => $checkoutResponse->status(),
                'response_body' => $checkoutResponse->body(),
            ]);

            if (! $checkoutResponse->successful()) {
                Log::error('Nomod refund aborted: cannot fetch checkout', [
                    'order_code' => $paymentOrder->order_code,
                    'checkout_id' => $checkoutId,
                    'status_code' => $checkoutResponse->status(),
                    'response_body' => $checkoutResponse->body(),
                ]);

                return RefundResponse::failure(
                    orderCode: $paymentOrder->order_code,
                    message: 'Unable to fetch Nomod checkout for refund'
                );
            }

            $checkout = $checkoutResponse->json();
            $chargeId = $checkout['charges'][0]['id'] ?? null;

            if (empty($chargeId)) {
                Log::warning('Nomod refund aborted: no charge found', [
                    'order_code' => $paymentOrder->order_code,
                    'checkout_id' => $checkoutId,
                    'checkout_status' => $checkout['status'] ?? null,
                ]);

                return RefundResponse::failure(
                    orderCode: $paymentOrder->order_code,
                    message: 'No charge found for this Nomod checkout'
                );
            }

            $amount = number_format((float) $paymentOrder->amount, 2, '.', '');

            Log::info('Nomod Refund Request', [
                'order_code' => $paymentOrder->order_code,
                'charge_id' => $chargeId,
                'amount' => $amount,
                'url' => self::API_BASE.'/v1/charges/'.$chargeId.'/refund',
            ]);

            $refundResponse = Http::withHeaders(['X-API-KEY' => $apiKey])
                ->acceptJson()
                ->asJson()
                ->post(self::API_BASE.'/v1/charges/'.urlencode($chargeId).'/refund', [
                    'amount' => $amount,
                ]);

            Log::info('Nomod Refund Response', [
                'order_code' => $paymentOrder->order_code,
                'charge_id' => $chargeId,
                'status_code' => $refundResponse->status(),
                'response_body' => $refundResponse->body(),
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

            Log::info('Nomod Refund Successful', [
                'order_code' => $paymentOrder->order_code,
                'charge_id' => $chargeId,
                'refunded_amount' => $amount,
            ]);

            return RefundResponse::success(
                orderCode: $paymentOrder->order_code,
                refundedAmount: (float) $paymentOrder->amount,
                refundTransactionId: 'nomod_refund_'.$chargeId,
                originalTransactionId: $chargeId,
                message: 'Refund completed successfully via Nomod',
            );
        } catch (\Exception $e) {
            Log::error('Nomod refund exception', [
                'order_code' => $paymentOrder->order_code,
                'message' => $e->getMessage(),
            ]);
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
        $separator = Str::contains($callbackUrl, '?') ? '&' : '?';
        $callbackUrlWithRef = $callbackUrl.$separator.'reference_id='.urlencode($paymentOrder->order_code);
        $cancelUrl = $callbackUrlWithRef.'&cancelled=1';
        $amount = number_format((float) $paymentOrder->amount, 2, '.', '');
        $currency = strtoupper((string) ($paymentOrder->currency ?? 'AED'));
        $description = $paymentOrder->description ?: ('Order '.$paymentOrder->order_code);

        $payload = [
            'reference_id' => $paymentOrder->order_code,
            'amount' => $amount,
            'currency' => $currency,
            'success_url' => $callbackUrlWithRef,
            'failure_url' => $callbackUrlWithRef,
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
            ...$this->splitCustomerName($paymentOrder->customer_name),
            'email' => $paymentOrder->customer_email,
            'phone_number' => $paymentOrder->customer_phone ?? null,
        ], static fn ($v) => ! empty($v));

        if (! empty($customer)) {
            $payload['customer'] = $customer;
        }

        return $payload;
    }

    /**
     * Nomod requires both first_name and last_name. If the merchant only stored a
     * single name (e.g. "Admin"), reuse it as last_name so the API doesn't reject
     * the customer with `customer_invalid_last_name`.
     */
    private function splitCustomerName(?string $fullName): array
    {
        $fullName = trim((string) $fullName);

        if ($fullName === '') {
            return [];
        }

        $parts = preg_split('/\s+/', $fullName, 2) ?: [];

        $first = $parts[0] ?? '';
        $last = $parts[1] ?? $first;

        return [
            'first_name' => $first,
            'last_name' => $last,
        ];
    }
}
