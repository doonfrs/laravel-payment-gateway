<?php

namespace Trinavo\PaymentGateway\Contracts;

interface AmountFormatter
{
    public function format(float|int|string $amount, string $currency): string;
}
