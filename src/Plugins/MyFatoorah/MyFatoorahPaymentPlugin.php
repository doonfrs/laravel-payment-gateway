<?php

namespace Trinavo\PaymentGateway\Plugins\MyFatoorah;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Trinavo\PaymentGateway\Configuration\CheckboxField;
use Trinavo\PaymentGateway\Configuration\SelectField;
use Trinavo\PaymentGateway\Configuration\TextField;
use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\CallbackResponse;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Models\RefundResponse;

class MyFatoorahPaymentPlugin extends PaymentPluginInterface
{
    private const BASE_URLS = [
        'test' => 'https://apitest.myfatoorah.com',
        'KWT' => 'https://api.myfatoorah.com',
        'BHR' => 'https://api.myfatoorah.com',
        'JOD' => 'https://api.myfatoorah.com',
        'OMN' => 'https://api.myfatoorah.com',
        'ARE' => 'https://api-ae.myfatoorah.com',
        'SAU' => 'https://api-sa.myfatoorah.com',
        'QAT' => 'https://api-qa.myfatoorah.com',
        'EGY' => 'https://api-eg.myfatoorah.com',
    ];

    public static function getLogoUrl(): string
    {
        return asset('vendor/payment-gateway/imgs/myfatoorah.png');
    }

    public static function getMoreInfoUrl(): string
    {
        return 'https://myfatoorah.com';
    }

    public static function getSupportedCountries(): array
    {
        return ['KW', 'SA', 'AE', 'QA', 'BH', 'OM', 'JO', 'EG'];
    }

    public function getName(): string
    {
        return __('MyFatoorah');
    }

    public function getDescription(): string
    {
        return __('Accept payments via MyFatoorah (KNET, Benefit, mada, cards, Apple Pay, and more across GCC).');
    }

    public function getConfigurationFields(): array
    {
        return [
            new TextField(
                name: 'api_token_test',
                label: 'API Token (Test)',
                required: true,
                encrypted: true,
                description: 'Your MyFatoorah API token for test/sandbox environment.'
            ),
            new TextField(
                name: 'api_token_live',
                label: 'API Token (Live)',
                required: false,
                encrypted: true,
                description: 'Your MyFatoorah API token for production environment.'
            ),
            new CheckboxField(
                name: 'test_mode',
                label: 'Test Mode',
                default: true,
                description: 'Enable test/sandbox mode for MyFatoorah payments.'
            ),
            new SelectField(
                name: 'country_iso',
                label: 'Country',
                required: true,
                options: [
                    'KWT' => 'Kuwait',
                    'SAU' => 'Saudi Arabia',
                    'ARE' => 'UAE',
                    'QAT' => 'Qatar',
                    'BHR' => 'Bahrain',
                    'OMN' => 'Oman',
                    'JOD' => 'Jordan',
                    'EGY' => 'Egypt',
                ],
                default: 'KWT',
                description: 'Select your MyFatoorah account country. Each country uses a different API endpoint.'
            ),
        ];
    }

    public function validateConfiguration(): bool
    {
        $countryIso = $this->paymentMethod->getSetting('country_iso');
        if (empty($countryIso)) {
            return false;
        }

        $testMode = $this->paymentMethod->getSetting('test_mode', true);

        if ($testMode) {
            return ! empty($this->paymentMethod->getSetting('api_token_test'));
        }

        return ! empty($this->paymentMethod->getSetting('api_token_live'));
    }

    public function processPayment(PaymentOrder $paymentOrder)
    {
        Log::info('MyFatoorah Payment Processing Started', [
            'order_code' => $paymentOrder->order_code,
            'amount' => $paymentOrder->amount,
            'currency' => $paymentOrder->currency,
        ]);

        if (! $this->paymentMethod->relationLoaded('settings')) {
            $this->paymentMethod->load('settings');
        }

        try {
            $apiToken = $this->getApiToken();
            $baseUrl = $this->getBaseUrl();

            if (empty($apiToken)) {
                throw new \Exception('MyFatoorah API token is not configured.');
            }

            $callbackUrl = $this->getCallbackUrl();

            $payload = [
                'InvoiceValue' => round($paymentOrder->amount, 2),
                'CustomerName' => $paymentOrder->customer_name ?: 'Customer',
                'CustomerEmail' => $paymentOrder->customer_email ?: '',
                'CustomerMobile' => $paymentOrder->customer_phone ?: '',
                'NotificationOption' => 'LNK',
                'CallBackUrl' => $callbackUrl,
                'ErrorUrl' => $callbackUrl,
                'Language' => 'en',
                'CustomerReference' => $paymentOrder->order_code,
                'DisplayCurrencyIso' => $paymentOrder->currency ?? 'KWD',
            ];

            Log::info('MyFatoorah SendPayment Request', [
                'url' => $baseUrl.'/v2/SendPayment',
                'order_code' => $paymentOrder->order_code,
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiToken,
                'Content-Type' => 'application/json',
            ])->post($baseUrl.'/v2/SendPayment', $payload);

            Log::info('MyFatoorah SendPayment Response', [
                'status_code' => $response->status(),
                'response_body' => $response->body(),
            ]);

            if (! $response->successful()) {
                $errorData = $response->json();
                $errorMessage = $errorData['Message'] ?? $response->body();
                throw new \Exception('MyFatoorah: '.$errorMessage);
            }

            $responseData = $response->json();
            $isSuccess = $responseData['IsSuccess'] ?? false;

            if (! $isSuccess) {
                throw new \Exception('MyFatoorah: '.($responseData['Message'] ?? 'Unknown error'));
            }

            $data = $responseData['Data'] ?? [];
            $invoiceUrl = $data['InvoiceURL'] ?? null;
            $invoiceId = $data['InvoiceId'] ?? null;

            if (! $invoiceUrl) {
                throw new \Exception('MyFatoorah did not return an invoice URL.');
            }

            $paymentOrder->update([
                'payment_data' => array_merge($paymentOrder->payment_data ?? [], [
                    'myfatoorah_invoice_id' => $invoiceId,
                ]),
            ]);

            Log::info('MyFatoorah Invoice Created', [
                'order_code' => $paymentOrder->order_code,
                'invoice_id' => $invoiceId,
            ]);

            return redirect()->away($invoiceUrl);
        } catch (\Exception $e) {
            report($e);

            return view('payment-gateway::plugins.myfatoorah-payment-error', [
                'paymentOrder' => $paymentOrder,
                'paymentMethod' => $this->paymentMethod,
                'failureUrl' => $this->getFailureUrl($paymentOrder),
                'errorMessage' => $e->getMessage(),
            ]);
        }
    }

    public function handleCallback(array $callbackData): CallbackResponse
    {
        Log::info('MyFatoorah Callback Received', [
            'raw_callback_data' => $callbackData,
        ]);

        $paymentId = $callbackData['paymentId'] ?? $callbackData['PaymentId'] ?? null;

        if (! $paymentId) {
            return CallbackResponse::failure(
                orderCode: 'unknown',
                message: 'Payment ID is required from MyFatoorah callback'
            );
        }

        try {
            $apiToken = $this->getApiToken();
            $baseUrl = $this->getBaseUrl();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiToken,
                'Content-Type' => 'application/json',
            ])->post($baseUrl.'/v2/GetPaymentStatus', [
                'KeyType' => 'PaymentId',
                'Key' => $paymentId,
            ]);

            if (! $response->successful()) {
                return CallbackResponse::failure(
                    orderCode: 'unknown',
                    message: 'Failed to verify MyFatoorah payment status'
                );
            }

            $responseData = $response->json();
            $data = $responseData['Data'] ?? [];
            $invoiceStatus = $data['InvoiceStatus'] ?? null;
            $invoiceId = $data['InvoiceId'] ?? null;
            $orderCode = $data['CustomerReference'] ?? null;
            $invoiceTransactions = $data['InvoiceTransactions'] ?? [];

            $transactionId = null;
            foreach ($invoiceTransactions as $transaction) {
                if (($transaction['TransactionStatus'] ?? '') === 'Succss') {
                    $transactionId = $transaction['TransactionId'] ?? null;
                    break;
                }
            }

            Log::info('MyFatoorah Payment Status', [
                'order_code' => $orderCode,
                'invoice_id' => $invoiceId,
                'status' => $invoiceStatus,
                'payment_id' => $paymentId,
            ]);

            if (! $orderCode) {
                return CallbackResponse::failure(
                    orderCode: 'unknown',
                    message: 'Customer reference (order code) not found in MyFatoorah response'
                );
            }

            if ($invoiceStatus === 'Paid') {
                return CallbackResponse::success(
                    orderCode: (string) $orderCode,
                    transactionId: $transactionId ? (string) $transactionId : 'mf_'.$invoiceId,
                    message: 'Payment completed successfully via MyFatoorah',
                    additionalData: [
                        'myfatoorah_invoice_id' => $invoiceId,
                        'myfatoorah_payment_id' => $paymentId,
                        'myfatoorah_invoice_status' => $invoiceStatus,
                    ]
                );
            }

            return CallbackResponse::failure(
                orderCode: (string) $orderCode,
                message: __('payment_failed'),
                status: $invoiceStatus,
                additionalData: [
                    'myfatoorah_invoice_id' => $invoiceId,
                    'myfatoorah_payment_id' => $paymentId,
                    'myfatoorah_invoice_status' => $invoiceStatus,
                ]
            );
        } catch (\Exception $e) {
            report($e);

            return CallbackResponse::failure(
                orderCode: 'unknown',
                message: 'Error verifying MyFatoorah payment status'
            );
        }
    }

    public function refund(PaymentOrder $paymentOrder): RefundResponse
    {
        $invoiceId = $paymentOrder->payment_data['myfatoorah_invoice_id'] ?? null;

        if (! $invoiceId) {
            return RefundResponse::failure(
                orderCode: $paymentOrder->order_code,
                message: 'MyFatoorah invoice ID not found in payment data'
            );
        }

        try {
            $apiToken = $this->getApiToken();
            $baseUrl = $this->getBaseUrl();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiToken,
                'Content-Type' => 'application/json',
            ])->post($baseUrl.'/v2/MakeRefund', [
                'KeyType' => 'InvoiceId',
                'Key' => $invoiceId,
                'Amount' => round($paymentOrder->amount, 2),
                'Comment' => 'Merchant initiated refund',
            ]);

            if (! $response->successful()) {
                return RefundResponse::failure(
                    orderCode: $paymentOrder->order_code,
                    message: 'MyFatoorah refund request failed'
                );
            }

            $refundData = $response->json();
            $isSuccess = $refundData['IsSuccess'] ?? false;

            if ($isSuccess) {
                $data = $refundData['Data'] ?? [];
                $refundId = $data['RefundId'] ?? $data['Key'] ?? null;

                return RefundResponse::success(
                    orderCode: $paymentOrder->order_code,
                    refundedAmount: $paymentOrder->amount,
                    refundTransactionId: $refundId ? (string) $refundId : null,
                    originalTransactionId: (string) $invoiceId,
                    message: 'Refund processed successfully via MyFatoorah'
                );
            }

            return RefundResponse::failure(
                orderCode: $paymentOrder->order_code,
                message: 'MyFatoorah refund failed: '.($refundData['Message'] ?? 'Unknown error')
            );
        } catch (\Exception $e) {
            report($e);

            return RefundResponse::failure(
                orderCode: $paymentOrder->order_code,
                message: 'Error processing MyFatoorah refund'
            );
        }
    }

    private function getApiToken(): string
    {
        $testMode = $this->paymentMethod->getSetting('test_mode', true);

        return $testMode
            ? $this->paymentMethod->getSetting('api_token_test', '')
            : $this->paymentMethod->getSetting('api_token_live', '');
    }

    public function getBaseUrl(): string
    {
        $testMode = $this->paymentMethod->getSetting('test_mode', true);

        if ($testMode) {
            return self::BASE_URLS['test'];
        }

        $countryIso = strtoupper($this->paymentMethod->getSetting('country_iso', 'KWT'));

        return self::BASE_URLS[$countryIso] ?? self::BASE_URLS['KWT'];
    }
}
