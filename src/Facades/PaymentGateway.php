<?php

namespace Trinavo\PaymentGateway\Facades;

use Illuminate\Support\Facades\Facade;
use Trinavo\PaymentGateway\Services\PaymentGatewayService;

class PaymentGateway extends Facade
{
    protected static function getFacadeAccessor()
    {
        return PaymentGatewayService::class;
    }
}
