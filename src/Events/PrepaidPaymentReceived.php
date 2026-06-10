<?php

namespace Trinavo\PaymentGateway\Events;

use Trinavo\PaymentGateway\Events\Dto\PrepaidCustomerIdentity;
use Trinavo\PaymentGateway\Models\PaymentMethod;
use Trinavo\PaymentGateway\Models\PaymentOrder;

/**
 * Fired after a prepaid payment notification has been received from the
 * payment provider and a synthetic PaymentOrder has been recorded as
 * completed. Host listeners credit the customer (e.g. wallet top-up) using
 * the typed `$identity` (no array fishing required).
 *
 * Idempotency is guaranteed by the plugin: it skips dispatch when a
 * PaymentOrder already exists for the same provider transaction id, so
 * listeners can credit unconditionally.
 */
class PrepaidPaymentReceived
{
    public function __construct(
        public readonly PaymentOrder $paymentOrder,
        public readonly PaymentMethod $paymentMethod,
        public readonly PrepaidCustomerIdentity $identity,
        public readonly string $providerTransactionId,
    ) {}
}
