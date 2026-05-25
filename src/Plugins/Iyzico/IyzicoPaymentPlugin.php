<?php

namespace Trinavo\PaymentGateway\Plugins\Iyzico;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Trinavo\PaymentGateway\Configuration\CheckboxField;
use Trinavo\PaymentGateway\Configuration\TextField;
use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\CallbackResponse;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Models\RefundResponse;

class IyzicoPaymentPlugin extends PaymentPluginInterface
{
    private const LIVE_BASE_URL = 'https://api.iyzipay.com';

    private const SANDBOX_BASE_URL = 'https://sandbox-api.iyzipay.com';

    private const INITIALIZE_PATH = '/payment/iyzipos/checkoutform/initialize/auth/ecom';

    private const RETRIEVE_PATH = '/payment/iyzipos/checkoutform/auth/ecom/detail';

    public static function getLogoUrl(): string
    {
        return asset('vendor/payment-gateway/imgs/Iyzico.svg');
    }

    public static function getMoreInfoUrl(): string
    {
        return 'https://www.iyzico.com';
    }

    public static function getSupportedCountries(): array
    {
        return ['TR'];
    }

    public function getName(): string
    {
        return __('Iyzico');
    }

    public function getDescription(): string
    {
        return __('Integrate iyzico payment gateway using their Checkout Form.');
    }

    public function getConfigurationFields(): array
    {
        return [
            new TextField(
                name: 'api_key',
                label: 'API Key',
                required: true,
                encrypted: true,
                description: 'Your iyzico API Key. Get this from your iyzico merchant panel.'
            ),
            new TextField(
                name: 'secret_key',
                label: 'Secret Key',
                required: true,
                encrypted: true,
                description: 'Your iyzico Secret Key. Get this from your iyzico merchant panel.'
            ),
            new TextField(
                name: 'currency',
                label: 'Currency',
                required: false,
                encrypted: false,
                description: 'Payment currency. One of: TRY, USD, EUR, GBP, NOK, CHF.',
                default: 'TRY'
            ),
            new CheckboxField(
                name: 'test_mode',
                label: 'Test Mode',
                default: true,
                description: 'Enable sandbox mode (routes requests to sandbox-api.iyzipay.com).'
            ),
        ];
    }

    public function validateConfiguration(): bool
    {
        return ! empty($this->paymentMethod->getSetting('api_key'))
            && ! empty($this->paymentMethod->getSetting('secret_key'));
    }

    public function processPayment(PaymentOrder $paymentOrder)
    {
        Log::info('Iyzico Payment Processing Started', [
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
            [$token, $checkoutFormContent] = $this->initializeCheckoutForm($paymentOrder);

            $paymentOrder->update(['external_transaction_id' => $token]);

            return view('payment-gateway::plugins.iyzico-payment', [
                'paymentOrder' => $paymentOrder,
                'paymentMethod' => $this->paymentMethod,
                'iyzicoToken' => $token,
                'checkoutFormContent' => $checkoutFormContent,
                'callbackUrl' => $this->getCallbackUrl(),
                'successUrl' => $this->getSuccessUrl($paymentOrder),
                'failureUrl' => $this->getFailureUrl($paymentOrder),
            ]);
        } catch (\Exception $e) {
            report($e);

            return view('payment-gateway::plugins.iyzico-payment-error', [
                'paymentOrder' => $paymentOrder,
                'paymentMethod' => $this->paymentMethod,
                'failureUrl' => $this->getFailureUrl($paymentOrder),
            ]);
        }
    }

    public function handleCallback(array $callbackData): CallbackResponse
    {
        Log::info('Iyzico Callback Received', [
            'callback_keys' => array_keys($callbackData),
            'has_token' => ! empty($callbackData['token']),
        ]);

        $token = $callbackData['token'] ?? null;

        if (! $token) {
            Log::error('Iyzico Callback Missing token', ['callback_data' => $callbackData]);

            return CallbackResponse::failure(
                orderCode: 'unknown',
                message: 'Iyzico callback is missing token'
            );
        }

        /** @var PaymentOrder|null $order */
        $order = PaymentOrder::where('external_transaction_id', $token)->first();

        if (! $order) {
            Log::error('Iyzico Callback Order Not Found', ['token_prefix' => substr($token, 0, 8).'...']);

            return CallbackResponse::failure(
                orderCode: 'unknown',
                message: 'Order not found for the supplied iyzico token'
            );
        }

        try {
            $result = $this->retrieveCheckoutForm($order, $token);
        } catch (\Exception $e) {
            report($e);

            return CallbackResponse::failure(
                orderCode: $order->order_code,
                message: __('payment_gateway_error')
            );
        }

        Log::info('Iyzico Callback Retrieved', [
            'order_code' => $order->order_code,
            'status' => $result['status'] ?? null,
            'paymentStatus' => $result['paymentStatus'] ?? null,
            'fraudStatus' => $result['fraudStatus'] ?? null,
            'paymentId' => $result['paymentId'] ?? null,
        ]);

        $status = $result['status'] ?? null;
        $paymentStatus = $result['paymentStatus'] ?? null;

        if ($status !== 'success' || $paymentStatus !== 'SUCCESS') {
            Log::warning('Iyzico Payment Not Successful', [
                'order_code' => $order->order_code,
                'errorCode' => $result['errorCode'] ?? null,
                'errorMessage' => $result['errorMessage'] ?? null,
            ]);

            return CallbackResponse::failure(
                orderCode: $order->order_code,
                message: __('payment_failed'),
                additionalData: [
                    'iyzico_status' => $status,
                    'iyzico_payment_status' => $paymentStatus,
                    'iyzico_error_code' => $result['errorCode'] ?? null,
                    'iyzico_error_message' => $result['errorMessage'] ?? null,
                ]
            );
        }

        $fraudStatus = (int) ($result['fraudStatus'] ?? 0);

        if ($fraudStatus === 0) {
            return CallbackResponse::pending(
                orderCode: $order->order_code,
                transactionId: $result['paymentId'] ?? null,
                message: 'Iyzico fraud review in progress',
                additionalData: [
                    'iyzico_payment_id' => $result['paymentId'] ?? null,
                    'iyzico_fraud_status' => $fraudStatus,
                ]
            );
        }

        if ($fraudStatus === -1) {
            return CallbackResponse::failure(
                orderCode: $order->order_code,
                message: __('payment_failed'),
                additionalData: [
                    'iyzico_payment_id' => $result['paymentId'] ?? null,
                    'iyzico_fraud_status' => $fraudStatus,
                ]
            );
        }

        return CallbackResponse::success(
            orderCode: $order->order_code,
            transactionId: $result['paymentId'] ?? 'iyzico_'.uniqid(),
            message: 'Payment completed successfully via iyzico',
            additionalData: [
                'iyzico_payment_id' => $result['paymentId'] ?? null,
                'iyzico_fraud_status' => $fraudStatus,
                'iyzico_paid_price' => $result['paidPrice'] ?? null,
                'iyzico_currency' => $result['currency'] ?? null,
                'iyzico_installment' => $result['installment'] ?? null,
            ]
        );
    }

    public function refund(PaymentOrder $paymentOrder): RefundResponse
    {
        return RefundResponse::failure(
            orderCode: $paymentOrder->order_code,
            message: 'Refunds for iyzico are processed from the iyzico merchant panel.'
        );
    }

    /**
     * POST to the Checkout Form initialize endpoint and return [token, decoded checkoutFormContent].
     *
     * @return array{0:string,1:string}
     */
    private function initializeCheckoutForm(PaymentOrder $paymentOrder): array
    {
        $apiKey = $this->paymentMethod->getSetting('api_key');
        $secretKey = $this->paymentMethod->getSetting('secret_key');

        if (empty($apiKey) || empty($secretKey)) {
            throw new \Exception('Iyzico configuration is missing api_key or secret_key');
        }

        $body = $this->buildInitializeBody($paymentOrder);
        $jsonBody = json_encode($body, JSON_UNESCAPED_UNICODE);

        Log::info('Iyzico Initialize Request', [
            'url' => $this->baseUrl().self::INITIALIZE_PATH,
            'order_code' => $paymentOrder->order_code,
            'price' => $body['price'],
            'currency' => $body['currency'],
        ]);

        $response = $this->signedPost(self::INITIALIZE_PATH, $jsonBody);

        Log::info('Iyzico Initialize Response', [
            'status_code' => $response->status(),
            'response_body' => $response->body(),
        ]);

        if (! $response->successful()) {
            throw new \Exception(__('payment_gateway_error'));
        }

        $data = $response->json();

        if (! is_array($data) || ($data['status'] ?? null) !== 'success' || empty($data['token']) || empty($data['checkoutFormContent'])) {
            Log::error('Iyzico Initialize Failed', [
                'order_code' => $paymentOrder->order_code,
                'errorCode' => $data['errorCode'] ?? null,
                'errorMessage' => $data['errorMessage'] ?? null,
            ]);
            throw new \Exception(__('payment_gateway_error'));
        }

        // checkoutFormContent is delivered as a base64-encoded HTML+script blob.
        $decoded = base64_decode($data['checkoutFormContent'], true);
        if ($decoded === false || $decoded === '') {
            // Some iyzico responses return the HTML+script unencoded already.
            $decoded = $data['checkoutFormContent'];
        }

        return [$data['token'], $decoded];
    }

    /**
     * POST to the Checkout Form retrieve endpoint and return the decoded JSON payload.
     */
    private function retrieveCheckoutForm(PaymentOrder $paymentOrder, string $token): array
    {
        $body = [
            'locale' => 'en',
            'conversationId' => $paymentOrder->order_code,
            'token' => $token,
        ];
        $jsonBody = json_encode($body, JSON_UNESCAPED_UNICODE);

        $response = $this->signedPost(self::RETRIEVE_PATH, $jsonBody);

        if (! $response->successful()) {
            throw new \Exception(__('payment_gateway_error'));
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new \Exception(__('payment_gateway_error'));
        }

        return $data;
    }

    /**
     * Build the CF initialize request body. Fills required buyer/address/basket fields
     * from the PaymentOrder when present, falling back to safe defaults — iyzico rejects
     * the request if any required string is empty.
     */
    private function buildInitializeBody(PaymentOrder $paymentOrder): array
    {
        $currency = $this->paymentMethod->getSetting('currency', 'TRY');
        $price = number_format((float) $paymentOrder->amount, 2, '.', '');
        $locale = app()->getLocale() === 'tr' ? 'tr' : 'en';

        [$firstName, $lastName] = $this->splitName($paymentOrder->customer_name);
        $fullName = trim($firstName.' '.$lastName);
        $email = $paymentOrder->customer_email ?: 'noreply@example.com';
        $phone = $paymentOrder->customer_phone ?: '+905350000000';
        $ip = request()->ip() ?? '0.0.0.0';

        $itemName = $paymentOrder->getLocalizedDescription() ?: $paymentOrder->order_code;

        $address = [
            'contactName' => $fullName,
            'city' => 'Istanbul',
            'country' => 'Turkey',
            'address' => 'Not provided',
            'zipCode' => '34000',
        ];

        return [
            'locale' => $locale,
            'conversationId' => $paymentOrder->order_code,
            'price' => $price,
            'paidPrice' => $price,
            'currency' => $currency,
            'basketId' => $paymentOrder->order_code,
            'paymentGroup' => 'PRODUCT',
            'callbackUrl' => $this->getCallbackUrl(),
            'buyer' => [
                'id' => 'BY-'.$paymentOrder->order_code,
                'name' => $firstName,
                'surname' => $lastName,
                'identityNumber' => '11111111111',
                'email' => $email,
                'gsmNumber' => $phone,
                'registrationAddress' => 'Not provided',
                'city' => 'Istanbul',
                'country' => 'Turkey',
                'zipCode' => '34000',
                'ip' => $ip,
            ],
            'shippingAddress' => $address,
            'billingAddress' => $address,
            'basketItems' => [
                [
                    'id' => $paymentOrder->order_code,
                    'name' => mb_substr($itemName, 0, 150),
                    'category1' => 'General',
                    'itemType' => 'VIRTUAL',
                    'price' => $price,
                ],
            ],
        ];
    }

    /**
     * Send a signed POST request to iyzico using the IYZWSv2 HMAC-SHA256 auth scheme.
     *
     * Signing:
     *   payload   = randomKey + uri.path + jsonBody
     *   signature = hex(hmac_sha256(payload, secretKey))
     *   auth      = base64("apiKey:{api}&randomKey:{rnd}&signature:{sig}")
     *   header    = "IYZWSv2 {auth}"
     */
    private function signedPost(string $path, string $jsonBody): \Illuminate\Http\Client\Response
    {
        $apiKey = $this->paymentMethod->getSetting('api_key');
        $secretKey = $this->paymentMethod->getSetting('secret_key');

        $randomKey = (string) Str::uuid();
        $signature = hash_hmac('sha256', $randomKey.$path.$jsonBody, $secretKey);
        $authStr = 'apiKey:'.$apiKey.'&randomKey:'.$randomKey.'&signature:'.$signature;
        $authHeader = 'IYZWSv2 '.base64_encode($authStr);

        return Http::withHeaders([
            'Authorization' => $authHeader,
            'x-iyzi-rnd' => $randomKey,
            'Accept' => 'application/json',
        ])
            ->withBody($jsonBody, 'application/json')
            ->post($this->baseUrl().$path);
    }

    private function baseUrl(): string
    {
        return $this->paymentMethod->getSetting('test_mode', true)
            ? self::SANDBOX_BASE_URL
            : self::LIVE_BASE_URL;
    }

    /**
     * Split a full name into [first, last] with sane fallbacks so iyzico's
     * required name/surname fields are never empty.
     *
     * @return array{0:string,1:string}
     */
    private function splitName(?string $fullName): array
    {
        $name = trim((string) $fullName);
        if ($name === '') {
            return ['Customer', 'Customer'];
        }

        $parts = preg_split('/\s+/', $name, 2) ?: [$name];
        $first = $parts[0] ?? 'Customer';
        $last = $parts[1] ?? $first;

        return [$first, $last];
    }
}
