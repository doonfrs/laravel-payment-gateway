<?php

namespace Trinavo\PaymentGateway\Contracts;

use Trinavo\PaymentGateway\Support\Inbound\BillDescriptor;
use Trinavo\PaymentGateway\Support\Inbound\InboundBillContext;
use Trinavo\PaymentGateway\Support\Inbound\InboundPaymentContext;

/**
 * Extension point for inbound (provider-initiated) bills and payments whose
 * reference the gateway cannot map to one of its own PaymentOrders.
 *
 * Hosts override the binding via `config('payment-gateway.inbound_billing_handler')`
 * (same convention as AmountFormatter). The default NullInboundBillingHandler
 * declines everything, so order flows are unaffected until a host opts in.
 *
 * The gateway speaks only billing vocabulary here; what the reference maps to
 * (a customer, a wallet, anything) is entirely the host's concern.
 */
interface InboundBillingHandler
{
    /**
     * Describe a bill for a reference the gateway does not own. Return null
     * when the reference is unknown (gateway responds with "invalid bill").
     */
    public function describeBill(InboundBillContext $context): ?BillDescriptor;

    /**
     * Handle an inbound payment for a reference the gateway does not own.
     * Return true to claim it (gateway acks success and completes the audit
     * PaymentOrder), false to decline (gateway responds not-found).
     */
    public function handlePayment(InboundPaymentContext $context): bool;
}
