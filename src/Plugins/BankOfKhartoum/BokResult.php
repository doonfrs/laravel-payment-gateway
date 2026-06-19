<?php

namespace Trinavo\PaymentGateway\Plugins\BankOfKhartoum;

/**
 * Immutable result of a Bank of Khartoum API call.
 *
 * Wraps BOK's `statusCode` and message so callers never branch on raw bank
 * strings. `code` is BOK's statusCode (e.g. '00', '01', ...) or the sentinel
 * 'timeout' when the call could not be completed.
 */
final class BokResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $code,
        public readonly string $message = '',
        public readonly ?string $trnxId = null,
        public readonly array $raw = [],
    ) {}

    public static function fromResponse(array $json): self
    {
        $code = (string) ($json['statusCode'] ?? '');

        return new self(
            success: $code === '00',
            code: $code,
            message: (string) ($json['message'] ?? $json['errorMessage'] ?? ''),
            trnxId: isset($json['trnxId']) && $json['trnxId'] !== null ? (string) $json['trnxId'] : null,
            raw: $json,
        );
    }

    public static function timeout(string $message = 'timeout'): self
    {
        return new self(success: false, code: 'timeout', message: $message);
    }

    /**
     * True when the outcome is unknown (could not be completed or a duplicate
     * reference), so the caller should reconcile via GetStatus rather than
     * treat the payment as failed.
     */
    public function isAmbiguous(): bool
    {
        return $this->code === 'timeout' || $this->code === '02';
    }

    /**
     * Map a (step, statusCode) pair to a user-facing translation key. Single
     * source of truth for BOK status handling - never surface raw bank text.
     *
     * @param  string  $step  one of: is_live, otp_request, process
     */
    public static function messageKey(string $step, string $code): string
    {
        return match ($step) {
            'is_live' => $code === '00' ? 'bok.code_sent' : 'bok.unavailable',
            'otp_request' => match ($code) {
                '02' => 'bok.error.no_account',
                '03' => 'bok.error.enable_ecommerce',
                default => 'bok.error.generic',
            },
            'process' => match ($code) {
                '01' => 'bok.error.invalid_otp',
                '03' => 'bok.error.expired_otp',
                '04' => 'bok.error.insufficient_balance',
                default => 'bok.error.generic',
            },
            default => 'bok.error.generic',
        };
    }
}
