<?php

namespace Trinavo\PaymentGateway\Plugins\MadfoatDirectPay;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Trinavo\PaymentGateway\Configuration\CheckboxField;
use Trinavo\PaymentGateway\Configuration\PasswordField;
use Trinavo\PaymentGateway\Configuration\SelectField;
use Trinavo\PaymentGateway\Configuration\TextField;
use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\CallbackResponse;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Models\RefundResponse;
use Trinavo\PaymentGateway\Plugins\Madfoat\Concerns\MadfoatTransportTrait;
use Trinavo\PaymentGateway\Services\PaymentGatewayService;

/**
 * Madfoat DirectPay (eFAWATEERcom) - biller-initiated redirect payment.
 *
 * Customer is on our site, clicks "Top up wallet" (or any other PaymentOrder
 * checkout), we build a pipe-separated RequestParams string, BCrypt-sign it,
 * and redirect to eFAWATEERcom. They collect bank + OTP, then redirect back
 * to our callback URL with a signed ResponseParams blob.
 *
 * Two reconciliation paths land at our system:
 *
 *  1. Browser back-redirect → handleCallback() verifies the response signature
 *     and returns CallbackResponse::success/failure. The gateway's existing
 *     success path runs the PaymentOrder's success_callback (which for the
 *     wallet-topup flow credits the wallet via CartService::addFundsToWalletFromOrder).
 *
 *  2. Server-to-server biller-notification → handleInboundRequest() does the
 *     same thing without depending on the customer's browser. Idempotent
 *     against #1 via PaymentGatewayService::handlePaymentSuccess()'s isCompleted()
 *     short-circuit.
 *
 * Protocol reference: docs/madfoat/DirectPay-for-Billers-Integration_V2.3.md
 */
class MadfoatDirectPayPaymentPlugin extends PaymentPluginInterface
{
    use MadfoatTransportTrait;

    public static function getLogoUrl(): string
    {
        return asset('vendor/payment-gateway/imgs/madfoat.png');
    }

    public static function getMoreInfoUrl(): string
    {
        return 'https://www.madfoo3at.com';
    }

    public static function getSupportedCountries(): array
    {
        return ['JO'];
    }

    public function getName(): string
    {
        return __('Madfoat DirectPay');
    }

    public function getDescription(): string
    {
        return __('Pay directly from your Jordanian bank account via eFAWATEERcom DirectPay. No bill number needed: your account UID is used.');
    }

    public function getConfigurationFields(): array
    {
        return [
            new TextField(
                name: 'biller_code',
                label: 'Biller Code',
                required: true,
                description: 'Numeric BillerCode assigned by Madfoat/eFAWATEERcom (max 5 digits).',
                placeholder: 'e.g. 10',
                maxLength: 5,
            ),
            new TextField(
                name: 'service_code',
                label: 'Service Code',
                required: true,
                description: 'Numeric ServiceCode assigned by Madfoat for this biller (max 5 digits).',
                placeholder: 'e.g. 2',
                maxLength: 5,
            ),
            new SelectField(
                name: 'payment_type',
                label: 'Payment Type',
                options: [
                    (string) MadfoatDirectPayService::PAYMENT_TYPE_POSTPAID => 'Postpaid (BillingNo = user UID)',
                    (string) MadfoatDirectPayService::PAYMENT_TYPE_PREPAID => 'Prepaid',
                ],
                required: true,
                default: (string) MadfoatDirectPayService::PAYMENT_TYPE_POSTPAID,
                description: 'Postpaid uses the customer\'s UID as the BillingNo. Prepaid does not require a BillingNo.',
            ),
            new PasswordField(
                name: 'secret_token_test',
                label: 'Secret Token (Test)',
                required: true,
                description: 'Shared secret provided by Madfoat for the staging environment. Used to BCrypt-sign requests and verify responses.',
            ),
            new PasswordField(
                name: 'secret_token_production',
                label: 'Secret Token (Production)',
                required: false,
                description: 'Shared secret provided by Madfoat for the production environment.',
            ),
            new SelectField(
                name: 'language',
                label: 'Payment Page Language',
                options: [
                    'AR' => 'Arabic',
                    'EN' => 'English',
                ],
                required: false,
                default: 'AR',
                description: 'Language eFAWATEERcom uses on the hosted payment page.',
            ),
            new TextField(
                name: 'allowed_ips',
                label: 'Allowed IPs',
                required: false,
                default: '',
                description: 'Comma-separated list of allowed IPs for inbound biller-notification requests. Leave empty to allow all (for testing).',
                placeholder: '10.211.211.249,10.211.211.241',
            ),
            new TextField(
                name: 'auth_username',
                label: 'Basic Auth Username',
                required: false,
                default: '',
                description: 'Username for HTTP Basic Authentication on inbound biller-notification. Leave empty to disable.',
            ),
            new PasswordField(
                name: 'auth_password',
                label: 'Basic Auth Password',
                required: false,
                description: 'Password for HTTP Basic Authentication on inbound biller-notification.',
            ),
            new CheckboxField(
                name: 'test_mode',
                label: 'Test Mode',
                default: true,
                description: 'Use Madfoat DirectPay staging environment. Disable to hit production.',
            ),
        ];
    }

    public function validateConfiguration(): bool
    {
        if (empty($this->paymentMethod->getSetting('biller_code'))) {
            return false;
        }
        if (empty($this->paymentMethod->getSetting('service_code'))) {
            return false;
        }

        return ! empty($this->getActiveSecret());
    }

    public function processPayment(PaymentOrder $paymentOrder)
    {
        $service = $this->getService();
        $testMode = (bool) $this->paymentMethod->getSetting('test_mode', true);
        $secret = $this->getActiveSecret();

        if ($secret === '') {
            return $this->renderError($paymentOrder, __('Madfoat DirectPay is not fully configured (missing secret token).'));
        }

        $billingNo = $this->resolveBillingNo($paymentOrder);
        if ($billingNo === null) {
            return $this->renderError($paymentOrder, __('Madfoat DirectPay could not resolve a UID for this payment. Wallet top-up requires a logged-in user.'));
        }

        $params = [
            'BilrTrxNo' => $paymentOrder->order_code,
            'BillerCode' => (string) $this->paymentMethod->getSetting('biller_code'),
            'ServiceCode' => (string) $this->paymentMethod->getSetting('service_code'),
            'PaymentType' => (string) $this->paymentMethod->getSetting('payment_type', MadfoatDirectPayService::PAYMENT_TYPE_POSTPAID),
            'Currency' => $paymentOrder->currency ?? 'JOD',
            'BillingNo' => $billingNo,
            'PrepaidCatCode' => '',
            'Amount' => $service->formatAmount((float) $paymentOrder->amount),
            'StatmntNartive' => '',
            'CustEmail' => $paymentOrder->customer_email ?? '',
            'Language' => (string) $this->paymentMethod->getSetting('language', 'AR'),
            'OtherDetails' => '',
        ];

        $gatewayUrl = $service->getGatewayUrl($testMode);
        $requestParams = $service->buildRequestParams($params, $secret);

        Log::info('Madfoat DirectPay: redirect built', [
            'order_code' => $paymentOrder->order_code,
            'billing_no' => $billingNo,
            'test_mode' => $testMode,
        ]);

        return view('payment-gateway::plugins.madfoat-directpay-redirect', [
            'paymentOrder' => $paymentOrder,
            'paymentMethod' => $this->paymentMethod,
            'gatewayUrl' => $gatewayUrl,
            'requestParams' => $requestParams,
        ]);
    }

    public function handleCallback(array $callbackData): CallbackResponse
    {
        $raw = $callbackData['ResponseParams'] ?? '';
        $service = $this->getService();
        $parsed = $service->parseResponseParams($raw);

        $orderCode = $parsed['BilrTrxNo'] ?: 'unknown';

        if ($parsed['SecureHash'] === null || $parsed['BilrTrxNo'] === null) {
            Log::warning('Madfoat DirectPay: malformed ResponseParams', ['raw' => $raw]);

            return CallbackResponse::failure(
                orderCode: $orderCode,
                message: __('Malformed DirectPay response.'),
            );
        }

        $secret = $this->getActiveSecret();

        if (! $service->verifyResponseSignature($parsed['_paramsWithoutHash'], $parsed['SecureHash'], $secret)) {
            Log::warning('Madfoat DirectPay: invalid response signature', ['order_code' => $orderCode]);

            return CallbackResponse::failure(
                orderCode: $orderCode,
                message: __('Invalid DirectPay response signature.'),
            );
        }

        $trxStatus = (int) ($parsed['TrxStatus'] ?? 0);
        $paymentStatus = $parsed['PaymentStatus'] !== null ? (int) $parsed['PaymentStatus'] : null;
        $directPayTrxNo = $parsed['DirectPayTrxNo'] ?? null;

        $additionalData = [
            'directpay_trx_no' => $directPayTrxNo,
            'trx_status' => $trxStatus,
            'payment_status' => $paymentStatus,
        ];

        // Success requires both validation OK (TrxStatus=1) and payment OK (PaymentStatus=1).
        // PaymentStatus=2 (Under Processing) is treated as pending - the biller-notification
        // webhook will settle it later.
        if ($trxStatus === MadfoatDirectPayService::TRX_STATUS_SUCCESS
            && $paymentStatus === MadfoatDirectPayService::PAYMENT_STATUS_SUCCESS) {
            return CallbackResponse::success(
                orderCode: $orderCode,
                transactionId: $directPayTrxNo ?: 'directpay_'.uniqid(),
                message: __('Payment confirmed via Madfoat DirectPay.'),
                additionalData: $additionalData,
            );
        }

        if ($trxStatus === MadfoatDirectPayService::TRX_STATUS_SUCCESS
            && $paymentStatus === MadfoatDirectPayService::PAYMENT_STATUS_UNDER_PROCESSING) {
            return CallbackResponse::pending(
                orderCode: $orderCode,
                transactionId: $directPayTrxNo,
                message: __('Payment is under processing. The wallet will be credited once Madfoat notifies us.'),
                additionalData: $additionalData,
            );
        }

        $errorMessage = $service->getStatusCodeMessage($trxStatus);

        // TrxStatus=12 is "Payment transaction is canceled by customer".
        if ($trxStatus === 12) {
            return CallbackResponse::cancelled(
                orderCode: $orderCode,
                message: $errorMessage,
                additionalData: $additionalData,
            );
        }

        return CallbackResponse::failure(
            orderCode: $orderCode,
            message: $errorMessage,
            status: (string) $trxStatus,
            additionalData: $additionalData,
        );
    }

    public function supportsInboundRequests(): bool
    {
        return true;
    }

    public function handleInboundRequest(string $action, array $data): JsonResponse
    {
        if (! $this->isIpAllowed(request()->ip())) {
            Log::warning('Madfoat DirectPay: unauthorized IP attempt', [
                'ip' => request()->ip(),
                'action' => $action,
            ]);

            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (! $this->isBasicAuthValid()) {
            Log::warning('Madfoat DirectPay: Basic Auth failed', [
                'ip' => request()->ip(),
                'action' => $action,
            ]);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return match ($action) {
            'biller-notification' => $this->handleBillerNotification($data),
            default => response()->json(['error' => 'Unknown action: '.$action], 400),
        };
    }

    public function refund(PaymentOrder $paymentOrder): RefundResponse
    {
        return RefundResponse::failure(
            orderCode: $paymentOrder->order_code,
            message: __('Refunds via Madfoat DirectPay are not supported programmatically. Process manually through eFAWATEERcom.'),
        );
    }

    /**
     * Handle the server-to-server biller payment notification.
     *
     * Authoritative success signal - fires even if the user closes the browser.
     * Idempotent against duplicate deliveries via PaymentGatewayService::handlePaymentSuccess.
     */
    protected function handleBillerNotification(array $data): JsonResponse
    {
        $service = $this->getService();

        // Accept either the same pipe-separated ResponseParams shape Madfoat uses
        // on the back-redirect, or individual fields posted directly.
        $rawParams = $data['ResponseParams'] ?? null;
        if ($rawParams) {
            $parsed = $service->parseResponseParams($rawParams);
        } else {
            $parsed = [
                'BilrTrxNo' => $data['BilrTrxNo'] ?? null,
                'TrxStatus' => $data['TrxStatus'] ?? null,
                'DirectPayTrxNo' => $data['DirectPayTrxNo'] ?? null,
                'Amount' => $data['Amount'] ?? null,
                'PaymentStatus' => $data['PaymentStatus'] ?? null,
                'OtherDetails' => $data['OtherDetails'] ?? null,
                'SecureHash' => $data['SecureHash'] ?? null,
                '_paramsWithoutHash' => null,
            ];
        }

        $orderCode = $parsed['BilrTrxNo'];

        if (! $orderCode) {
            return response()->json(['error' => 'BilrTrxNo is required'], 400);
        }

        // Verify signature when ResponseParams shape arrived (only path with a
        // canonical paramsWithoutHash). For per-field POSTs we trust the IP +
        // Basic-Auth gate.
        if ($rawParams !== null) {
            $secret = $this->getActiveSecret();
            if (! $service->verifyResponseSignature($parsed['_paramsWithoutHash'] ?? '', $parsed['SecureHash'] ?? '', $secret)) {
                Log::warning('Madfoat DirectPay: biller-notification signature invalid', ['order_code' => $orderCode]);

                return response()->json(['error' => 'Invalid signature'], 401);
            }
        }

        $paymentOrder = PaymentOrder::where('order_code', $orderCode)->first();

        if (! $paymentOrder) {
            return response()->json(['error' => 'PaymentOrder not found', 'order_code' => $orderCode], 404);
        }

        $this->attachInboundRequestToPaymentOrder($paymentOrder->id);

        // Idempotent re-delivery: bank may re-send the notification.
        if ($paymentOrder->isCompleted()) {
            Log::info('Madfoat DirectPay: notification re-delivery (already completed)', [
                'order_code' => $orderCode,
                'directpay_trx_no' => $parsed['DirectPayTrxNo'] ?? null,
            ]);

            return response()->json(['status' => 'already_completed', 'order_code' => $orderCode]);
        }

        $trxStatus = (int) ($parsed['TrxStatus'] ?? 0);
        $paymentStatus = $parsed['PaymentStatus'] !== null ? (int) $parsed['PaymentStatus'] : null;
        $directPayTrxNo = $parsed['DirectPayTrxNo'] ?? null;

        $paymentData = [
            'external_transaction_id' => $directPayTrxNo,
            'directpay_trx_no' => $directPayTrxNo,
            'trx_status' => $trxStatus,
            'payment_status' => $paymentStatus,
            'source' => 'biller-notification',
        ];

        if ($trxStatus === MadfoatDirectPayService::TRX_STATUS_SUCCESS
            && $paymentStatus === MadfoatDirectPayService::PAYMENT_STATUS_SUCCESS) {
            $paymentOrder->update(['external_transaction_id' => $directPayTrxNo]);

            // Delegates to the gateway's standard success path: marks the
            // PaymentOrder completed AND fires the success_callback eval (which
            // for wallet top-ups runs CartService::addFundsToWalletFromOrder).
            app(PaymentGatewayService::class)->handlePaymentSuccess($paymentOrder, $paymentData);

            return response()->json(['status' => 'success', 'order_code' => $orderCode]);
        }

        if ($trxStatus === MadfoatDirectPayService::TRX_STATUS_SUCCESS
            && $paymentStatus === MadfoatDirectPayService::PAYMENT_STATUS_UNDER_PROCESSING) {
            // Stay PENDING - another notification will follow.
            $paymentOrder->update([
                'payment_data' => array_merge($paymentOrder->payment_data ?? [], $paymentData),
            ]);

            return response()->json(['status' => 'pending', 'order_code' => $orderCode]);
        }

        app(PaymentGatewayService::class)->handlePaymentFailure($paymentOrder, $paymentData);

        return response()->json(['status' => 'failed', 'order_code' => $orderCode]);
    }

    protected function getService(): MadfoatDirectPayService
    {
        return new MadfoatDirectPayService;
    }

    protected function getActiveSecret(): string
    {
        $testMode = (bool) $this->paymentMethod->getSetting('test_mode', true);

        return $testMode
            ? (string) $this->paymentMethod->getSetting('secret_token_test', '')
            : (string) $this->paymentMethod->getSetting('secret_token_production', '');
    }

    /**
     * Resolve the BillingNo we send to Madfoat.
     *
     * For wallet top-ups the convention is `customer_data->user_id` → user's
     * `user_identifier`. Plays the role of "saved bill at the bank" so the
     * customer doesn't type a per-transaction invoice number.
     */
    protected function resolveBillingNo(PaymentOrder $paymentOrder): ?string
    {
        $userId = $paymentOrder->customer_data['user_id'] ?? null;
        if (! $userId) {
            return null;
        }

        $userClass = '\App\Models\User';
        if (! class_exists($userClass)) {
            return null;
        }

        /** @var \App\Models\User|null $user */
        $user = $userClass::find($userId);
        if (! $user) {
            return null;
        }

        $uid = $user->user_identifier ?? null;

        return $uid ? (string) $uid : null;
    }

    protected function renderError(PaymentOrder $paymentOrder, string $message)
    {
        Log::error('Madfoat DirectPay: processPayment cannot build request', [
            'order_code' => $paymentOrder->order_code,
            'message' => $message,
        ]);

        return view('payment-gateway::plugins.madfoat-directpay-error', [
            'paymentOrder' => $paymentOrder,
            'paymentMethod' => $this->paymentMethod,
            'failureUrl' => $this->getFailureUrl($paymentOrder),
            'errorMessage' => $message,
        ]);
    }
}
