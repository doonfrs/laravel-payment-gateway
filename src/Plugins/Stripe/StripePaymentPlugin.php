<?php

namespace Trinavo\PaymentGateway\Plugins\Stripe;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Trinavo\PaymentGateway\Configuration\CheckboxField;
use Trinavo\PaymentGateway\Configuration\TextField;
use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\CallbackResponse;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Models\RefundResponse;

class StripePaymentPlugin extends PaymentPluginInterface
{
    private const API_BASE = 'https://api.stripe.com/v1';

    /**
     * Currencies Stripe bills as "zero-decimal" — amount is NOT multiplied by 100.
     * https://docs.stripe.com/currencies#zero-decimal
     */
    private const ZERO_DECIMAL_CURRENCIES = [
        'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG',
        'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
    ];

    public static function getLogoUrl(): string
    {
        return asset('vendor/payment-gateway/imgs/stripe.png');
    }

    public static function getMoreInfoUrl(): string
    {
        return 'https://stripe.com';
    }

    public static function getSupportedCountries(): array
    {
        return [
            'US', 'CA', 'GB', 'AU', 'NZ', 'SG', 'HK', 'JP', 'MY', 'TH', 'IN', 'AE',
            'IE', 'DE', 'FR', 'ES', 'IT', 'NL', 'BE', 'PT', 'AT', 'FI', 'SE', 'DK',
            'NO', 'CH', 'PL', 'CZ', 'GR', 'LU', 'EE', 'LV', 'LT', 'SI', 'SK', 'HU',
            'RO', 'BG', 'HR', 'CY', 'MT', 'BR', 'MX',
        ];
    }

    public function getName(): string
    {
        return __('Stripe');
    }

    public function getDescription(): string
    {
        return __('Accept global card payments via Stripe Checkout (cards, Apple Pay, Google Pay, Link).');
    }

    public function getConfigurationFields(): array
    {
        return [
            new TextField(
                name: 'secret_key_test',
                label: 'Secret Key (Test)',
                required: true,
                encrypted: true,
                description: 'Your Stripe test secret key (starts with sk_test_). Find it at https://dashboard.stripe.com/test/apikeys.'
            ),
            new TextField(
                name: 'webhook_secret_test',
                label: 'Webhook Secret (Test)',
                required: false,
                encrypted: true,
                description: 'Optional. Stripe webhook signing secret for test mode (starts with whsec_).'
            ),
            new TextField(
                name: 'secret_key_production',
                label: 'Secret Key (Production)',
                required: false,
                encrypted: true,
                description: 'Your Stripe live secret key (starts with sk_live_). Find it at https://dashboard.stripe.com/apikeys.'
            ),
            new TextField(
                name: 'webhook_secret_production',
                label: 'Webhook Secret (Production)',
                required: false,
                encrypted: true,
                description: 'Optional. Stripe webhook signing secret for production mode (starts with whsec_).'
            ),
            new CheckboxField(
                name: 'test_mode',
                label: 'Test Mode',
                default: true,
                description: 'Enable Stripe test mode. Uses the test secret key and test card numbers.'
            ),
        ];
    }

    public function validateConfiguration(): bool
    {
        $testMode = $this->paymentMethod->getSetting('test_mode', true);

        if ($testMode) {
            return ! empty($this->paymentMethod->getSetting('secret_key_test'));
        }

        return ! empty($this->paymentMethod->getSetting('secret_key_production'));
    }

    public function processPayment(PaymentOrder $paymentOrder)
    {
        Log::info('Stripe Payment Processing Started', [
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
                throw new \Exception('Stripe secret key is not configured.');
            }

            $currency = strtoupper($paymentOrder->currency ?? 'USD');
            $unitAmount = $this->toStripeAmount((float) $paymentOrder->amount, $currency);

            $params = [
                'mode' => 'payment',
                'client_reference_id' => $paymentOrder->order_code,
                'success_url' => $this->getCallbackUrl().'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $this->getCallbackUrl().'?session_id={CHECKOUT_SESSION_ID}&cancelled=1',
                'line_items[0][quantity]' => 1,
                'line_items[0][price_data][currency]' => strtolower($currency),
                'line_items[0][price_data][unit_amount]' => $unitAmount,
                'line_items[0][price_data][product_data][name]' => $paymentOrder->description ?: ('Order '.$paymentOrder->order_code),
                'metadata[order_code]' => $paymentOrder->order_code,
            ];

            if (! empty($paymentOrder->customer_email)) {
                $params['customer_email'] = $paymentOrder->customer_email;
            }

            $response = Http::withToken($secretKey)
                ->asForm()
                ->post(self::API_BASE.'/checkout/sessions', $params);

            Log::info('Stripe Checkout Session Response', [
                'order_code' => $paymentOrder->order_code,
                'status_code' => $response->status(),
            ]);

            if (! $response->successful()) {
                Log::error('Stripe API request failed', [
                    'order_code' => $paymentOrder->order_code,
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                ]);
                throw new \Exception(__('payment_gateway_error'));
            }

            $session = $response->json();

            if (empty($session['id']) || empty($session['url'])) {
                throw new \Exception('Stripe did not return a valid checkout session.');
            }

            $paymentOrder->update(['external_transaction_id' => $session['id']]);

            return redirect()->away($session['url']);
        } catch (\Exception $e) {
            report($e);

            return redirect()->away($this->getFailureUrl($paymentOrder));
        }
    }

    public function handleCallback(array $callbackData): CallbackResponse
    {
        Log::info('Stripe Callback Received', [
            'raw_callback_data' => $callbackData,
        ]);

        $sessionId = $callbackData['session_id'] ?? null;
        $cancelled = ! empty($callbackData['cancelled']);

        if (! $sessionId) {
            Log::error('Stripe Callback Missing Session ID', [
                'callback_data' => $callbackData,
            ]);

            return CallbackResponse::failure(
                orderCode: 'unknown',
                message: 'Stripe session id is required'
            );
        }

        try {
            $secretKey = $this->getSecretKey();
            if (empty($secretKey)) {
                throw new \Exception('Stripe secret key is not configured.');
            }

            $response = Http::withToken($secretKey)
                ->get(self::API_BASE.'/checkout/sessions/'.urlencode($sessionId));

            if (! $response->successful()) {
                Log::error('Stripe session retrieval failed', [
                    'session_id' => $sessionId,
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                ]);

                return CallbackResponse::failure(
                    orderCode: 'unknown',
                    message: 'Unable to verify payment with Stripe'
                );
            }

            $session = $response->json();
            $orderCode = $session['client_reference_id']
                ?? $session['metadata']['order_code']
                ?? 'unknown';
            $paymentStatus = $session['payment_status'] ?? null;
            $paymentIntentId = $session['payment_intent'] ?? null;

            if ($cancelled) {
                return CallbackResponse::cancelled(
                    orderCode: (string) $orderCode,
                    message: 'Payment cancelled on Stripe checkout'
                );
            }

            if ($paymentStatus === 'paid') {
                return CallbackResponse::success(
                    orderCode: (string) $orderCode,
                    transactionId: $paymentIntentId ? (string) $paymentIntentId : 'stripe_'.uniqid(),
                    message: 'Payment completed successfully via Stripe',
                    additionalData: [
                        'stripe_session_id' => $sessionId,
                        'stripe_payment_intent' => $paymentIntentId,
                        'stripe_payment_status' => $paymentStatus,
                    ]
                );
            }

            if ($paymentStatus === 'unpaid') {
                return CallbackResponse::pending(
                    orderCode: (string) $orderCode,
                    transactionId: $paymentIntentId,
                    message: 'Payment is pending on Stripe',
                    additionalData: [
                        'stripe_session_id' => $sessionId,
                        'stripe_payment_status' => $paymentStatus,
                    ]
                );
            }

            return CallbackResponse::failure(
                orderCode: (string) $orderCode,
                message: __('payment_failed'),
                status: $paymentStatus,
                additionalData: [
                    'stripe_session_id' => $sessionId,
                    'stripe_payment_status' => $paymentStatus,
                ]
            );
        } catch (\Exception $e) {
            report($e);

            return CallbackResponse::failure(
                orderCode: 'unknown',
                message: 'Unable to verify payment with Stripe'
            );
        }
    }

    public function refund(PaymentOrder $paymentOrder): RefundResponse
    {
        try {
            $secretKey = $this->getSecretKey();
            if (empty($secretKey)) {
                throw new \Exception('Stripe secret key is not configured.');
            }

            $paymentIntent = $this->resolvePaymentIntentId($paymentOrder);
            if (empty($paymentIntent)) {
                return RefundResponse::failure(
                    orderCode: $paymentOrder->order_code,
                    message: 'Missing Stripe payment intent for this order'
                );
            }

            $response = Http::withToken($secretKey)
                ->asForm()
                ->post(self::API_BASE.'/refunds', [
                    'payment_intent' => $paymentIntent,
                ]);

            if (! $response->successful()) {
                Log::error('Stripe refund failed', [
                    'order_code' => $paymentOrder->order_code,
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                ]);

                return RefundResponse::failure(
                    orderCode: $paymentOrder->order_code,
                    message: 'Stripe rejected the refund request'
                );
            }

            $refund = $response->json();
            $refundedAmount = isset($refund['amount'])
                ? $this->fromStripeAmount((int) $refund['amount'], strtoupper($refund['currency'] ?? $paymentOrder->currency ?? 'USD'))
                : (float) $paymentOrder->amount;

            return RefundResponse::success(
                orderCode: $paymentOrder->order_code,
                refundedAmount: $refundedAmount,
                refundTransactionId: $refund['id'] ?? null,
                originalTransactionId: $paymentIntent,
                message: 'Refund completed successfully via Stripe',
                additionalData: [
                    'stripe_refund_status' => $refund['status'] ?? null,
                ]
            );
        } catch (\Exception $e) {
            report($e);

            return RefundResponse::failure(
                orderCode: $paymentOrder->order_code,
                message: 'Refund could not be processed'
            );
        }
    }

    private function getSecretKey(): string
    {
        $testMode = $this->paymentMethod->getSetting('test_mode', true);

        return $testMode
            ? (string) $this->paymentMethod->getSetting('secret_key_test', '')
            : (string) $this->paymentMethod->getSetting('secret_key_production', '');
    }

    private function toStripeAmount(float $amount, string $currency): int
    {
        if (in_array(strtoupper($currency), self::ZERO_DECIMAL_CURRENCIES, true)) {
            return (int) round($amount);
        }

        return (int) round($amount * 100);
    }

    private function fromStripeAmount(int $amount, string $currency): float
    {
        if (in_array(strtoupper($currency), self::ZERO_DECIMAL_CURRENCIES, true)) {
            return (float) $amount;
        }

        return $amount / 100;
    }

    private function resolvePaymentIntentId(PaymentOrder $paymentOrder): ?string
    {
        $remoteId = $paymentOrder->external_transaction_id;

        if (empty($remoteId)) {
            return null;
        }

        if (str_starts_with($remoteId, 'pi_')) {
            return $remoteId;
        }

        if (str_starts_with($remoteId, 'cs_')) {
            $secretKey = $this->getSecretKey();
            if (empty($secretKey)) {
                return null;
            }

            $response = Http::withToken($secretKey)
                ->get(self::API_BASE.'/checkout/sessions/'.urlencode($remoteId));

            if (! $response->successful()) {
                return null;
            }

            $session = $response->json();

            return $session['payment_intent'] ?? null;
        }

        return null;
    }
}
