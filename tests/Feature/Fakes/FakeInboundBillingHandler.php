<?php

namespace Trinavo\PaymentGateway\Tests\Feature\Fakes;

use Trinavo\PaymentGateway\Contracts\InboundBillingHandler;
use Trinavo\PaymentGateway\Support\Inbound\BillDescriptor;
use Trinavo\PaymentGateway\Support\Inbound\InboundBillContext;
use Trinavo\PaymentGateway\Support\Inbound\InboundPaymentContext;

/**
 * Test double standing in for a host InboundBillingHandler. Records calls and
 * returns canned answers.
 */
class FakeInboundBillingHandler implements InboundBillingHandler
{
    public ?BillDescriptor $describe = null;

    public bool $handle = false;

    public ?InboundBillContext $lastBillContext = null;

    /** @var list<InboundPaymentContext> */
    public array $payments = [];

    public function describeBill(InboundBillContext $context): ?BillDescriptor
    {
        $this->lastBillContext = $context;

        return $this->describe;
    }

    public function handlePayment(InboundPaymentContext $context): bool
    {
        if ($this->handle) {
            $this->payments[] = $context;
        }

        return $this->handle;
    }
}
