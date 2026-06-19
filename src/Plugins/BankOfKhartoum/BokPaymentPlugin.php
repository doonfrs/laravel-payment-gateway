<?php

namespace Trinavo\PaymentGateway\Plugins\BankOfKhartoum;

use Trinavo\PaymentGateway\Configuration\CheckboxField;
use Trinavo\PaymentGateway\Configuration\NumberField;
use Trinavo\PaymentGateway\Configuration\PasswordField;
use Trinavo\PaymentGateway\Configuration\TextField;
use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\CallbackResponse;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Models\RefundResponse;
use Trinavo\PaymentGateway\Services\PaymentGatewayService;

/**
 * Bank of Khartoum (Bankak) - outbound, OTP-based direct debit.
 *
 * Flow (all on our site, full-page steps via the `interact` route):
 *   1. processPayment renders the account form (CIF + mobile).
 *   2. request_otp  -> BOK sends an OTP by SMS, we show the OTP form.
 *   3. verify_otp   -> BOK debits the Bankak account, we mark the order paid.
 *
 * No inbound webhooks (so it does not use the InboundBillingHandler contract).
 */
class BokPaymentPlugin extends PaymentPluginInterface
{
    public static function getLogoUrl(): string
    {
        return asset('vendor/payment-gateway/imgs/bok.png');
    }

    public static function getMoreInfoUrl(): string
    {
        return 'https://bankofkhartoum.com';
    }

    public static function getSupportedCountries(): array
    {
        return ['SD'];
    }

    public function getName(): string
    {
        return __('Bank of Khartoum (Bankak)');
    }

    public function getDescription(): string
    {
        return __('Pay from your Bankak account using a one-time password (OTP).');
    }

    public function getConfigurationFields(): array
    {
        return [
            new TextField(
                name: 'mCode',
                label: 'Merchant Code (mCode)',
                required: true,
                description: 'Unique merchant identification code provided by Bank of Khartoum.',
            ),
            new PasswordField(
                name: 'mPassword',
                label: 'Merchant Password',
                required: true,
                description: 'Merchant password provided by Bank of Khartoum (received by mail/SMS).',
            ),
            new PasswordField(
                name: 'mSecretKey',
                label: 'Merchant Secret Key',
                required: true,
                description: 'Merchant security key sent with every request.',
            ),
            new TextField(
                name: 'application_id',
                label: 'Application ID',
                required: true,
                default: 'MBOK',
                description: 'Application identifier agreed with Bank of Khartoum.',
            ),
            new TextField(
                name: 'base_url',
                label: 'Gateway Base URL',
                required: true,
                default: 'https://paymentgateway.bok-sd.com',
                description: 'Base URL of the Bank of Khartoum payment gateway (reached over the VPN).',
            ),
            new TextField(
                name: 'port',
                label: 'Gateway Port',
                required: true,
                default: '443',
                description: 'Port for the Bank of Khartoum payment gateway.',
            ),
            new NumberField(
                name: 'timeout',
                label: 'Request Timeout (seconds)',
                required: false,
                default: 30,
                description: 'HTTP timeout for calls to Bank of Khartoum.',
                min: 5,
                max: 120,
            ),
            new CheckboxField(
                name: 'check_is_live',
                label: 'Check service availability first',
                default: true,
                description: 'Call isLive before showing the payment form.',
            ),
            new CheckboxField(
                name: 'test_mode',
                label: 'Test Mode',
                default: true,
                description: 'Enable test/sandbox mode for Bank of Khartoum.',
            ),
            new TextField(
                name: 'instructions',
                label: 'Customer Instructions',
                required: false,
                default: 'Enable the E-commerce service in your Bankak app, then enter your account number (CIF) and mobile number to receive a verification code.',
                description: 'Instructions shown to the customer on the payment page.',
                maxLength: 500,
            ),
        ];
    }

    public function validateConfiguration(): bool
    {
        foreach (['mCode', 'mPassword', 'mSecretKey', 'application_id', 'base_url', 'port'] as $key) {
            if (empty($this->paymentMethod->getSetting($key))) {
                return false;
            }
        }

        return true;
    }

    public function processPayment(PaymentOrder $paymentOrder)
    {
        if ($this->paymentMethod->getSetting('check_is_live', true)) {
            $live = $this->getBokService()->isLive();

            if (! $live->success) {
                return view('payment-gateway::plugins.bok-payment-error', [
                    'paymentOrder' => $paymentOrder,
                    'paymentMethod' => $this->paymentMethod,
                    'message' => __('bok.unavailable'),
                    'failureUrl' => $this->getFailureUrl($paymentOrder),
                ]);
            }
        }

        return $this->renderAccountStep($paymentOrder);
    }

    public function handleWebAction(PaymentOrder $paymentOrder, array $data)
    {
        return match ($data['bok_step'] ?? null) {
            'request_otp' => $this->stepRequestOtp($paymentOrder, $data, fromVerify: false),
            'resend_otp' => $this->stepRequestOtp($paymentOrder, $data, fromVerify: true),
            'verify_otp' => $this->stepVerifyOtp($paymentOrder, $data),
            default => abort(404),
        };
    }

    /**
     * Step: request an OTP. Renders the OTP form on success, otherwise re-renders
     * the current step with a mapped error.
     */
    protected function stepRequestOtp(PaymentOrder $paymentOrder, array $data, bool $fromVerify)
    {
        $cif = trim((string) ($data['cif'] ?? $paymentOrder->payment_data['bok_cif'] ?? ''));
        $mobile = trim((string) ($data['mobile'] ?? $paymentOrder->payment_data['bok_mobile'] ?? ''));

        if ($cif === '' || $mobile === '') {
            return $this->renderAccountStep($paymentOrder, error: __('bok.error.fill_fields'));
        }

        $attempt = $this->currentAttempt($paymentOrder) + 1;
        $ref = $this->makeRef($paymentOrder, $attempt);
        $amount = $this->sdgAmount($paymentOrder);

        $result = $this->getBokService()->requestOtp($cif, $mobile, $amount, $ref);

        $this->persist($paymentOrder, [
            'bok_cif' => $cif,
            'bok_mobile' => $mobile,
            'bok_ref' => $ref,
            'bok_attempt' => $attempt,
            'bok_amount' => $amount,
        ]);

        if (! $result->success) {
            $error = __(BokResult::messageKey('otp_request', $result->code));

            return $fromVerify
                ? $this->renderOtpStep($paymentOrder, error: $error)
                : $this->renderAccountStep($paymentOrder, error: $error, cif: $cif, mobile: $mobile);
        }

        $this->persist($paymentOrder, ['bok_step' => 'otp']);

        return $this->renderOtpStep($paymentOrder, info: __('bok.code_sent'));
    }

    /**
     * Step: verify the OTP and process the debit.
     */
    protected function stepVerifyOtp(PaymentOrder $paymentOrder, array $data)
    {
        $otp = trim((string) ($data['otp'] ?? ''));
        $cif = (string) ($paymentOrder->payment_data['bok_cif'] ?? '');
        $mobile = (string) ($paymentOrder->payment_data['bok_mobile'] ?? '');
        $ref = (string) ($paymentOrder->payment_data['bok_ref'] ?? '');
        $amount = (string) ($paymentOrder->payment_data['bok_amount'] ?? $this->sdgAmount($paymentOrder));

        if ($cif === '' || $mobile === '' || $ref === '') {
            // Lost the request context (e.g. expired session) - restart.
            return $this->renderAccountStep($paymentOrder, error: __('bok.error.generic'));
        }

        if ($otp === '') {
            return $this->renderOtpStep($paymentOrder, error: __('bok.error.invalid_otp'));
        }

        $result = $this->getBokService()->processPayment(
            cif: $cif,
            mobile: $mobile,
            otp: $otp,
            amount: $amount,
            paymentRefNo: $ref,
            description: $this->description($paymentOrder),
        );

        if ($result->success) {
            return $this->completePayment($paymentOrder, $result->trnxId, $ref);
        }

        // Ambiguous outcome (timeout / duplicate): reconcile before failing so we
        // never double-charge or drop a confirmed payment.
        if ($result->isAmbiguous()) {
            $status = $this->getBokService()->getStatus($ref);

            if ($status->success) {
                return $this->completePayment($paymentOrder, $status->trnxId ?? $result->trnxId, $ref);
            }

            return $this->renderOtpStep($paymentOrder, error: __('bok.error.unconfirmed'));
        }

        return $this->renderOtpStep($paymentOrder, error: __(BokResult::messageKey('process', $result->code)));
    }

    /**
     * Mark the order paid (idempotent) and redirect to the success page.
     */
    protected function completePayment(PaymentOrder $paymentOrder, ?string $trnxId, string $ref)
    {
        if ($trnxId) {
            $paymentOrder->update(['external_transaction_id' => $trnxId]);
        }

        app(PaymentGatewayService::class)->handlePaymentSuccess($paymentOrder, [
            'transaction_id' => $trnxId,
            'bok_ref' => $ref,
            'status' => 'completed',
        ]);

        return redirect()->away($this->getSuccessUrl($paymentOrder));
    }

    public function handleCallback(array $callbackData): CallbackResponse
    {
        $orderCode = $callbackData['order_code'] ?? null;

        if (! $orderCode) {
            return CallbackResponse::failure('unknown', __('bok.error.generic'));
        }

        $paymentOrder = PaymentOrder::where('order_code', $orderCode)->first();
        $ref = $callbackData['paymentRefNo'] ?? ($paymentOrder->payment_data['bok_ref'] ?? null);

        if ($paymentOrder && $ref) {
            $status = $this->getBokService()->getStatus((string) $ref);

            if ($status->success) {
                return CallbackResponse::success(
                    orderCode: $orderCode,
                    transactionId: $status->trnxId,
                    message: 'Bank of Khartoum payment confirmed'
                );
            }
        }

        return CallbackResponse::failure($orderCode, __('bok.error.generic'));
    }

    public function refund(PaymentOrder $paymentOrder): RefundResponse
    {
        return RefundResponse::failure(
            orderCode: $paymentOrder->order_code,
            message: 'Refunds are not supported for Bank of Khartoum payments. Please process refunds manually.'
        );
    }

    public function getBokService(): BokService
    {
        return new BokService(
            mCode: (string) $this->paymentMethod->getSetting('mCode', ''),
            mPassword: (string) $this->paymentMethod->getSetting('mPassword', ''),
            mSecretKey: (string) $this->paymentMethod->getSetting('mSecretKey', ''),
            applicationId: (string) $this->paymentMethod->getSetting('application_id', 'MBOK'),
            baseUrl: (string) $this->paymentMethod->getSetting('base_url', 'https://paymentgateway.bok-sd.com'),
            port: (string) $this->paymentMethod->getSetting('port', '443'),
            timeout: (int) $this->paymentMethod->getSetting('timeout', 30),
        );
    }

    protected function renderAccountStep(PaymentOrder $paymentOrder, ?string $error = null, string $cif = '', string $mobile = '')
    {
        return view('payment-gateway::plugins.bok-otp-account', [
            'paymentOrder' => $paymentOrder,
            'paymentMethod' => $this->paymentMethod,
            'interactUrl' => $this->getInteractUrl($paymentOrder),
            'instructions' => $this->paymentMethod->getSetting('instructions', ''),
            'error' => $error,
            'cif' => $cif,
            'mobile' => $mobile,
        ]);
    }

    protected function renderOtpStep(PaymentOrder $paymentOrder, ?string $error = null, ?string $info = null)
    {
        return view('payment-gateway::plugins.bok-otp-verify', [
            'paymentOrder' => $paymentOrder,
            'paymentMethod' => $this->paymentMethod,
            'interactUrl' => $this->getInteractUrl($paymentOrder),
            'error' => $error,
            'info' => $info,
        ]);
    }

    protected function currentAttempt(PaymentOrder $paymentOrder): int
    {
        return (int) ($paymentOrder->payment_data['bok_attempt'] ?? 0);
    }

    /**
     * Unique numeric reference per attempt; reversible to the order id.
     */
    protected function makeRef(PaymentOrder $paymentOrder, int $attempt): string
    {
        return (string) ($paymentOrder->id * 1000 + $attempt);
    }

    protected function persist(PaymentOrder $paymentOrder, array $data): void
    {
        $paymentOrder->update([
            'payment_data' => array_merge($paymentOrder->payment_data ?? [], $data),
        ]);
    }

    /**
     * Convert the order amount to SDG (what the Bankak account is debited in).
     */
    protected function sdgAmount(PaymentOrder $paymentOrder): string
    {
        // Bank of Khartoum settles in SDG; convert the base-currency order total.
        $amount = (float) $this->convertAmount($paymentOrder, 'SDG');

        // number_format always emits 2 decimals, so trim trailing zeros/point:
        // 1200.00 -> "1200", 12.50 -> "12.5".
        return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
    }

    protected function description(PaymentOrder $paymentOrder): string
    {
        $description = $paymentOrder->getLocalizedDescription() ?: $paymentOrder->order_code;

        return mb_substr((string) $description, 0, 100);
    }
}
