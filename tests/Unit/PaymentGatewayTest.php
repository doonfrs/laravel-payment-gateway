<?php

namespace Trinavo\MultiTenancy\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Trinavo\PaymentGateway\Providers\PaymentGatewayServiceProvider;

class PaymentGatewayTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            PaymentGatewayServiceProvider::class,
        ];
    }

    public function test_the_test()
    {
        $this->assertTrue(true);
    }
}
