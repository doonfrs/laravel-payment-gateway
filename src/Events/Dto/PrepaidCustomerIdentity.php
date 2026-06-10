<?php

namespace Trinavo\PaymentGateway\Events\Dto;

/**
 * Typed identity returned by a PrepaidPaymentValidationRequested listener.
 *
 * Provider-agnostic contract between plugins (which know nothing about the
 * host's User model) and host listeners (which resolve a BillingNo to a real
 * customer). Carried verbatim into the PaymentOrder's `customer_data` for
 * persistence and into PrepaidPaymentReceived for the credit step.
 */
final class PrepaidCustomerIdentity
{
    /**
     * @param  int|string  $userId  Host's primary identifier (int for sequential, string for UUID).
     * @param  string  $userIdentifier  The same value the bank sent as BillingNo (echoed back for audit).
     * @param  string  $name  Display name returned to the bank's confirmation screen.
     * @param  array<string, mixed>  $meta  Free-form provider/host-specific extras; persisted on the PaymentOrder.
     */
    public function __construct(
        public readonly int|string $userId,
        public readonly string $userIdentifier,
        public readonly string $name,
        public readonly array $meta = [],
    ) {}
}
