<?php

namespace Trinavo\PaymentGateway\Support;

use Trinavo\PaymentGateway\Contracts\CurrencyConverter;

/**
 * Default no-op converter: returns the amount unchanged. Hosts that need real
 * conversion bind their own implementation via
 * `config('payment-gateway.currency_converter')`.
 */
class NullCurrencyConverter implements CurrencyConverter
{
    public function convert(float $amount, string $from, string $to): string
    {
        return (string) $amount;
    }
}
