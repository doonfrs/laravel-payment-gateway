<?php

namespace Trinavo\PaymentGateway\Support\Inbound;

use Trinavo\PaymentGateway\Models\PaymentMethod;
use Trinavo\PaymentGateway\Models\PaymentOrder;

/**
 * Describes an inbound payment for a reference the gateway does not own.
 *
 * The gateway has already created a pending PaymentOrder as its audit +
 * idempotency record (keyed on `providerTransactionId`) and hands it to the
 * host's InboundBillingHandler::handlePayment(). The host claims the payment
 * (returns true) and may reference `paymentOrder->id` on whatever record it
 * creates; the gateway then completes the PaymentOrder. Returning false leaves
 * the reference unrecognised and the gateway responds not-found.
 */
final class InboundPaymentContext
{
    public function __construct(
        public readonly PaymentMethod $paymentMethod,
        public readonly string $reference,
        public readonly string $providerTransactionId,
        public readonly string $amount,
        public readonly PaymentOrder $paymentOrder,
        /** Raw provider payload, for the host's own audit if needed. */
        public readonly array $raw = [],
    ) {}
}
