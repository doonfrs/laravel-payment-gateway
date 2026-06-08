<?php

namespace Trinavo\PaymentGateway\Plugins\Madfoat\Concerns;

use Trinavo\PaymentGateway\Models\PaymentGatewayInboundRequest;

/**
 * Shared inbound-transport plumbing for Madfoat plugins.
 *
 * Madfoat (eFAWATEERcom) reaches us over a fixed IPsec VPN with a small set of
 * allow-listed IPs and optional HTTP Basic Auth. Both the bill-payment plugin
 * (bill-pull / payment-notification) and the DirectPay plugin (biller-notification)
 * share the same allow-list shape, the same Basic Auth settings, and the same
 * inbound-audit row attachment logic. This trait centralises those three concerns
 * so the two plugins can't drift.
 *
 * Plugins using this trait MUST expose `$paymentMethod` (a PaymentMethod instance
 * with allowed_ips / auth_username / auth_password settings).
 */
trait MadfoatTransportTrait
{
    /**
     * Check whether the given IP is allowed to make inbound requests for this plugin.
     */
    protected function isIpAllowed(string $ip): bool
    {
        $allowedIps = $this->paymentMethod->getSetting('allowed_ips', '');

        if (empty($allowedIps)) {
            return true;
        }

        $allowed = array_map('trim', explode(',', $allowedIps));

        return in_array($ip, $allowed, true);
    }

    /**
     * Validate HTTP Basic Authentication credentials against plugin settings.
     *
     * Returns true when no credentials are configured (backward-compatible
     * opt-in behavior).
     */
    protected function isBasicAuthValid(): bool
    {
        $expectedUsername = $this->paymentMethod->getSetting('auth_username', '');
        $expectedPassword = $this->paymentMethod->getSetting('auth_password', '');

        if (empty($expectedUsername) && empty($expectedPassword)) {
            return true;
        }

        $providedUsername = request()->getUser();
        $providedPassword = request()->getPassword();

        return $providedUsername === $expectedUsername
            && $providedPassword === $expectedPassword;
    }

    /**
     * Attach the resolved PaymentOrder id to the current inbound-request audit row,
     * if the controller already created one.
     */
    protected function attachInboundRequestToPaymentOrder(?int $paymentOrderId): void
    {
        if (! $paymentOrderId) {
            return;
        }

        $record = request()->attributes->get('inbound_request_record');

        if ($record instanceof PaymentGatewayInboundRequest) {
            $record->update(['payment_order_id' => $paymentOrderId]);
        }
    }
}
