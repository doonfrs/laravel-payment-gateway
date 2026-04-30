<?php

namespace Trinavo\PaymentGateway\Plugins\Mamopay;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Trinavo\PaymentGateway\Configuration\CheckboxField;
use Trinavo\PaymentGateway\Configuration\TextField;
use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\CallbackResponse;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Models\RefundResponse;

class MamopayPaymentPlugin extends PaymentPluginInterface
{
    private const SANDBOX_BASE_URL = 'https://sandbox.dev.business.mamopay.com/manage_api/v1';

    private const PRODUCTION_BASE_URL = 'https://business.mamopay.com/manage_api/v1';

    private const SUPPORTED_CURRENCIES = ['AED', 'USD', 'EUR', 'GBP', 'SAR'];

    public static function getLogoUrl(): string
    {
        return asset('vendor/payment-gateway/imgs/mamopay.svg');
    }

    public static function getMoreInfoUrl(): string
    {
        return 'https://mamopay.com';
    }

    public static function getSupportedCountries(): array
    {
        return ['AE', 'SA', 'OM', 'BH', 'KW', 'QA'];
    }

    public function getName(): string
    {
        return __('Mamopay');
    }

    public function getDescription(): string
    {
        return __('Pay securely via Mamopay hosted checkout (AED, USD, EUR, GBP, SAR).');
    }

    public function getConfigurationFields(): array
    {
        return [
            new TextField(
                name: 'api_key_test',
                label: __('API Key (Test)'),
                required: true,
                encrypted: true,
                description: __('Your Mamopay sandbox API key. Request one from api@mamopay.com if you do not have a sandbox account.'),
            ),
            new TextField(
                name: 'api_key_production',
                label: __('API Key (Production)'),
                required: false,
                encrypted: true,
                description: __('Your Mamopay production API key from the Mamo Business dashboard (Developer → Keys).'),
            ),
            new CheckboxField(
                name: 'test_mode',
                label: __('Test Mode'),
                default: true,
                description: __('Use the Mamopay sandbox environment.'),
            ),
            new TextField(
                name: 'webhook_auth_header_value',
                label: __('Webhook Auth Header Value'),
                required: false,
                encrypted: true,
                description: __('Shared secret (1-50 chars) you registered as auth_header on POST /webhooks. Mamopay echoes this back on every delivery.'),
                maxLength: 50,
            ),
            new TextField(
                name: 'webhook_auth_header_name',
                label: __('Webhook Auth Header Name'),
                required: false,
                encrypted: false,
                description: __('HTTP header name Mamopay uses to deliver the auth value. Default: Authorization. Adjust if a sandbox delivery shows a different header.'),
                default: 'Authorization',
            ),
            new TextField(
                name: 'default_currency_override',
                label: __('Force Currency'),
                required: false,
                encrypted: false,
                description: __('Optional. Force all charges to this currency (AED, USD, EUR, GBP, SAR). Leave blank to use the order currency.'),
            ),
        ];
    }

    public function validateConfiguration(): bool
    {
        $testMode = (bool) $this->paymentMethod->getSetting('test_mode', true);

        return $testMode
            ? ! empty($this->paymentMethod->getSetting('api_key_test'))
            : ! empty($this->paymentMethod->getSetting('api_key_production'));
    }

    public function processPayment(PaymentOrder $paymentOrder)
    {
        if (! $this->paymentMethod->relationLoaded('settings')) {
            $this->paymentMethod->load('settings');
        }

        $body = $this->buildLinkPayload($paymentOrder);

        Log::info('Mamopay processPayment start', [
            'order_code' => $paymentOrder->order_code,
            'amount' => $paymentOrder->amount,
            'currency' => $paymentOrder->currency,
            'test_mode' => $this->isTestMode(),
            'customer_email' => $paymentOrder->customer_email,
            'request_body' => $body,
        ]);

        try {
            $response = Http::withToken($this->getApiKey())
                ->acceptJson()
                ->asJson()
                ->post($this->getBaseUrl().'/links', $body);
        } catch (\Throwable $e) {
            Log::error('Mamopay create-link HTTP exception', [
                'order_code' => $paymentOrder->order_code,
                'exception' => $e->getMessage(),
            ]);
            report($e);

            return $this->errorView($paymentOrder);
        }

        Log::info('Mamopay create-link response', [
            'order_code' => $paymentOrder->order_code,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        if (! $response->successful()) {
            Log::error('Mamopay create-link request failed', [
                'order_code' => $paymentOrder->order_code,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return $this->errorView($paymentOrder);
        }

        $data = $response->json();
        $paymentUrl = $data['payment_url'] ?? null;
        $linkId = $data['id'] ?? null;

        if (! $paymentUrl) {
            Log::error('Mamopay create-link response missing payment_url', [
                'order_code' => $paymentOrder->order_code,
                'response' => $data,
            ]);

            return $this->errorView($paymentOrder);
        }

        if ($linkId) {
            $paymentOrder->update(['external_transaction_id' => $linkId]);
        }

        Log::info('Mamopay redirecting customer to payment_url', [
            'order_code' => $paymentOrder->order_code,
            'payment_link_id' => $linkId,
            'payment_url' => $paymentUrl,
        ]);

        return redirect()->away($paymentUrl);
    }

    public function handleCallback(array $callbackData): CallbackResponse
    {
        Log::info('Mamopay callback received', [
            'callback_keys' => array_keys($callbackData),
            'callback' => $callbackData,
        ]);

        $orderCode = $callbackData['order_code']
            ?? ($callbackData['custom_data']['order_code'] ?? null);
        $transactionId = $callbackData['transactionId'] ?? $callbackData['transaction_id'] ?? null;
        $paymentLinkId = $callbackData['paymentLinkId'] ?? $callbackData['payment_link_id'] ?? null;

        if (! $orderCode && $paymentLinkId) {
            $orderCode = PaymentOrder::where('external_transaction_id', $paymentLinkId)
                ->value('order_code');
        }

        if (! $orderCode) {
            Log::warning('Mamopay callback missing order code', ['callback' => $callbackData]);

            return CallbackResponse::failure('unknown', __('Missing order reference'));
        }

        if (! $transactionId) {
            Log::warning('Mamopay callback missing transactionId', ['order_code' => $orderCode]);

            return CallbackResponse::failure($orderCode, __('Missing transaction id'));
        }

        try {
            $response = Http::withToken($this->getApiKey())
                ->acceptJson()
                ->get($this->getBaseUrl().'/charges/'.$transactionId);
        } catch (\Throwable $e) {
            Log::error('Mamopay charge verification HTTP exception', [
                'order_code' => $orderCode,
                'transaction_id' => $transactionId,
                'exception' => $e->getMessage(),
            ]);
            report($e);

            return CallbackResponse::pending($orderCode, $transactionId, __('Payment verification deferred'));
        }

        Log::info('Mamopay charge verification response', [
            'order_code' => $orderCode,
            'transaction_id' => $transactionId,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        if (! $response->successful()) {
            Log::error('Mamopay charges/{id} verification failed', [
                'order_code' => $orderCode,
                'transaction_id' => $transactionId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return CallbackResponse::pending($orderCode, $transactionId, __('Payment verification deferred'));
        }

        $charge = $response->json();
        $status = $charge['status'] ?? null;
        $audit = $this->extractAuditData($charge);

        return match ($status) {
            'captured' => CallbackResponse::success(
                orderCode: $orderCode,
                transactionId: $transactionId,
                additionalData: $audit,
            ),
            'failed' => CallbackResponse::failure(
                orderCode: $orderCode,
                message: $charge['error_message'] ?? __('Payment failed'),
                status: $charge['error_code'] ?? null,
                additionalData: $audit,
            ),
            'refunded', 'refund_initiated' => CallbackResponse::success(
                orderCode: $orderCode,
                transactionId: $transactionId,
                additionalData: array_merge($audit, ['refunded' => true]),
            ),
            'processing', 'confirmation_required' => CallbackResponse::pending(
                orderCode: $orderCode,
                transactionId: $transactionId,
                additionalData: $audit,
            ),
            default => CallbackResponse::failure(
                orderCode: $orderCode,
                message: __('Unknown payment status'),
                status: $status,
                additionalData: $audit,
            ),
        };
    }

    public function refund(PaymentOrder $paymentOrder): RefundResponse
    {
        return RefundResponse::failure(
            orderCode: $paymentOrder->order_code,
            message: 'Refunds must be issued from the Mamopay dashboard.'
        );
    }

    public function supportsInboundRequests(): bool
    {
        return true;
    }

    public function handleInboundRequest(string $action, array $data): JsonResponse
    {
        Log::info('Mamopay inbound request received', [
            'action' => $action,
            'event_type' => $data['event_type'] ?? null,
            'status' => $data['status'] ?? null,
            'charge_id' => $data['id'] ?? null,
            'external_id' => $data['external_id'] ?? null,
            'payment_link_id' => $data['payment_link_id'] ?? null,
            'header_names' => array_keys(request()->headers->all()),
            'body' => $data,
        ]);

        if ($action !== 'webhook') {
            Log::warning('Mamopay inbound request: unknown action', ['action' => $action]);

            return response()->json(['error' => 'Unknown action'], 404);
        }

        $expectedSecret = $this->paymentMethod->getSetting('webhook_auth_header_value');

        if (! empty($expectedSecret)) {
            $headerName = $this->paymentMethod->getSetting('webhook_auth_header_name', 'Authorization');
            $received = (string) (request()->header($headerName) ?? '');

            if (! hash_equals((string) $expectedSecret, $received)) {
                Log::warning('Mamopay webhook auth header mismatch', [
                    'expected_header' => $headerName,
                    'received_length' => strlen($received),
                ]);

                return response()->json(['error' => 'Unauthorized'], 401);
            }
        }

        $eventType = $data['event_type'] ?? null;
        $status = $data['status'] ?? null;
        $externalId = $data['external_id'] ?? null;
        $linkId = $data['payment_link_id'] ?? null;

        $order = null;
        if ($externalId) {
            $order = PaymentOrder::where('order_code', $externalId)->first();
        }
        if (! $order && $linkId) {
            $order = PaymentOrder::where('external_transaction_id', $linkId)->first();
        }

        if (! $order) {
            Log::info('Mamopay webhook: payment order not found', [
                'event_type' => $eventType,
                'external_id' => $externalId,
                'payment_link_id' => $linkId,
            ]);

            return response()->json(['received' => true]);
        }

        $audit = $this->extractAuditData($data);
        $statusBefore = $order->status;

        switch ($eventType) {
            case 'charge.succeeded':
                if ($order->isPending()) {
                    $order->markAsCompleted($audit);
                }
                break;
            case 'charge.failed':
                if ($order->isPending()) {
                    $order->markAsFailed($audit);
                }
                break;
            case 'charge.refunded':
                if (! $order->isRefunded()) {
                    $order->markAsRefunded(array_merge($audit, [
                        'refund_amount' => $data['refund_amount'] ?? null,
                    ]));
                }
                break;
            default:
                if ($status === 'captured' && $order->isPending()) {
                    $order->markAsCompleted($audit);
                }
                break;
        }

        Log::info('Mamopay webhook: processed', [
            'order_code' => $order->order_code,
            'event_type' => $eventType,
            'status_before' => $statusBefore,
            'status_after' => $order->fresh()->status,
            'refunded' => $order->fresh()->isRefunded(),
        ]);

        return response()->json(['received' => true]);
    }

    private function getBaseUrl(): string
    {
        return $this->isTestMode() ? self::SANDBOX_BASE_URL : self::PRODUCTION_BASE_URL;
    }

    private function getApiKey(): string
    {
        return (string) ($this->isTestMode()
            ? $this->paymentMethod->getSetting('api_key_test')
            : $this->paymentMethod->getSetting('api_key_production'));
    }

    private function isTestMode(): bool
    {
        return (bool) $this->paymentMethod->getSetting('test_mode', true);
    }

    private function buildLinkPayload(PaymentOrder $paymentOrder): array
    {
        $title = $paymentOrder->getLocalizedDescription() ?: 'Order '.$paymentOrder->order_code;
        $title = mb_substr(trim($title), 0, 50);

        $override = trim((string) $this->paymentMethod->getSetting('default_currency_override', ''));
        $currency = strtoupper($override ?: ($paymentOrder->currency ?: 'AED'));
        if (! in_array($currency, self::SUPPORTED_CURRENCIES, true)) {
            $currency = 'AED';
        }

        [$firstName, $lastName] = $this->splitName($paymentOrder->customer_name);

        $callbackUrl = $this->getCallbackUrl();

        $payload = [
            'title' => $title,
            'amount' => round((float) $paymentOrder->amount, 2),
            'amount_currency' => $currency,
            'return_url' => $callbackUrl,
            'failure_return_url' => $callbackUrl,
            'external_id' => $paymentOrder->order_code,
            'capacity' => 1,
            'custom_data' => ['order_code' => $paymentOrder->order_code],
        ];

        if ($firstName !== '') {
            $payload['first_name'] = $firstName;
        }
        if ($lastName !== '') {
            $payload['last_name'] = $lastName;
        }
        if ($paymentOrder->customer_email) {
            $payload['email'] = $paymentOrder->customer_email;
        }

        return $payload;
    }

    private function splitName(?string $fullName): array
    {
        $fullName = trim((string) $fullName);
        if ($fullName === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s+/', $fullName, 2) ?: [];

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    private function extractAuditData(array $charge): array
    {
        return array_filter([
            'mamopay_charge_id' => $charge['id'] ?? null,
            'status' => $charge['status'] ?? null,
            'amount' => $charge['amount'] ?? null,
            'amount_currency' => $charge['amount_currency'] ?? null,
            'payment_link_id' => $charge['payment_link_id'] ?? null,
            'card_type' => $charge['payment_method']['type'] ?? null,
            'card_last4' => $charge['payment_method']['card_last4'] ?? null,
            'event_type' => $charge['event_type'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    private function errorView(PaymentOrder $paymentOrder)
    {
        return view('payment-gateway::plugins.mamopay-error', [
            'paymentOrder' => $paymentOrder,
            'paymentMethod' => $this->paymentMethod,
            'failureUrl' => $this->getFailureUrl($paymentOrder),
        ]);
    }
}
