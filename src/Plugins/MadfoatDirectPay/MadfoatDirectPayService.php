<?php

namespace Trinavo\PaymentGateway\Plugins\MadfoatDirectPay;

/**
 * Pure helpers for the Madfoat DirectPay (eFAWATEERcom) protocol.
 *
 * No Eloquent, no facades, no HTTP. Everything here is fully unit-testable
 * with deterministic vectors. The plugin class wires this into the gateway
 * lifecycle.
 *
 * Protocol reference: docs/madfoat/DirectPay-for-Billers-Integration_V2.3.md
 */
class MadfoatDirectPayService
{
    public const STAGING_URL = 'https://staging.efawateercom.jo/DirectPayService/DirectPay.aspx';

    public const PRODUCTION_URL = 'https://www.efawateercom.jo/DirectPayService/DirectPay.aspx';

    public const PAYMENT_TYPE_POSTPAID = 1;

    public const PAYMENT_TYPE_PREPAID = 2;

    public const TRX_STATUS_SUCCESS = 1;

    public const PAYMENT_STATUS_SUCCESS = 1;

    public const PAYMENT_STATUS_UNDER_PROCESSING = 2;

    public const PAYMENT_STATUS_FAILED = 3;

    /**
     * Ordered list of request param names (positions 1-13 from spec §2.1.1).
     */
    public const REQUEST_PARAM_ORDER = [
        'BilrTrxNo',
        'BillerCode',
        'ServiceCode',
        'PaymentType',
        'Currency',
        'BillingNo',
        'PrepaidCatCode',
        'Amount',
        'StatmntNartive',
        'CustEmail',
        'Language',
        'OtherDetails',
    ];

    /**
     * Ordered list of response param names BEFORE the trailing SecureHash
     * (positions 1-6 from spec §2.1.2). Some responses are truncated - see
     * parseResponseParams() for handling.
     */
    public const RESPONSE_PARAM_ORDER = [
        'BilrTrxNo',
        'TrxStatus',
        'DirectPayTrxNo',
        'Amount',
        'PaymentStatus',
        'OtherDetails',
    ];

    /**
     * Characters DirectPay rejects in any parameter value (spec §2.2).
     */
    public const FORBIDDEN_CHARS = ['~', '"', "'", '&', '#', '%'];

    /**
     * Resolve the gateway endpoint for the active mode.
     */
    public function getGatewayUrl(bool $testMode): string
    {
        return $testMode ? self::STAGING_URL : self::PRODUCTION_URL;
    }

    /**
     * Build the pipe-separated RequestParams value (including the trailing
     * SecureHash), ready to be put in a form field or query string.
     *
     * Caller passes the 12 protocol fields by name; we order them per spec,
     * sanitise each, append the BCrypt hash, and return.
     */
    public function buildRequestParams(array $params, string $secret): string
    {
        $orderedValues = [];

        foreach (self::REQUEST_PARAM_ORDER as $name) {
            $value = $params[$name] ?? '';
            $orderedValues[] = $this->sanitize((string) $value);
        }

        $paramsString = implode('|', $orderedValues);
        $hash = $this->signRequestParams($paramsString, $secret);

        return $paramsString.'|'.$hash;
    }

    /**
     * Build the full redirect URL (gateway endpoint + ?RequestParams=...).
     */
    public function buildRequestUrl(array $params, string $secret, bool $testMode): string
    {
        $requestParams = $this->buildRequestParams($params, $secret);

        // RequestParams contains pipe characters and a bcrypt hash with $
        // and / - encode it as a single query value so the gateway sees it
        // intact. The gateway example shows it unencoded, but raw query
        // values must be URL-safe to survive any intermediate hop.
        return $this->getGatewayUrl($testMode).'?RequestParams='.rawurlencode($requestParams);
    }

    /**
     * BCrypt-sign the request parameters string with the shared secret appended
     * (per spec §2.1.1.3).
     */
    public function signRequestParams(string $paramsWithoutHash, string $secret): string
    {
        return password_hash($paramsWithoutHash.$secret, PASSWORD_BCRYPT);
    }

    /**
     * Verify the response SecureHash. eFAWATEERcom hashes the response payload
     * (without the trailing SecureHash) plus the secret, using BCrypt.
     *
     * password_verify is constant-time and accepts both $2a$ and $2y$ prefixes.
     */
    public function verifyResponseSignature(string $responseParamsWithoutHash, string $providedHash, string $secret): bool
    {
        if ($providedHash === '' || $secret === '') {
            return false;
        }

        return password_verify($responseParamsWithoutHash.$secret, $providedHash);
    }

    /**
     * Strip the six forbidden characters from a parameter value (spec §2.2).
     *
     * We strip rather than throw so a customer with an apostrophe in their
     * name doesn't get a 500 - DirectPay would reject the request anyway,
     * and the protocol doesn't define a way to flag it back to the user.
     */
    public function sanitize(string $value): string
    {
        return str_replace(self::FORBIDDEN_CHARS, '', $value);
    }

    /**
     * Parse a raw ResponseParams string into a keyed array.
     *
     * Returns an array shaped like:
     *   [
     *     'BilrTrxNo' => string|null,
     *     'TrxStatus' => string|null,
     *     'DirectPayTrxNo' => string|null,
     *     'Amount' => string|null,
     *     'PaymentStatus' => string|null,
     *     'OtherDetails' => string|null,
     *     'SecureHash' => string|null,
     *     '_paramsWithoutHash' => string,  // exact bytes the SecureHash signs
     *   ]
     *
     * The last pipe-separated element is treated as SecureHash regardless of
     * how many fields precede it - spec samples show success responses with
     * 6 elements and failure responses with 3, but in both cases SecureHash
     * is last.
     */
    public function parseResponseParams(string $rawParams): array
    {
        $rawParams = trim($rawParams);

        if ($rawParams === '') {
            return $this->emptyParsedResponse();
        }

        $parts = explode('|', $rawParams);

        if (count($parts) < 2) {
            // Can't be a valid signed response.
            return $this->emptyParsedResponse();
        }

        $hash = array_pop($parts);
        $paramsWithoutHash = implode('|', $parts);

        $parsed = $this->emptyParsedResponse();
        $parsed['SecureHash'] = $hash;
        $parsed['_paramsWithoutHash'] = $paramsWithoutHash;

        foreach (self::RESPONSE_PARAM_ORDER as $index => $name) {
            if (! array_key_exists($index, $parts)) {
                break;
            }

            $value = $parts[$index];
            $parsed[$name] = $value === '' ? null : $value;
        }

        return $parsed;
    }

    /**
     * Translatable human-readable message for a TrxStatus error code.
     * Falls back to the English label if no translation exists.
     */
    public function getStatusCodeMessage(int $code): string
    {
        $messages = [
            1 => 'Success',
            2 => 'Wrong Biller Transaction No',
            3 => 'Wrong Biller Code',
            4 => 'Wrong Service Code',
            5 => 'Wrong Prepaid Category Code',
            6 => 'Wrong Amount',
            7 => 'Wrong Customer Email',
            8 => 'Wrong Call Back URL',
            9 => 'Parsing Error',
            10 => 'Internal Error',
            11 => 'Unable to process your payment, please try again later',
            12 => 'Payment transaction is canceled by customer',
            13 => 'Insufficient balance',
            14 => 'No registered mobile number, please refer to your bank to register your mobile number',
            15 => 'Unable to process your payment, please get back to your bank',
            16 => 'The entered OTP is incorrect',
            17 => 'The entered OTP is expired',
            18 => 'You reached the maximum limit to request a new OTP',
            19 => 'Bank is not available',
            20 => 'Invalid Token',
        ];

        return $messages[$code] ?? 'Unknown DirectPay error code: '.$code;
    }

    /**
     * Format a decimal amount per spec (18,3 with dot decimal separator).
     */
    public function formatAmount(float $amount): string
    {
        return number_format($amount, 3, '.', '');
    }

    private function emptyParsedResponse(): array
    {
        return [
            'BilrTrxNo' => null,
            'TrxStatus' => null,
            'DirectPayTrxNo' => null,
            'Amount' => null,
            'PaymentStatus' => null,
            'OtherDetails' => null,
            'SecureHash' => null,
            '_paramsWithoutHash' => '',
        ];
    }
}
