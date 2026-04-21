<?php

namespace Trinavo\PaymentGateway\Plugins\Paylink;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Trinavo\PaymentGateway\Configuration\CheckboxField;
use Trinavo\PaymentGateway\Configuration\TextField;
use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\CallbackResponse;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Models\RefundResponse;

class PaylinkPaymentPlugin extends PaymentPluginInterface
{
    private const TEST_API_BASE_URL = 'https://restpilot.paylink.sa';

    private const PRODUCTION_API_BASE_URL = 'https://restapi.paylink.sa';

    public static function getLogoUrl(): string
    {
        return asset('vendor/payment-gateway/imgs/paylink.png');
    }

    public static function getMoreInfoUrl(): string
    {
        return 'https://paylink.sa';
    }

    public function getName(): string
    {
        return __('Paylink');
    }

    public function getDescription(): string
    {
        return __('Accept payments via Paylink (mada, Visa/Mastercard, Amex, STC Pay, urpay, Apple Pay, Tabby, Tamara) — popular in Saudi Arabia.');
    }

    public function getConfigurationFields(): array
    {
        return [
            new TextField(
                name: 'api_id_test',
                label: 'API ID (Test)',
                required: true,
                encrypted: true,
                description: 'Your Paylink test API ID from the merchant panel.'
            ),
            new TextField(
                name: 'secret_key_test',
                label: 'Secret Key (Test)',
                required: true,
                encrypted: true,
                description: 'Your Paylink test secret key.'
            ),
            new TextField(
                name: 'api_id_live',
                label: 'API ID (Live)',
                required: false,
                encrypted: true,
                description: 'Your Paylink production API ID.'
            ),
            new TextField(
                name: 'secret_key_live',
                label: 'Secret Key (Live)',
                required: false,
                encrypted: true,
                description: 'Your Paylink production secret key.'
            ),
            new CheckboxField(
                name: 'test_mode',
                label: 'Test Mode',
                default: true,
                description: 'Enable sandbox mode (restpilot.paylink.sa) for Paylink payments.'
            ),
        ];
    }

    public function validateConfiguration(): bool
    {
        [$apiId, $secretKey] = $this->getCredentials();

        return ! empty($apiId) && ! empty($secretKey);
    }

    public function processPayment(PaymentOrder $paymentOrder)
    {
        Log::info('Paylink Processing Started', [
            'order_code' => $paymentOrder->order_code,
            'amount' => $paymentOrder->amount,
            'currency' => $paymentOrder->currency,
        ]);

        if (! $this->paymentMethod->relationLoaded('settings')) {
            $this->paymentMethod->load('settings');
        }

        try {
            [$apiId, $secretKey] = $this->getCredentials();

            if (empty($apiId) || empty($secretKey)) {
                throw new \Exception('Paylink credentials are not configured.');
            }

            $idToken = $this->authenticate($apiId, $secretKey);
            $baseUrl = $this->getApiBaseUrl();

            $invoiceData = [
                'orderNumber' => (string) $paymentOrder->order_code,
                'amount' => round($paymentOrder->amount, 2),
                'currency' => $paymentOrder->currency ?: 'SAR',
                'callBackUrl' => $this->getCallbackUrl(),
                'cancelUrl' => $this->getFailureUrl($paymentOrder),
                'clientName' => $paymentOrder->customer_name ?: 'Customer',
                'clientMobile' => $paymentOrder->customer_phone ?: '0500000000',
                'products' => [
                    [
                        'title' => Str::limit(
                            $paymentOrder->description ?: 'Order '.$paymentOrder->order_code,
                            100,
                            ''
                        ),
                        'price' => round($paymentOrder->amount, 2),
                        'qty' => 1,
                    ],
                ],
            ];

            if (! empty($paymentOrder->customer_email)) {
                $invoiceData['clientEmail'] = $paymentOrder->customer_email;
            }

            Log::info('Paylink Add Invoice Request', [
                'order_code' => $paymentOrder->order_code,
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$idToken,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->post($baseUrl.'/api/addInvoice', $invoiceData);

            Log::info('Paylink Add Invoice Response', [
                'status_code' => $response->status(),
                'response_body' => $response->body(),
            ]);

            if (! $response->successful()) {
                $errorData = $response->json();
                $errorMessage = $errorData['title']
                    ?? $errorData['detail']
                    ?? $errorData['message']
                    ?? $response->body();
                throw new \Exception('Paylink: '.$errorMessage);
            }

            $responseData = $response->json();
            $redirectUrl = $responseData['url'] ?? null;
            $transactionNo = $responseData['transactionNo'] ?? null;

            if (empty($redirectUrl) || empty($transactionNo)) {
                throw new \Exception('Paylink did not return a redirect URL or transaction number.');
            }

            $paymentOrder->update([
                'payment_data' => array_merge($paymentOrder->payment_data ?? [], [
                    'paylink_transaction_no' => $transactionNo,
                    'paylink_url' => $redirectUrl,
                ]),
            ]);

            return redirect()->away($redirectUrl);
        } catch (\Exception $e) {
            report($e);

            return view('payment-gateway::plugins.paylink-payment-error', [
                'paymentOrder' => $paymentOrder,
                'paymentMethod' => $this->paymentMethod,
                'failureUrl' => $this->getFailureUrl($paymentOrder),
                'errorMessage' => $e->getMessage(),
            ]);
        }
    }

    public function handleCallback(array $callbackData): CallbackResponse
    {
        Log::info('Paylink Callback Received', [
            'raw_callback_data' => $callbackData,
        ]);

        $transactionNo = $callbackData['transactionNo']
            ?? $callbackData['data']['transactionNo']
            ?? null;
        $orderCode = $callbackData['merchantOrderNumber']
            ?? $callbackData['orderNumber']
            ?? $callbackData['order_code']
            ?? null;

        if (! $transactionNo) {
            return CallbackResponse::failure(
                orderCode: $orderCode ?? 'unknown',
                message: 'Paylink transaction number is missing from callback data'
            );
        }

        try {
            [$apiId, $secretKey] = $this->getCredentials();
            $idToken = $this->authenticate($apiId, $secretKey);
            $baseUrl = $this->getApiBaseUrl();

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$idToken,
                'Accept' => 'application/json',
            ])->get($baseUrl.'/api/getInvoice/'.$transactionNo);

            if (! $response->successful()) {
                return CallbackResponse::failure(
                    orderCode: $orderCode ?? 'unknown',
                    message: 'Failed to verify Paylink invoice status'
                );
            }

            $invoice = $response->json();
            $orderStatus = $invoice['orderStatus'] ?? null;
            $invoiceAmount = $invoice['amount'] ?? null;
            $fetchedOrderCode = $invoice['orderNumber']
                ?? $invoice['merchantOrderNumber']
                ?? $orderCode
                ?? 'unknown';

            Log::info('Paylink Invoice Verification', [
                'order_code' => $fetchedOrderCode,
                'transaction_no' => $transactionNo,
                'order_status' => $orderStatus,
            ]);

            if ($orderStatus === 'Paid') {
                return CallbackResponse::success(
                    orderCode: (string) $fetchedOrderCode,
                    transactionId: (string) $transactionNo,
                    message: 'Payment completed successfully via Paylink',
                    additionalData: [
                        'paylink_transaction_no' => $transactionNo,
                        'paylink_order_status' => $orderStatus,
                        'paylink_amount' => $invoiceAmount,
                    ]
                );
            }

            $statusMessage = in_array($orderStatus, ['Cancelled', 'Canceled', 'Expired'], true)
                ? 'cancelled'
                : 'failed';

            return CallbackResponse::failure(
                orderCode: (string) $fetchedOrderCode,
                message: __('payment_failed'),
                status: $statusMessage,
                additionalData: [
                    'paylink_transaction_no' => $transactionNo,
                    'paylink_order_status' => $orderStatus,
                ]
            );
        } catch (\Exception $e) {
            report($e);

            return CallbackResponse::failure(
                orderCode: $orderCode ?? 'unknown',
                message: 'Error verifying Paylink payment'
            );
        }
    }

    public function refund(PaymentOrder $paymentOrder): RefundResponse
    {
        return RefundResponse::failure(
            orderCode: $paymentOrder->order_code,
            message: 'Refunds are not supported via the Paylink API. Please process the refund from the Paylink merchant dashboard.'
        );
    }

    private function authenticate(string $apiId, string $secretKey): string
    {
        $baseUrl = $this->getApiBaseUrl();

        Log::info('Paylink Authentication Request', [
            'base_url' => $baseUrl,
        ]);

        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post($baseUrl.'/api/auth', [
            'apiId' => $apiId,
            'secretKey' => $secretKey,
            'persistToken' => false,
        ]);

        Log::info('Paylink Authentication Response', [
            'status_code' => $response->status(),
        ]);

        if (! $response->successful()) {
            $errorData = $response->json();
            $errorMessage = $errorData['title']
                ?? $errorData['detail']
                ?? $errorData['message']
                ?? $response->body();
            throw new \Exception('Paylink authentication failed: '.$errorMessage);
        }

        $idToken = $response->json('id_token');

        if (empty($idToken)) {
            throw new \Exception('Paylink authentication did not return an id_token.');
        }

        return $idToken;
    }

    private function getApiBaseUrl(): string
    {
        $testMode = $this->paymentMethod->getSetting('test_mode', true);

        return $testMode ? self::TEST_API_BASE_URL : self::PRODUCTION_API_BASE_URL;
    }

    private function getCredentials(): array
    {
        $testMode = $this->paymentMethod->getSetting('test_mode', true);

        if ($testMode) {
            return [
                $this->paymentMethod->getSetting('api_id_test', ''),
                $this->paymentMethod->getSetting('secret_key_test', ''),
            ];
        }

        return [
            $this->paymentMethod->getSetting('api_id_live', ''),
            $this->paymentMethod->getSetting('secret_key_live', ''),
        ];
    }
}
