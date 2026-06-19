<?php

namespace Trinavo\PaymentGateway\Plugins\Paymera;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Trinavo\PaymentGateway\Configuration\CheckboxField;
use Trinavo\PaymentGateway\Configuration\TextField;
use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\CallbackResponse;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Models\RefundResponse;

class PaymeraPaymentPlugin extends PaymentPluginInterface
{
    private const TEST_BASE_URL = 'https://egate-t.paymera.cc';

    private const PRODUCTION_BASE_URL = 'https://egate.paymera.cc';

    public static function getLogoUrl(): string
    {
        return asset('vendor/payment-gateway/imgs/paymera.png');
    }

    public static function getMoreInfoUrl(): string
    {
        return 'https://paymera.cc';
    }

    public static function getSupportedCountries(): array
    {
        return ['SY'];
    }

    public function getName(): string
    {
        return __('Paymera');
    }

    public function getDescription(): string
    {
        return __('Accept payments via Paymera eGate (Syria) using card payments through partner banks.');
    }

    public function getConfigurationFields(): array
    {
        return [
            new TextField(
                name: 'terminal_id_test',
                label: 'Terminal ID (Test)',
                required: true,
                encrypted: true,
                description: '8-character Paymera terminal ID assigned for the test environment.'
            ),
            new TextField(
                name: 'username_test',
                label: 'Username (Test)',
                required: true,
                encrypted: true,
                description: 'Paymera eGate API username for the test environment (HTTP Basic auth).'
            ),
            new TextField(
                name: 'password_test',
                label: 'Password (Test)',
                required: true,
                encrypted: true,
                description: 'Paymera eGate API password for the test environment (HTTP Basic auth).'
            ),
            new TextField(
                name: 'terminal_id_production',
                label: 'Terminal ID (Production)',
                required: false,
                encrypted: true,
                description: '8-character Paymera terminal ID assigned for production. Production credentials are restricted to a single public IP whitelisted by your bank.'
            ),
            new TextField(
                name: 'username_production',
                label: 'Username (Production)',
                required: false,
                encrypted: true,
                description: 'Paymera eGate API username for production (HTTP Basic auth).'
            ),
            new TextField(
                name: 'password_production',
                label: 'Password (Production)',
                required: false,
                encrypted: true,
                description: 'Paymera eGate API password for production (HTTP Basic auth).'
            ),
            new CheckboxField(
                name: 'test_mode',
                label: 'Test Mode',
                default: true,
                description: 'Enable test mode to use the Paymera eGate test environment (egate-t.paymera.cc).'
            ),
        ];
    }

    public function validateConfiguration(): bool
    {
        if ($this->isTestMode()) {
            return ! empty($this->paymentMethod->getSetting('terminal_id_test'))
                && ! empty($this->paymentMethod->getSetting('username_test'))
                && ! empty($this->paymentMethod->getSetting('password_test'));
        }

        return ! empty($this->paymentMethod->getSetting('terminal_id_production'))
            && ! empty($this->paymentMethod->getSetting('username_production'))
            && ! empty($this->paymentMethod->getSetting('password_production'));
    }

    public function processPayment(PaymentOrder $paymentOrder)
    {
        Log::info('Paymera Processing Started', [
            'order_code' => $paymentOrder->order_code,
            'amount' => $paymentOrder->amount,
            'currency' => $paymentOrder->currency,
        ]);

        if (! $this->paymentMethod->relationLoaded('settings')) {
            $this->paymentMethod->load('settings');
        }

        try {
            [$username, $password] = $this->getCredentials();
            $terminalId = $this->getTerminalId();

            if (empty($username) || empty($password) || empty($terminalId)) {
                throw new \Exception('Paymera credentials are not configured.');
            }

            $callbackUrl = $this->buildCallbackUrl($paymentOrder);

            $payload = [
                'lang' => $this->resolveLang(),
                'terminalId' => $terminalId,
                // Paymera eGate (Syria) settles in SYP; convert the base-currency order total.
                'amount' => (int) round((float) $this->convertAmount($paymentOrder, 'SYP')),
                'callbackURL' => $callbackUrl,
                'triggerURL' => $callbackUrl,
                'notes' => 'Order ' . $paymentOrder->order_code,
            ];

            Log::info('Paymera Create Payment Request', [
                'order_code' => $paymentOrder->order_code,
                'terminal_id' => $terminalId,
                'amount' => $payload['amount'],
            ]);

            $response = Http::withBasicAuth($username, $password)
                ->asJson()
                ->post($this->getBaseUrl() . '/api/create-payment', $payload);

            Log::info('Paymera Create Payment Response', [
                'status_code' => $response->status(),
                'response_body' => $response->body(),
            ]);

            if (! $response->successful()) {
                throw new \Exception('Paymera create-payment HTTP failure (status ' . $response->status() . ').');
            }

            $responseData = $response->json();
            $errorCode = $responseData['ErrorCode'] ?? null;
            $data = $responseData['Data'] ?? [];
            $paymeraPaymentId = $data['paymentId'] ?? null;
            $redirectUrl = $data['url'] ?? null;

            if ($errorCode !== 0 || empty($paymeraPaymentId) || empty($redirectUrl)) {
                $errorMessage = $responseData['ErrorMessage'] ?? 'Unknown Paymera error';
                throw new \Exception('Paymera: ' . $errorMessage);
            }

            $paymentOrder->update([
                'external_transaction_id' => $paymeraPaymentId,
                'payment_data' => array_merge($paymentOrder->payment_data ?? [], [
                    'paymera_payment_id' => $paymeraPaymentId,
                ]),
            ]);

            return redirect()->away($redirectUrl);
        } catch (\Exception $e) {
            report($e);

            return view('payment-gateway::plugins.paymera-payment-error', [
                'paymentOrder' => $paymentOrder,
                'paymentMethod' => $this->paymentMethod,
                'failureUrl' => $this->getFailureUrl($paymentOrder),
                'errorMessage' => $e->getMessage(),
            ]);
        }
    }

    public function handleCallback(array $callbackData): CallbackResponse
    {
        Log::info('Paymera Callback Received', [
            'raw_callback_data' => $callbackData,
        ]);

        $orderCode = $callbackData['order_code'] ?? null;

        if (! $orderCode) {
            Log::error('Paymera Callback Missing Order Code', [
                'callback_data' => $callbackData,
            ]);

            return CallbackResponse::failure(
                orderCode: 'unknown',
                message: 'Order code is required'
            );
        }

        try {
            $paymentOrder = PaymentOrder::where('order_code', $orderCode)->first();

            if (! $paymentOrder) {
                return CallbackResponse::failure(
                    orderCode: $orderCode,
                    message: 'Payment order not found'
                );
            }

            $paymeraPaymentId = $paymentOrder->payment_data['paymera_payment_id']
                ?? $paymentOrder->external_transaction_id
                ?? null;

            if (! $paymeraPaymentId) {
                return CallbackResponse::failure(
                    orderCode: $orderCode,
                    message: 'Paymera payment ID not found for this order'
                );
            }

            [$username, $password] = $this->getCredentials();

            $response = Http::withBasicAuth($username, $password)
                ->get($this->getBaseUrl() . '/api/get-payment-status/' . $paymeraPaymentId);

            Log::info('Paymera Get Payment Status Response', [
                'order_code' => $orderCode,
                'paymera_payment_id' => $paymeraPaymentId,
                'status_code' => $response->status(),
                'response_body' => $response->body(),
            ]);

            if (! $response->successful()) {
                return CallbackResponse::failure(
                    orderCode: $orderCode,
                    message: 'Failed to verify Paymera payment status'
                );
            }

            $responseData = $response->json();
            $errorCode = $responseData['ErrorCode'] ?? null;
            $data = $responseData['Data'] ?? [];
            $status = $data['status'] ?? null;
            $rrn = $data['rrn'] ?? null;

            if ($errorCode !== 0 || ! $status) {
                return CallbackResponse::failure(
                    orderCode: $orderCode,
                    message: $responseData['ErrorMessage'] ?? 'Unable to read Paymera payment status'
                );
            }

            $additionalData = [
                'paymera_payment_id' => $paymeraPaymentId,
                'paymera_status' => $status,
                'paymera_rrn' => $rrn,
            ];

            if ($status === 'A') {
                return CallbackResponse::success(
                    orderCode: $orderCode,
                    transactionId: (string) ($rrn ?: $paymeraPaymentId),
                    message: 'Payment completed successfully via Paymera',
                    additionalData: $additionalData
                );
            }

            if ($status === 'C') {
                return CallbackResponse::cancelled(
                    orderCode: $orderCode,
                    message: __('cancel_payment'),
                    additionalData: $additionalData
                );
            }

            if ($status === 'P') {
                return CallbackResponse::pending(
                    orderCode: $orderCode,
                    transactionId: (string) ($rrn ?: $paymeraPaymentId),
                    message: __('Payment is still being processed by Paymera'),
                    additionalData: $additionalData
                );
            }

            return CallbackResponse::failure(
                orderCode: $orderCode,
                message: __('payment_failed'),
                status: $status,
                additionalData: $additionalData
            );
        } catch (\Exception $e) {
            report($e);

            return CallbackResponse::failure(
                orderCode: $orderCode,
                message: 'Error verifying Paymera payment'
            );
        }
    }

    public function refund(PaymentOrder $paymentOrder): RefundResponse
    {
        $paymeraPaymentId = $paymentOrder->payment_data['paymera_payment_id']
            ?? $paymentOrder->external_transaction_id
            ?? null;

        if (! $paymeraPaymentId) {
            return RefundResponse::failure(
                orderCode: $paymentOrder->order_code,
                message: 'Paymera payment ID not found in payment data'
            );
        }

        try {
            [$username, $password] = $this->getCredentials();

            $response = Http::withBasicAuth($username, $password)
                ->asJson()
                ->post($this->getBaseUrl() . '/api/cancel-payment', [
                    'lang' => $this->resolveLang(),
                    'payment_id' => $paymeraPaymentId,
                ]);

            Log::info('Paymera Cancel Payment Response', [
                'order_code' => $paymentOrder->order_code,
                'paymera_payment_id' => $paymeraPaymentId,
                'status_code' => $response->status(),
                'response_body' => $response->body(),
            ]);

            if (! $response->successful()) {
                return RefundResponse::failure(
                    orderCode: $paymentOrder->order_code,
                    message: 'Paymera cancel-payment request failed'
                );
            }

            $responseData = $response->json();
            $errorCode = $responseData['ErrorCode'] ?? null;

            if ($errorCode === 0) {
                return RefundResponse::success(
                    orderCode: $paymentOrder->order_code,
                    refundedAmount: (float) $paymentOrder->amount,
                    refundTransactionId: (string) $paymeraPaymentId,
                    originalTransactionId: (string) $paymeraPaymentId,
                    message: 'Refund processed successfully via Paymera'
                );
            }

            return RefundResponse::failure(
                orderCode: $paymentOrder->order_code,
                message: 'Paymera refund was declined: ' . ($responseData['ErrorMessage'] ?? 'Unknown')
            );
        } catch (\Exception $e) {
            report($e);

            return RefundResponse::failure(
                orderCode: $paymentOrder->order_code,
                message: 'Error processing Paymera refund'
            );
        }
    }

    private function isTestMode(): bool
    {
        return (bool) $this->paymentMethod->getSetting('test_mode', true);
    }

    private function getBaseUrl(): string
    {
        return $this->isTestMode() ? self::TEST_BASE_URL : self::PRODUCTION_BASE_URL;
    }

    private function getTerminalId(): ?string
    {
        return $this->isTestMode()
            ? $this->paymentMethod->getSetting('terminal_id_test')
            : $this->paymentMethod->getSetting('terminal_id_production');
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function getCredentials(): array
    {
        if ($this->isTestMode()) {
            return [
                $this->paymentMethod->getSetting('username_test'),
                $this->paymentMethod->getSetting('password_test'),
            ];
        }

        return [
            $this->paymentMethod->getSetting('username_production'),
            $this->paymentMethod->getSetting('password_production'),
        ];
    }

    private function buildCallbackUrl(PaymentOrder $paymentOrder): string
    {
        $base = $this->getCallbackUrl();
        $separator = str_contains($base, '?') ? '&' : '?';

        return $base . $separator . 'order_code=' . urlencode($paymentOrder->order_code);
    }

    private function resolveLang(): string
    {
        return app()->getLocale() === 'ar' ? 'ar' : 'en';
    }
}
