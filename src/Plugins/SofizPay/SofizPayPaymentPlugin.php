<?php

namespace Trinavo\PaymentGateway\Plugins\SofizPay;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Trinavo\PaymentGateway\Configuration\CheckboxField;
use Trinavo\PaymentGateway\Configuration\TextField;
use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\CallbackResponse;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Models\RefundResponse;

class SofizPayPaymentPlugin extends PaymentPluginInterface
{
    private const PRODUCTION_MAKE_URL = 'https://www.sofizpay.com/make-cib-transaction/';

    private const SANDBOX_MAKE_URL = 'https://sofizpay.com/sandbox/make-cib-transaction/';

    private const PRODUCTION_CHECK_URL = 'https://www.sofizpay.com/cib-transaction-check/';

    private const SANDBOX_CHECK_URL = 'https://sofizpay.com/sandbox/cib-transaction-check/';

    // SofizPay RSA public key used to verify signed return callbacks.
    // Reference: sofiz/sofizpay-sdk-php → SofizPaySDK::verifySignature()
    private const CALLBACK_PUBLIC_KEY = "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA1N+bDPxpqeB9QB0affr/\n02aeRXAAnqHuLrgiUlVNdXtF7t+2w8pnEg+m9RRlc+4YEY6UyKTUjVe6k7v2p8Jj\nUItk/fMNOEg/zY222EbqsKZ2mF4hzqgyJ3QHPXjZEEqABkbcYVv4ZyV2Wq0x0ykI\n+Hy/5YWKeah4RP2uEML1FlXGpuacnMXpW6n36dne3fUN+OzILGefeRpmpnSGO5+i\nJmpF2mRdKL3hs9WgaLSg6uQyrQuJA9xqcCpUmpNbIGYXN9QZxjdyRGnxivTE8awx\nTHV3WRcKrP2krz3ruRGF6yP6PVHEuPc0YDLsYjV5uhfs7JtIksNKhRRAQ16bAsj/\n9wIDAQAB\n-----END PUBLIC KEY-----";

    public static function getLogoUrl(): string
    {
        return asset('vendor/payment-gateway/imgs/sofizpay.png');
    }

    public static function getMoreInfoUrl(): string
    {
        return 'https://sofizpay.com';
    }

    public static function getSupportedCountries(): array
    {
        return ['DZ'];
    }

    public function getName(): string
    {
        return __('SofizPay');
    }

    public function getDescription(): string
    {
        return __('Integrate SofizPay payment gateway for Algerian CIB/Dahabia card payments in DZD.');
    }

    public function getConfigurationFields(): array
    {
        return [
            new TextField(
                name: 'account_test',
                label: 'Account Public Key (Test)',
                required: true,
                encrypted: true,
                description: 'Your SofizPay Stellar public key for the sandbox environment (starts with G). Get this from https://merchant.sofizpay.com.'
            ),
            new TextField(
                name: 'account_production',
                label: 'Account Public Key (Production)',
                required: false,
                encrypted: true,
                description: 'Your SofizPay Stellar public key for production. Required before switching off test mode.'
            ),
            new CheckboxField(
                name: 'test_mode',
                label: 'Test Mode',
                default: true,
                description: 'When enabled, payments are routed through the SofizPay sandbox.'
            ),
        ];
    }

    public function validateConfiguration(): bool
    {
        $testMode = $this->paymentMethod->getSetting('test_mode', true);

        if ($testMode) {
            return ! empty($this->paymentMethod->getSetting('account_test'));
        }

        return ! empty($this->paymentMethod->getSetting('account_production'));
    }

    public function processPayment(PaymentOrder $paymentOrder)
    {
        Log::info('SofizPay Payment Processing Started', [
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
            $account = $this->getAccount();
            if (empty($account)) {
                throw new \Exception('SofizPay configuration is missing the account public key');
            }

            // Memo is limited to 28 chars on Stellar — truncate to be safe.
            $memo = Str::limit((string) $paymentOrder->order_code, 28, '');

            $query = array_filter([
                'account' => $account,
                'amount' => number_format((float) $paymentOrder->amount, 2, '.', ''),
                'full_name' => $paymentOrder->customer_name ?: 'Customer',
                'phone' => $paymentOrder->customer_phone ?: '',
                'email' => $paymentOrder->customer_email ?: '',
                'memo' => $memo,
                'return_url' => $this->getCallbackUrl(),
                'redirect' => 'no',
            ], fn ($v) => $v !== '' && $v !== null);

            $response = Http::acceptJson()->timeout(30)->get($this->getMakeUrl(), $query);

            Log::info('SofizPay Make CIB Response', [
                'order_code' => $paymentOrder->order_code,
                'status_code' => $response->status(),
                'response_body' => $response->body(),
            ]);

            if (! $response->successful()) {
                throw new \Exception(__('payment_gateway_error'));
            }

            $data = $response->json() ?? [];
            $paymentUrl = $data['payment_url'] ?? null;
            $cibTransactionId = $data['cib_transaction_id'] ?? $data['order_number'] ?? null;

            if (empty($paymentUrl)) {
                throw new \Exception('SofizPay did not return a payment URL');
            }

            if ($cibTransactionId) {
                $paymentOrder->update(['remote_transaction_id' => $cibTransactionId]);
            }

            return redirect()->away($paymentUrl);

        } catch (\Exception $e) {
            report($e);

            return view('payment-gateway::plugins.sofizpay-payment-error', [
                'paymentOrder' => $paymentOrder,
                'paymentMethod' => $this->paymentMethod,
                'failureUrl' => $this->getFailureUrl($paymentOrder),
                'errorMessage' => $e->getMessage(),
            ]);
        }
    }

    public function handleCallback(array $callbackData): CallbackResponse
    {
        Log::info('SofizPay Callback Received', [
            'raw_callback_data' => $callbackData,
            'callback_keys' => array_keys($callbackData),
        ]);

        $orderCode = $callbackData['memo'] ?? $callbackData['order_code'] ?? null;
        $cibTransactionId = $callbackData['order_number']
            ?? $callbackData['cib_transaction_id']
            ?? $callbackData['transaction_id']
            ?? null;

        // If we have a signed message, verify it before trusting any status bit.
        if (! empty($callbackData['message']) && ! empty($callbackData['signature_url_safe'])) {
            if (! $this->verifySignature($callbackData)) {
                Log::warning('SofizPay Callback Signature Verification Failed', [
                    'order_code' => $orderCode,
                ]);

                return CallbackResponse::failure(
                    orderCode: $orderCode ?: 'unknown',
                    message: 'Invalid callback signature'
                );
            }
        }

        // Fallback: if memo is missing but we have a transaction id, try to find the order.
        if (! $orderCode && $cibTransactionId) {
            $paymentOrder = PaymentOrder::where('remote_transaction_id', $cibTransactionId)->first();
            $orderCode = $paymentOrder?->order_code;
        }

        if (! $orderCode) {
            Log::error('SofizPay Callback Missing Order Code', [
                'callback_data' => $callbackData,
            ]);

            return CallbackResponse::failure(
                orderCode: 'unknown',
                message: 'Order code is required'
            );
        }

        // Resolve the CIB transaction id if the callback didn't provide one.
        if (! $cibTransactionId) {
            $paymentOrder = PaymentOrder::where('order_code', $orderCode)->first();
            $cibTransactionId = $paymentOrder?->remote_transaction_id;
        }

        if (! $cibTransactionId) {
            return CallbackResponse::failure(
                orderCode: $orderCode,
                message: 'Unable to resolve SofizPay transaction id'
            );
        }

        $status = $this->checkStatus($cibTransactionId);

        if (! $status['success']) {
            return CallbackResponse::failure(
                orderCode: $orderCode,
                message: $status['error'] ?? 'Unable to verify payment status'
            );
        }

        $statusData = $status['data'] ?? [];
        $paid = $this->isPaidStatus($statusData);

        if ($paid) {
            return CallbackResponse::success(
                orderCode: $orderCode,
                transactionId: $cibTransactionId,
                message: 'Payment completed successfully via SofizPay',
                additionalData: [
                    'sofizpay_status' => $statusData['status'] ?? null,
                ]
            );
        }

        return CallbackResponse::failure(
            orderCode: $orderCode,
            message: __('payment_failed'),
            status: $statusData['status'] ?? null,
            additionalData: [
                'sofizpay_status' => $statusData['status'] ?? null,
            ]
        );
    }

    public function refund(PaymentOrder $paymentOrder): RefundResponse
    {
        return RefundResponse::failure(
            orderCode: $paymentOrder->order_code,
            message: 'SofizPay refunds are not supported via API; please process refunds from the SofizPay merchant dashboard.'
        );
    }

    private function getMakeUrl(): string
    {
        return $this->isTestMode() ? self::SANDBOX_MAKE_URL : self::PRODUCTION_MAKE_URL;
    }

    private function getCheckUrl(): string
    {
        return $this->isTestMode() ? self::SANDBOX_CHECK_URL : self::PRODUCTION_CHECK_URL;
    }

    private function isTestMode(): bool
    {
        return (bool) $this->paymentMethod->getSetting('test_mode', true);
    }

    private function getAccount(): string
    {
        return $this->isTestMode()
            ? (string) $this->paymentMethod->getSetting('account_test')
            : (string) $this->paymentMethod->getSetting('account_production');
    }

    private function checkStatus(string $cibTransactionId): array
    {
        try {
            $response = Http::acceptJson()->timeout(30)->get($this->getCheckUrl(), [
                'order_number' => $cibTransactionId,
            ]);

            Log::info('SofizPay Status Check Response', [
                'cib_transaction_id' => $cibTransactionId,
                'status_code' => $response->status(),
                'response_body' => $response->body(),
            ]);

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'error' => 'SofizPay status check failed: '.$response->body(),
                ];
            }

            return [
                'success' => true,
                'data' => $response->json() ?? [],
            ];
        } catch (\Exception $e) {
            report($e);

            return [
                'success' => false,
                'error' => 'Exception during SofizPay status check: '.$e->getMessage(),
            ];
        }
    }

    private function isPaidStatus(array $statusData): bool
    {
        $status = strtolower((string) ($statusData['status'] ?? ''));
        if (in_array($status, ['paid', 'completed', 'success', 'successful', 'confirmed'], true)) {
            return true;
        }

        if (! empty($statusData['paid']) || ! empty($statusData['is_paid'])) {
            return true;
        }

        return false;
    }

    private function verifySignature(array $data): bool
    {
        if (empty($data['message']) || empty($data['signature_url_safe'])) {
            return false;
        }

        $signature = str_replace(['-', '_'], ['+', '/'], $data['signature_url_safe']);
        $signature .= str_repeat('=', (4 - strlen($signature) % 4) % 4);
        $signature = base64_decode($signature);

        if ($signature === false) {
            return false;
        }

        $result = @openssl_verify($data['message'], $signature, self::CALLBACK_PUBLIC_KEY, OPENSSL_ALGO_SHA256);

        return $result === 1;
    }
}
