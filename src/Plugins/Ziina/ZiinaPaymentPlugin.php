<?php

namespace Trinavo\PaymentGateway\Plugins\Ziina;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Trinavo\PaymentGateway\Configuration\CheckboxField;
use Trinavo\PaymentGateway\Configuration\TextField;
use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\CallbackResponse;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Models\RefundResponse;

class ZiinaPaymentPlugin extends PaymentPluginInterface
{
    private const API_BASE_URL = 'https://api-v2.ziina.com/api';

    private const SUPPORTED_CURRENCIES = ['AED', 'BHD', 'EUR', 'GBP', 'INR', 'KWD', 'OMR', 'QAR', 'SAR', 'USD'];

    private const THREE_DECIMAL_CURRENCIES = ['BHD', 'KWD', 'OMR'];

    public function getName(): string
    {
        return __('Ziina Payment Plugin');
    }

    public function getDescription(): string
    {
        return __('Integrate Ziina payment gateway for card payments in the Middle East region.');
    }

    public function getConfigurationFields(): array
    {
        return [
            new TextField(
                name: 'api_key_test',
                label: 'API Key (Test)',
                required: true,
                encrypted: true,
                description: 'Your Ziina API key for test/sandbox mode. Obtain from https://ziina.com/business/connect'
            ),

            new TextField(
                name: 'api_key_live',
                label: 'API Key (Live)',
                required: false,
                encrypted: true,
                description: 'Your Ziina API key for live/production mode. Obtain from https://ziina.com/business/connect'
            ),

            new TextField(
                name: 'webhook_secret',
                label: 'Webhook Secret',
                required: false,
                encrypted: true,
                description: 'HMAC secret for verifying webhook signatures. Configure this in your Ziina dashboard webhook settings.'
            ),

            new CheckboxField(
                name: 'test_mode',
                label: 'Test Mode',
                default: true,
                description: 'Enable test mode for Ziina payments. When enabled, uses test API key and sends test=true flag.'
            ),
        ];
    }

    public function validateConfiguration(): bool
    {
        $testMode = $this->paymentMethod->getSetting('test_mode', true);

        if ($testMode) {
            return ! empty($this->paymentMethod->getSetting('api_key_test'));
        }

        return ! empty($this->paymentMethod->getSetting('api_key_live'));
    }

    public function processPayment(PaymentOrder $paymentOrder)
    {
        Log::info('Ziina Payment Processing Started', [
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
            $paymentIntent = $this->createPaymentIntent($paymentOrder);

            if (empty($paymentIntent['redirect_url'])) {
                throw new \Exception('Ziina did not return a redirect URL');
            }

            $paymentOrder->update([
                'payment_data' => array_merge($paymentOrder->payment_data ?? [], [
                    'ziina_payment_intent_id' => $paymentIntent['id'],
                ]),
            ]);

            Log::info('Ziina Payment Intent Created', [
                'order_code' => $paymentOrder->order_code,
                'payment_intent_id' => $paymentIntent['id'],
                'redirect_url' => $paymentIntent['redirect_url'],
            ]);

            return redirect()->away($paymentIntent['redirect_url']);

        } catch (\Exception $e) {
            report($e);

            return view('payment-gateway::plugins.ziina-payment-error', [
                'paymentOrder' => $paymentOrder,
                'paymentMethod' => $this->paymentMethod,
                'failureUrl' => $this->getFailureUrl($paymentOrder),
                'errorMessage' => $e->getMessage(),
            ]);
        }
    }

    public function handleCallback(array $callbackData): CallbackResponse
    {
        Log::info('Ziina Callback Received', [
            'raw_callback_data' => $callbackData,
            'callback_keys' => array_keys($callbackData),
        ]);

        $isWebhook = isset($callbackData['event']);

        if ($isWebhook) {
            return $this->handleWebhookCallback($callbackData);
        }

        return $this->handleRedirectCallback($callbackData);
    }

    public function refund(PaymentOrder $paymentOrder): RefundResponse
    {
        Log::info('Ziina Refund Processing Started', [
            'order_code' => $paymentOrder->order_code,
            'amount' => $paymentOrder->amount,
        ]);

        $paymentData = $paymentOrder->payment_data ?? [];
        $paymentIntentId = $paymentData['ziina_payment_intent_id'] ?? null;

        if (! $paymentIntentId) {
            return RefundResponse::failure(
                orderCode: $paymentOrder->order_code,
                message: 'Cannot refund: Ziina payment intent ID not found in payment data'
            );
        }

        try {
            $apiKey = $this->getApiKey();
            $currency = strtoupper($paymentOrder->currency ?? 'AED');
            $amountInSmallestUnit = $this->convertToSmallestUnit($paymentOrder->amount, $currency);

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post(self::API_BASE_URL.'/refund', [
                'payment_intent_id' => $paymentIntentId,
                'amount' => $amountInSmallestUnit,
            ]);

            Log::info('Ziina Refund Response', [
                'order_code' => $paymentOrder->order_code,
                'status_code' => $response->status(),
                'response_body' => $response->body(),
            ]);

            if ($response->successful()) {
                $responseData = $response->json();

                return RefundResponse::success(
                    orderCode: $paymentOrder->order_code,
                    refundedAmount: $paymentOrder->amount,
                    refundTransactionId: $responseData['id'] ?? null,
                    originalTransactionId: $paymentIntentId,
                    message: 'Refund processed successfully via Ziina',
                    additionalData: [
                        'ziina_refund_response' => $responseData,
                    ]
                );
            }

            $errorBody = $response->json();
            $errorMessage = $errorBody['message'] ?? $response->body();

            Log::error('Ziina Refund Failed', [
                'order_code' => $paymentOrder->order_code,
                'status_code' => $response->status(),
                'error' => $errorMessage,
            ]);

            return RefundResponse::failure(
                orderCode: $paymentOrder->order_code,
                message: 'Ziina refund failed'
            );

        } catch (\Exception $e) {
            report($e);

            return RefundResponse::failure(
                orderCode: $paymentOrder->order_code,
                message: 'Ziina refund error'
            );
        }
    }

    private function handleRedirectCallback(array $callbackData): CallbackResponse
    {
        $orderCode = $callbackData['order_code'] ?? null;
        $paymentIntentId = $callbackData['payment_intent_id'] ?? null;
        $status = $callbackData['status'] ?? null;

        if (! $orderCode) {
            return CallbackResponse::failure(
                orderCode: 'unknown',
                message: 'Order code is required'
            );
        }

        if ($status === 'cancelled') {
            return CallbackResponse::cancelled(
                orderCode: $orderCode,
                message: 'Payment was cancelled by the customer'
            );
        }

        if ($paymentIntentId) {
            $verification = $this->verifyPaymentIntent($paymentIntentId);

            if ($verification !== null) {
                $intentStatus = $verification['status'] ?? null;

                if ($intentStatus === 'completed') {
                    return CallbackResponse::success(
                        orderCode: $orderCode,
                        transactionId: $verification['operation_id'] ?? $paymentIntentId,
                        message: 'Payment completed successfully via Ziina',
                        additionalData: [
                            'ziina_payment_intent_id' => $paymentIntentId,
                            'ziina_status' => $intentStatus,
                        ]
                    );
                }

                if (in_array($intentStatus, ['pending', 'requires_payment_instrument', 'requires_user_action'])) {
                    return CallbackResponse::pending(
                        orderCode: $orderCode,
                        transactionId: $paymentIntentId,
                        message: 'Payment is pending',
                        additionalData: [
                            'ziina_payment_intent_id' => $paymentIntentId,
                            'ziina_status' => $intentStatus,
                        ]
                    );
                }

                if (in_array($intentStatus, ['failed', 'canceled'])) {
                    return CallbackResponse::failure(
                        orderCode: $orderCode,
                        message: __('payment_failed'),
                        status: $intentStatus,
                        additionalData: [
                            'ziina_payment_intent_id' => $paymentIntentId,
                            'ziina_status' => $intentStatus,
                        ]
                    );
                }
            }
        }

        if ($status === 'success') {
            return CallbackResponse::success(
                orderCode: $orderCode,
                transactionId: $paymentIntentId ?? ('ziina_'.uniqid()),
                message: 'Payment completed via Ziina (unverified)',
                additionalData: [
                    'ziina_payment_intent_id' => $paymentIntentId,
                    'verified' => false,
                ]
            );
        }

        return CallbackResponse::failure(
            orderCode: $orderCode,
            message: __('payment_failed'),
            additionalData: [
                'ziina_payment_intent_id' => $paymentIntentId,
            ]
        );
    }

    private function handleWebhookCallback(array $callbackData): CallbackResponse
    {
        $rawBody = request()->getContent();
        $receivedSignature = request()->header('X-Hmac-Signature');

        if (! $this->verifyWebhookHmac($rawBody, $receivedSignature)) {
            Log::warning('Ziina Webhook HMAC verification failed');

            return CallbackResponse::failure(
                orderCode: 'unknown',
                message: 'Invalid webhook signature'
            );
        }

        $event = $callbackData['event'] ?? null;
        $data = $callbackData['data'] ?? [];

        $paymentIntentId = $data['id'] ?? null;
        $intentStatus = $data['status'] ?? null;

        $orderCode = $this->extractOrderCodeFromPaymentIntent($data);

        if (! $orderCode) {
            Log::warning('Ziina Webhook: Could not determine order code', [
                'payment_intent_id' => $paymentIntentId,
                'event' => $event,
            ]);

            return CallbackResponse::failure(
                orderCode: 'unknown',
                message: 'Could not determine order code from webhook'
            );
        }

        if ($event === 'payment_intent.status.updated' && $intentStatus === 'completed') {
            return CallbackResponse::success(
                orderCode: $orderCode,
                transactionId: $data['operation_id'] ?? $paymentIntentId ?? ('ziina_'.uniqid()),
                message: 'Payment completed via Ziina webhook',
                additionalData: [
                    'ziina_payment_intent_id' => $paymentIntentId,
                    'ziina_status' => $intentStatus,
                    'ziina_event' => $event,
                ]
            );
        }

        if (in_array($intentStatus, ['failed', 'canceled'])) {
            return CallbackResponse::failure(
                orderCode: $orderCode,
                message: __('payment_failed'),
                status: $intentStatus,
                additionalData: [
                    'ziina_payment_intent_id' => $paymentIntentId,
                    'ziina_status' => $intentStatus,
                    'ziina_event' => $event,
                ]
            );
        }

        return CallbackResponse::pending(
            orderCode: $orderCode,
            transactionId: $paymentIntentId,
            message: 'Payment status updated: '.$intentStatus,
            additionalData: [
                'ziina_payment_intent_id' => $paymentIntentId,
                'ziina_status' => $intentStatus,
                'ziina_event' => $event,
            ]
        );
    }

    private function createPaymentIntent(PaymentOrder $paymentOrder): array
    {
        $apiKey = $this->getApiKey();
        $testMode = $this->paymentMethod->getSetting('test_mode', true);
        $currency = strtoupper($paymentOrder->currency ?? 'AED');

        if (! in_array($currency, self::SUPPORTED_CURRENCIES)) {
            throw new \Exception(__('Currency :currency is not supported by Ziina', ['currency' => $currency]));
        }

        $amountInSmallestUnit = $this->convertToSmallestUnit($paymentOrder->amount, $currency);

        $callbackUrl = $this->getCallbackUrl();
        $successUrl = $callbackUrl.'?order_code='.urlencode($paymentOrder->order_code)
            .'&payment_intent_id={PAYMENT_INTENT_ID}&status=success';
        $cancelUrl = $callbackUrl.'?order_code='.urlencode($paymentOrder->order_code)
            .'&payment_intent_id={PAYMENT_INTENT_ID}&status=cancelled';

        $data = [
            'amount' => $amountInSmallestUnit,
            'currency_code' => $currency,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'message' => $paymentOrder->description ?: 'Payment for order '.$paymentOrder->order_code,
            'test' => (bool) $testMode,
        ];

        Log::info('Ziina Create Payment Intent Request', [
            'order_code' => $paymentOrder->order_code,
            'amount_original' => $paymentOrder->amount,
            'amount_smallest_unit' => $amountInSmallestUnit,
            'currency' => $currency,
            'test_mode' => $testMode,
        ]);

        $response = Http::timeout(30)->withHeaders([
            'Authorization' => 'Bearer '.$apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post(self::API_BASE_URL.'/payment_intent', $data);

        Log::info('Ziina Create Payment Intent Response', [
            'status_code' => $response->status(),
            'response_body' => $response->body(),
        ]);

        if (! $response->successful()) {
            Log::error('Ziina create payment intent failed', [
                'order_code' => $paymentOrder->order_code,
                'status_code' => $response->status(),
                'response_body' => $response->body(),
            ]);
            throw new \Exception(__('payment_gateway_error'));
        }

        $responseData = $response->json();

        if (! isset($responseData['redirect_url'])) {
            throw new \Exception('Ziina response missing redirect_url');
        }

        return $responseData;
    }

    private function getApiKey(): string
    {
        $testMode = $this->paymentMethod->getSetting('test_mode', true);

        $apiKey = $testMode
            ? $this->paymentMethod->getSetting('api_key_test')
            : $this->paymentMethod->getSetting('api_key_live');

        if (empty($apiKey)) {
            throw new \Exception('Ziina API key is not configured for '.($testMode ? 'test' : 'live').' mode');
        }

        return $apiKey;
    }

    private function convertToSmallestUnit(float $amount, string $currency): int
    {
        $currency = strtoupper($currency);

        if (in_array($currency, self::THREE_DECIMAL_CURRENCIES)) {
            return (int) round($amount * 1000);
        }

        return (int) round($amount * 100);
    }

    private function verifyPaymentIntent(string $paymentIntentId): ?array
    {
        try {
            $apiKey = $this->getApiKey();

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'Accept' => 'application/json',
            ])->get(self::API_BASE_URL.'/payment_intent/'.$paymentIntentId);

            if (! $response->successful()) {
                Log::warning('Ziina payment intent verification failed', [
                    'payment_intent_id' => $paymentIntentId,
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                ]);

                return null;
            }

            return $response->json();

        } catch (\Exception $e) {
            Log::error('Ziina payment intent verification exception', [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function verifyWebhookHmac(?string $rawBody, ?string $receivedSignature): bool
    {
        $webhookSecret = $this->paymentMethod->getSetting('webhook_secret');

        if (empty($webhookSecret)) {
            Log::warning('Ziina webhook secret not configured, skipping HMAC verification');

            return true;
        }

        if (! $rawBody || ! $receivedSignature) {
            return false;
        }

        $calculatedSignature = hash_hmac('sha256', $rawBody, $webhookSecret);

        return hash_equals($calculatedSignature, $receivedSignature);
    }

    private function extractOrderCodeFromPaymentIntent(array $data): ?string
    {
        $successUrl = $data['success_url'] ?? null;
        if ($successUrl) {
            $parsedUrl = parse_url($successUrl);
            $queryString = $parsedUrl['query'] ?? '';
            parse_str($queryString, $queryParams);
            if (isset($queryParams['order_code'])) {
                return $queryParams['order_code'];
            }
        }

        $paymentIntentId = $data['id'] ?? null;
        if ($paymentIntentId) {
            /** @var PaymentOrder|null $paymentOrder */
            $paymentOrder = PaymentOrder::where('payment_data->ziina_payment_intent_id', $paymentIntentId)->first();
            if ($paymentOrder) {
                return $paymentOrder->order_code;
            }
        }

        return null;
    }
}
