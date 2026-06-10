<?php

namespace Trinavo\PaymentGateway\Events;

use Trinavo\PaymentGateway\Events\Dto\PrepaidCustomerIdentity;
use Trinavo\PaymentGateway\Models\PaymentMethod;

/**
 * Fired synchronously when an inbound prepaid validation request arrives and
 * the host needs to confirm the customer identifier exists.
 *
 * Listeners set `$identity` to a PrepaidCustomerIdentity instance when the
 * BillingNo resolves to a real customer. Null after dispatch means the
 * plugin treats the identifier as unknown and rejects the request to the
 * payment provider.
 *
 * Plugins from any payment provider can dispatch this; host listeners stay
 * provider-agnostic by reading only `billingNo` + `paymentMethod`.
 */
class PrepaidPaymentValidationRequested
{
    public ?PrepaidCustomerIdentity $identity = null;

    public function __construct(
        public readonly PaymentMethod $paymentMethod,
        public readonly string $billingNo,
        public readonly string $dueAmount,
    ) {}
}
