<?php

namespace Trinavo\PaymentGateway\Plugins\Kashier;

use Illuminate\Support\Facades\Log;
use Trinavo\PaymentGateway\Configuration\CheckboxField;
use Trinavo\PaymentGateway\Configuration\SelectField;
use Trinavo\PaymentGateway\Configuration\TextField;
use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\CallbackResponse;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Models\RefundResponse;

class KashierPaymentPlugin extends PaymentPluginInterface
{
    private const BASE_URL = 'https://checkout.kashier.io';

    public static function getLogoUrl(): string
    {
        return asset('vendor/payment-gateway/imgs/kashier.png');
    }

    public static function getMoreInfoUrl(): string
    {
        return 'https://kashier.io';
    }

    public function getName(): string
    {
        return __('Kashier');
    }

    public function getDescription(): string
    {
        return __('Accept payments via Kashier (cards, wallets, bank installments).');
    }

    public function getConfigurationFields(): array
    {
        return [
            new TextField(
                name: 'merchant_id',
                label: 'Merchant ID',
                required: true,
                encrypted: false,
                description: 'Your Kashier Merchant ID (MID-xx-xx format).'
            ),
            new TextField(
                name: 'api_key_test',
                label: 'API Key (Test)',
                required: true,
                encrypted: true,
                description: 'Your Kashier API Key for test/development mode.'
            ),
            new TextField(
                name: 'api_key_live',
                label: 'API Key (Live)',
                required: false,
                encrypted: true,
                description: 'Your Kashier API Key for live/production mode.'
            ),
            new CheckboxField(
                name: 'test_mode',
                label: 'Test Mode',
                default: true,
                description: 'Enable test mode for Kashier payments.'
            ),
            new TextField(
                name: 'allowed_methods',
                label: 'Allowed Payment Methods',
                required: false,
                encrypted: false,
                description: 'Comma-separated list of allowed payment methods (e.g., card,wallet,bank_installments).'
            ),
            new SelectField(
                name: 'display_language',
                label: 'Display Language',
                required: false,
                options: [
                    'en' => 'English',
                    'ar' => 'Arabic',
                ],
                default: 'en',
                description: 'The language of the Kashier hosted payment page.'
            ),
        ];
    }

    public function validateConfiguration(): bool
    {
        $merchantId = $this->paymentMethod->getSetting('merchant_id');
        if (empty($merchantId)) {
            return false;
        }

        $testMode = $this->paymentMethod->getSetting('test_mode', true);

        if ($testMode) {
            return ! empty($this->paymentMethod->getSetting('api_key_test'));
        }

        return ! empty($this->paymentMethod->getSetting('api_key_live'));
    }

    public function processPayment(PaymentOrder $paymentOrder)
    {
        Log::info('Kashier Payment Processing Started', [
            'order_code' => $paymentOrder->order_code,
            'amount' => $paymentOrder->amount,
            'currency' => $paymentOrder->currency,
        ]);

        if (! $this->paymentMethod->relationLoaded('settings')) {
            $this->paymentMethod->load('settings');
        }

        try {
            $mid = $this->paymentMethod->getSetting('merchant_id');
            $apiKey = $this->getApiKey();
            $testMode = $this->paymentMethod->getSetting('test_mode', true);
            $allowedMethods = $this->paymentMethod->getSetting('allowed_methods', 'card,wallet,bank_installments');
            $displayLanguage = $this->paymentMethod->getSetting('display_language', 'en');

            if (empty($mid) || empty($apiKey)) {
                throw new \Exception('Kashier Merchant ID or API Key is not configured.');
            }

            $amount = round($paymentOrder->amount, 2);
            $currency = $paymentOrder->currency ?? 'EGP';
            $orderId = $paymentOrder->order_code;

            $hash = $this->generateOrderHash($mid, $orderId, $amount, $currency, $apiKey);

            $callbackUrl = $this->getCallbackUrl();

            $hppUrl = self::BASE_URL
                .'?merchantId='.urlencode($mid)
                .'&orderId='.urlencode($orderId)
                .'&amount='.urlencode($amount)
                .'&currency='.urlencode($currency)
                .'&hash='.urlencode($hash)
                .'&merchantRedirect='.urlencode($callbackUrl)
                .'&allowedMethods='.urlencode($allowedMethods)
                .'&mode='.($testMode ? 'test' : 'live')
                .'&display='.urlencode($displayLanguage);

            Log::info('Kashier HPP URL Generated', [
                'order_code' => $orderId,
                'hpp_url' => $hppUrl,
            ]);

            return redirect()->away($hppUrl);
        } catch (\Exception $e) {
            report($e);

            return view('payment-gateway::plugins.kashier-payment-error', [
                'paymentOrder' => $paymentOrder,
                'paymentMethod' => $this->paymentMethod,
                'failureUrl' => $this->getFailureUrl($paymentOrder),
                'errorMessage' => $e->getMessage(),
            ]);
        }
    }

    public function handleCallback(array $callbackData): CallbackResponse
    {
        Log::info('Kashier Callback Received', [
            'raw_callback_data' => $callbackData,
        ]);

        $paymentStatus = $callbackData['paymentStatus'] ?? null;
        $signature = $callbackData['signature'] ?? null;
        $orderCode = $callbackData['orderId'] ?? $callbackData['merchantOrderId'] ?? null;
        $transactionId = $callbackData['transactionId'] ?? null;

        if (! $orderCode) {
            Log::error('Kashier Callback Missing Order Code', [
                'callback_data' => $callbackData,
            ]);

            return CallbackResponse::failure(
                orderCode: 'unknown',
                message: 'Order code (orderId) is required'
            );
        }

        if (! $signature || ! $this->isValidCallbackSignature($callbackData, $signature)) {
            Log::warning('Kashier Callback Signature Invalid', [
                'order_code' => $orderCode,
            ]);

            return CallbackResponse::failure(
                orderCode: (string) $orderCode,
                message: 'Invalid callback signature from Kashier'
            );
        }

        if ($paymentStatus === 'SUCCESS') {
            Log::info('Kashier Payment Successful', [
                'order_code' => $orderCode,
                'transaction_id' => $transactionId,
            ]);

            return CallbackResponse::success(
                orderCode: (string) $orderCode,
                transactionId: $transactionId ? (string) $transactionId : 'kashier_'.uniqid(),
                message: 'Payment completed successfully via Kashier',
                additionalData: [
                    'kashier_transaction_id' => $transactionId,
                    'kashier_order_id' => $callbackData['kashierOrderId'] ?? null,
                    'card_data_token' => $callbackData['cardDataToken'] ?? null,
                    'masked_card' => $callbackData['maskedCard'] ?? null,
                ]
            );
        }

        Log::info('Kashier Payment Failed/Pending', [
            'order_code' => $orderCode,
            'payment_status' => $paymentStatus,
        ]);

        return CallbackResponse::failure(
            orderCode: (string) $orderCode,
            message: __('payment_failed'),
            status: $paymentStatus,
            additionalData: [
                'kashier_payment_status' => $paymentStatus,
                'kashier_transaction_id' => $transactionId,
            ]
        );
    }

    public function refund(PaymentOrder $paymentOrder): RefundResponse
    {
        return RefundResponse::failure(
            orderCode: $paymentOrder->order_code,
            message: 'Refunds are not yet implemented for Kashier API'
        );
    }

    /**
     * Generate HMAC SHA256 hash for Kashier order
     */
    public function generateOrderHash(string $mid, string $orderId, float $amount, string $currency, string $apiKey): string
    {
        $path = "/?payment={$mid}.{$orderId}.{$amount}.{$currency}";

        return hash_hmac('sha256', $path, $apiKey, false);
    }

    /**
     * Get the active API key based on test/live mode
     */
    private function getApiKey(): string
    {
        $testMode = $this->paymentMethod->getSetting('test_mode', true);

        return $testMode
            ? $this->paymentMethod->getSetting('api_key_test', '')
            : $this->paymentMethod->getSetting('api_key_live', '');
    }

    /**
     * Validate Kashier callback signature (HMAC-SHA256)
     *
     * Per Kashier docs: build query string from all params except signature and mode,
     * hash with API key, compare with received signature.
     */
    private function isValidCallbackSignature(array $callbackData, string $receivedSignature): bool
    {
        $apiKey = $this->getApiKey();

        if (empty($apiKey)) {
            return false;
        }

        $queryString = '';
        foreach ($callbackData as $key => $value) {
            if ($key === 'signature' || $key === 'mode') {
                continue;
            }
            $queryString .= '&'.$key.'='.$value;
        }
        $queryString = ltrim($queryString, '&');

        $expectedSignature = hash_hmac('sha256', $queryString, $apiKey, false);

        return hash_equals($expectedSignature, $receivedSignature);
    }
}
