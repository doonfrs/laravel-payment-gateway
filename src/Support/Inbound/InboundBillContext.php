<?php

namespace Trinavo\PaymentGateway\Support\Inbound;

use Trinavo\PaymentGateway\Models\PaymentMethod;

/**
 * Describes an inbound bill inquiry for a reference the gateway does not own
 * (e.g. an eFAWATEERcom bill-pull or prepaid-validation whose BillingNo is not
 * one of the gateway's PaymentOrders).
 *
 * The host's InboundBillingHandler::describeBill() inspects `reference` and
 * returns a BillDescriptor, or null when the reference is unknown.
 */
final class InboundBillContext
{
    public function __construct(
        public readonly PaymentMethod $paymentMethod,
        public readonly string $reference,
        /**
         * The customer-entered amount when the channel already carries one
         * (e.g. prepaid validation), or null when the gateway must supply the
         * amount itself (e.g. bill-pull).
         */
        public readonly ?string $proposedAmount = null,
    ) {}
}
