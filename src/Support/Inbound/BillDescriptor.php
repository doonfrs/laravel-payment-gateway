<?php

namespace Trinavo\PaymentGateway\Support\Inbound;

/**
 * The host's answer to an InboundBillContext: what the gateway should tell the
 * billing network about a bill it does not own.
 *
 * Pure billing vocabulary — the gateway never learns what the host did to
 * produce these values.
 */
final class BillDescriptor
{
    public function __construct(
        public readonly string $customerName,
        /** Amount owed/expected as a string (the gateway formats it for the wire). */
        public readonly string $amount,
        /** When true, the payer may submit any amount (gateway emits an open PmtConst range). */
        public readonly bool $allowOverpayment = false,
        /** Optional free-text note shown to the payer. */
        public readonly ?string $note = null,
    ) {}
}
