<?php

namespace Trinavo\PaymentGateway\Plugins\PayTR;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Trinavo\PaymentGateway\Configuration\CheckboxField;
use Trinavo\PaymentGateway\Configuration\TextField;
use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\CallbackResponse;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Models\RefundResponse;

class PayTRPaymentPlugin extends PaymentPluginInterface
{
    private const GET_TOKEN_URL = 'https://www.paytr.com/odeme/api/get-token';

    private const IFRAME_URL = 'https://www.paytr.com/odeme/guvenli/';

    public static function getLogoUrl(): string
    {
        return asset('vendor/payment-gateway/imgs/paytr.svg');
    }

    public static function getMoreInfoUrl(): string
    {
        return 'https://www.paytr.com';
    }

    public static function getSupportedCountries(): array
    {
        return ['TR'];
    }

    public function getName(): string
    {
        return __('PayTR');
    }

    public function getDescription(): string
    {
        return __('Integrate PayTR payment gateway using their iFrame API.');
    }

    public function getConfigurationFields(): array
    {
        return [
            new TextField(
                name: 'merchant_id',
                label: 'Merchant ID',
                required: true,
                encrypted: true,
                description: 'Your PayTR Merchant ID. Get this from your PayTR merchant panel.'
            ),
            new TextField(
                name: 'merchant_key',
                label: 'Merchant Key',
                required: true,
                encrypted: true,
                description: 'Your PayTR Merchant Key. Get this from your PayTR merchant panel.'
            ),
            new TextField(
                name: 'merchant_salt',
                label: 'Merchant Salt',
                required: true,
                encrypted: true,
                description: 'Your PayTR Merchant Salt. Get this from your PayTR merchant panel.'
            ),
            new TextField(
                name: 'currency',
                label: 'Currency',
                required: false,
                encrypted: false,
                description: 'Payment currency. One of: TL, USD, EUR, GBP, RUB.',
                default: 'TL'
            ),
            new TextField(
                name: 'installment_max',
                label: 'Max Installments',
                required: false,
                encrypted: false,
                description: '0 = allow all installments; 1 = disable installments; 2–12 = max number of installments.',
                default: '0'
            ),
            new TextField(
                name: 'timeout_limit',
                label: 'Timeout (minutes)',
                required: false,
                encrypted: false,
                description: 'Payment session timeout in minutes (PayTR default: 30).',
                default: '30'
            ),
            new CheckboxField(
                name: 'test_mode',
                label: 'Test Mode',
                default: true,
                description: 'Enable sandbox/test mode (sends test_mode=1 to PayTR).'
            ),
        ];
    }

    public function validateConfiguration(): bool
    {
        return ! empty($this->paymentMethod->getSetting('merchant_id'))
            && ! empty($this->paymentMethod->getSetting('merchant_key'))
            && ! empty($this->paymentMethod->getSetting('merchant_salt'));
    }

    public function processPayment(PaymentOrder $paymentOrder)
    {
        Log::info('PayTR Payment Processing Started', [
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
            $token = $this->requestToken($paymentOrder);

            $paymentOrder->update(['external_transaction_id' => $token]);

            return view('payment-gateway::plugins.paytr-payment', [
                'paymentOrder' => $paymentOrder,
                'paymentMethod' => $this->paymentMethod,
                'paytrToken' => $token,
                'iframeUrl' => self::IFRAME_URL.$token,
                'callbackUrl' => $this->getCallbackUrl(),
                'successUrl' => $this->getSuccessUrl($paymentOrder),
                'failureUrl' => $this->getFailureUrl($paymentOrder),
            ]);
        } catch (\Exception $e) {
            report($e);

            return view('payment-gateway::plugins.paytr-payment-error', [
                'paymentOrder' => $paymentOrder,
                'paymentMethod' => $this->paymentMethod,
                'failureUrl' => $this->getFailureUrl($paymentOrder),
            ]);
        }
    }

    public function handleCallback(array $callbackData): CallbackResponse
    {
        Log::info('PayTR Callback Received', [
            'callback_keys' => array_keys($callbackData),
            'merchant_oid' => $callbackData['merchant_oid'] ?? null,
            'status' => $callbackData['status'] ?? null,
        ]);

        $merchantOid = $callbackData['merchant_oid'] ?? null;
        $status = $callbackData['status'] ?? null;
        $totalAmount = $callbackData['total_amount'] ?? null;
        $receivedHash = $callbackData['hash'] ?? null;

        if (! $merchantOid) {
            Log::error('PayTR Callback Missing merchant_oid', ['callback_data' => $callbackData]);

            return CallbackResponse::failure(
                orderCode: 'unknown',
                message: 'Order code is required'
            );
        }

        if (! $status || $totalAmount === null || ! $receivedHash) {
            Log::warning('PayTR Callback Missing verification payload — likely a browser redirect, not a bildirim notification', [
                'merchant_oid' => $merchantOid,
                'has_status' => (bool) $status,
                'has_total_amount' => $totalAmount !== null,
                'has_hash' => (bool) $receivedHash,
            ]);

            return CallbackResponse::pending(
                orderCode: $merchantOid,
                message: 'Awaiting PayTR notification to confirm payment status'
            );
        }

        $merchantKey = $this->paymentMethod->getSetting('merchant_key');
        $merchantSalt = $this->paymentMethod->getSetting('merchant_salt');

        // PayTR callback hash concatenation order: merchant_oid + merchant_salt + status + total_amount
        $hashString = $merchantOid.$merchantSalt.$status.$totalAmount;
        $expectedHash = base64_encode(hash_hmac('sha256', $hashString, $merchantKey, true));

        if (! hash_equals($expectedHash, $receivedHash)) {
            Log::error('PayTR Callback Hash Mismatch', [
                'merchant_oid' => $merchantOid,
                'expected_hash_prefix' => substr($expectedHash, 0, 8).'...',
                'received_hash_prefix' => substr($receivedHash, 0, 8).'...',
            ]);

            return CallbackResponse::failure(
                orderCode: $merchantOid,
                message: 'Callback hash verification failed'
            );
        }

        if ($status === 'success') {
            return CallbackResponse::success(
                orderCode: $merchantOid,
                transactionId: $callbackData['payment_id'] ?? 'paytr_'.uniqid(),
                message: 'Payment completed successfully via PayTR',
                additionalData: [
                    'paytr_total_amount' => $totalAmount,
                    'paytr_payment_type' => $callbackData['payment_type'] ?? null,
                    'paytr_currency' => $callbackData['currency'] ?? null,
                ]
            );
        }

        Log::warning('PayTR Payment Failed', [
            'merchant_oid' => $merchantOid,
            'failed_reason_code' => $callbackData['failed_reason_code'] ?? null,
            'failed_reason_msg' => $callbackData['failed_reason_msg'] ?? null,
        ]);

        return CallbackResponse::failure(
            orderCode: $merchantOid,
            message: __('payment_failed'),
            status: $status,
            additionalData: [
                'paytr_failed_reason_code' => $callbackData['failed_reason_code'] ?? null,
                'paytr_failed_reason_msg' => $callbackData['failed_reason_msg'] ?? null,
            ]
        );
    }

    public function refund(PaymentOrder $paymentOrder): RefundResponse
    {
        return RefundResponse::failure(
            orderCode: $paymentOrder->order_code,
            message: 'Refunds for PayTR are processed from the PayTR merchant panel.'
        );
    }

    /**
     * Build the get-token request, post it, and return the PayTR token.
     */
    private function requestToken(PaymentOrder $paymentOrder): string
    {
        $merchantId = $this->paymentMethod->getSetting('merchant_id');
        $merchantKey = $this->paymentMethod->getSetting('merchant_key');
        $merchantSalt = $this->paymentMethod->getSetting('merchant_salt');

        if (empty($merchantId) || empty($merchantKey) || empty($merchantSalt)) {
            throw new \Exception('PayTR configuration is missing merchant_id, merchant_key, or merchant_salt');
        }

        $merchantOid = $this->normalizeOrderCode($paymentOrder->order_code);
        $email = $paymentOrder->customer_email ?: 'noreply@example.com';
        // PayTR expects the amount in the smallest unit (e.g. kuruş for TL).
        $paymentAmount = (int) round(((float) $paymentOrder->amount) * 100);
        $currency = $this->paymentMethod->getSetting('currency', 'TL');
        $maxInstallment = (int) $this->paymentMethod->getSetting('installment_max', 0);
        $noInstallment = $maxInstallment === 1 ? 1 : 0;
        $timeoutLimit = (int) $this->paymentMethod->getSetting('timeout_limit', 30);
        $testMode = $this->paymentMethod->getSetting('test_mode', true) ? 1 : 0;

        $itemName = $paymentOrder->getLocalizedDescription() ?: $merchantOid;
        $userBasket = base64_encode(json_encode([
            [mb_substr($itemName, 0, 150), number_format((float) $paymentOrder->amount, 2, '.', ''), 1],
        ], JSON_UNESCAPED_UNICODE));

        $userIp = request()->ip() ?? '0.0.0.0';

        // PayTR hash concatenation order — MUST match exactly:
        // merchant_id + user_ip + merchant_oid + email + payment_amount + user_basket
        //   + no_installment + max_installment + currency + test_mode
        $hashString = $merchantId.$userIp.$merchantOid.$email.$paymentAmount.$userBasket
            .$noInstallment.($maxInstallment === 1 ? 0 : $maxInstallment).$currency.$testMode;
        $paytrToken = base64_encode(hash_hmac('sha256', $hashString.$merchantSalt, $merchantKey, true));

        $payload = [
            'merchant_id' => $merchantId,
            'user_ip' => $userIp,
            'merchant_oid' => $merchantOid,
            'email' => $email,
            'payment_amount' => $paymentAmount,
            'paytr_token' => $paytrToken,
            'user_basket' => $userBasket,
            'debug_on' => $testMode,
            'no_installment' => $noInstallment,
            'max_installment' => $maxInstallment === 1 ? 0 : $maxInstallment,
            'user_name' => $paymentOrder->customer_name ?: 'NA',
            'user_address' => 'NA',
            'user_phone' => $paymentOrder->customer_phone ?? 'NA',
            'merchant_ok_url' => $this->getCallbackUrl(),
            'merchant_fail_url' => $this->getCallbackUrl(),
            'timeout_limit' => $timeoutLimit,
            'currency' => $currency,
            'test_mode' => $testMode,
        ];

        Log::info('PayTR Get-Token Request', [
            'url' => self::GET_TOKEN_URL,
            'merchant_oid' => $merchantOid,
            'payment_amount' => $paymentAmount,
            'currency' => $currency,
            'test_mode' => $testMode,
        ]);

        $response = Http::asForm()->post(self::GET_TOKEN_URL, $payload);

        Log::info('PayTR Get-Token Response', [
            'status_code' => $response->status(),
            'response_body' => $response->body(),
        ]);

        if (! $response->successful()) {
            throw new \Exception(__('payment_gateway_error'));
        }

        $data = $response->json();

        if (! is_array($data) || ($data['status'] ?? null) !== 'success' || empty($data['token'])) {
            $reason = $data['reason'] ?? ($data['reason_msg'] ?? 'PayTR get-token request failed');
            Log::error('PayTR Get-Token Failed', [
                'merchant_oid' => $merchantOid,
                'reason' => $reason,
                'response_data' => $data,
            ]);
            throw new \Exception(__('payment_gateway_error'));
        }

        return $data['token'];
    }

    /**
     * PayTR requires merchant_oid to be alphanumeric only — strip anything else.
     */
    private function normalizeOrderCode(string $orderCode): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9]/', '', $orderCode) ?? '';

        return $sanitized !== '' ? $sanitized : 'ORDER'.time();
    }
}
