<?php

namespace Trinavo\PaymentGateway\Support;

use Trinavo\PaymentGateway\Contracts\InboundBillingHandler;
use Trinavo\PaymentGateway\Support\Inbound\BillDescriptor;
use Trinavo\PaymentGateway\Support\Inbound\InboundBillContext;
use Trinavo\PaymentGateway\Support\Inbound\InboundPaymentContext;

/**
 * Default no-op handler: declines every inbound bill/payment so the gateway
 * keeps its original "invalid bill" / "order not found" behaviour. Hosts that
 * want to claim inbound references bind their own implementation via
 * `config('payment-gateway.inbound_billing_handler')`.
 */
class NullInboundBillingHandler implements InboundBillingHandler
{
    public function describeBill(InboundBillContext $context): ?BillDescriptor
    {
        return null;
    }

    public function handlePayment(InboundPaymentContext $context): bool
    {
        return false;
    }
}
