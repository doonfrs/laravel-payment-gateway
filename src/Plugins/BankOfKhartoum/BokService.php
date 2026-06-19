<?php

namespace Trinavo\PaymentGateway\Plugins\BankOfKhartoum;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Outbound client for the Bank of Khartoum Payment Gateway API.
 *
 * Pure transport: builds the JSON envelopes, POSTs over the VPN, and maps the
 * bank's `statusCode` into a BokResult. Knows nothing about orders or the UI.
 * Network/timeout failures return BokResult::timeout() (never throw) so callers
 * can reconcile via getStatus() instead of wrongly failing a payment.
 */
class BokService
{
    public function __construct(
        protected string $mCode,
        protected string $mPassword,
        protected string $mSecretKey,
        protected string $applicationId,
        protected string $baseUrl,
        protected string $port = '443',
        protected int $timeout = 30,
    ) {}

    /**
     * Health check. Should be called before submitting a payment request.
     */
    public function isLive(): BokResult
    {
        return $this->post('System/isLive', [
            'ApplicationId' => $this->applicationId,
        ]);
    }

    /**
     * Authenticate the customer account and trigger an OTP (sent by BOK via SMS).
     */
    public function requestOtp(string $cif, string $mobile, string $amount, string $paymentRefNo): BokResult
    {
        return $this->post('merchantPayment/paymentOtpRequest', $this->merchantFields([
            'reqType' => 'paymentOtpRequest',
            'mobile' => $mobile,
            'cif' => $cif,
            'amount' => $amount,
            'paymentRefNo' => $paymentRefNo,
        ]));
    }

    /**
     * Validate the OTP, debit the customer's Bankak account, credit the merchant.
     * On success the result carries the core (IMAL) trnxId.
     */
    public function processPayment(
        string $cif,
        string $mobile,
        string $otp,
        string $amount,
        string $paymentRefNo,
        string $description = ''
    ): BokResult {
        return $this->post('merchantPayment/paymentProcess', $this->merchantFields([
            'reqType' => 'paymentProcess',
            'mobile' => $mobile,
            'cif' => $cif,
            'amount' => $amount,
            'paymentRefNo' => $paymentRefNo,
            'otp' => $otp,
            'paymentDescription' => $description,
        ]));
    }

    /**
     * Enquire a transaction's status by the payment reference number.
     */
    public function getStatus(string $paymentRefNo): BokResult
    {
        return $this->post('merchantPayment/GetStatus', $this->merchantFields([
            'reqType' => 'getStatus',
            'paymentRefNo' => $paymentRefNo,
        ]));
    }

    /**
     * Merge the merchant credentials into a request body.
     */
    protected function merchantFields(array $fields): array
    {
        return array_merge($fields, [
            'mCode' => $this->mCode,
            'mPassword' => $this->mPassword,
            'mSecretKey' => $this->mSecretKey,
        ]);
    }

    /**
     * Build the full endpoint URL: {baseUrl}:{port}/mfmbs/paymentgateway/{path}.
     */
    public function endpoint(string $path): string
    {
        $host = rtrim($this->baseUrl, '/');

        if ($this->port !== '' && ! preg_match('/:\d+$/', $host)) {
            $host .= ':'.$this->port;
        }

        return $host.'/mfmbs/paymentgateway/'.ltrim($path, '/');
    }

    protected function post(string $path, array $body): BokResult
    {
        $url = $this->endpoint($path);

        $this->log('request', $path, $this->mask($body));

        try {
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->asJson()
                ->post($url, $body);
        } catch (\Throwable $e) {
            report($e);
            $this->log('transport-error', $path, ['error' => $e->getMessage()]);

            return BokResult::timeout($e->getMessage());
        }

        $json = $response->json();

        if (! is_array($json)) {
            $this->log('non-json-response', $path, ['status' => $response->status()]);

            return BokResult::timeout('non-json response');
        }

        $this->log('response', $path, $json);

        return BokResult::fromResponse($json);
    }

    /**
     * Redact secrets/OTP before logging.
     */
    protected function mask(array $body): array
    {
        foreach (['mPassword', 'mSecretKey', 'otp'] as $key) {
            if (isset($body[$key])) {
                $body[$key] = '***';
            }
        }

        return $body;
    }

    protected function log(string $event, string $path, array $context = []): void
    {
        Log::info("BOK: {$event} {$path}", $context);
    }
}
