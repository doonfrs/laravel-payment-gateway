<?php

namespace Trinavo\PaymentGateway\Contracts;

/**
 * Converts an amount between currencies.
 *
 * Plugins that must charge in a currency different from the order currency
 * resolve this from the container. Hosts bind their own implementation via
 * `config('payment-gateway.currency_converter')` (same convention as
 * AmountFormatter / InboundBillingHandler). The default NullCurrencyConverter
 * returns the amount unchanged, so order flows are unaffected until a host
 * opts in. The gateway speaks only "amount + currency codes"; how the rate is
 * resolved is entirely the host's concern.
 */
interface CurrencyConverter
{
    /**
     * Convert $amount from currency $from into currency $to, returning the
     * converted amount as a string ready for the wire.
     */
    public function convert(float $amount, string $from, string $to): string;
}
