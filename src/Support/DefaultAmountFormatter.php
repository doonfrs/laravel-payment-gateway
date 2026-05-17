<?php

namespace Trinavo\PaymentGateway\Support;

use Trinavo\PaymentGateway\Contracts\AmountFormatter;

class DefaultAmountFormatter implements AmountFormatter
{
    public function format(float|int|string $amount, string $currency): string
    {
        return number_format((float) $amount, 2).' '.$currency;
    }
}
