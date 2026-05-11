<?php

namespace Trinavo\PaymentGateway\Plugins\Madfoat;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Trinavo\PaymentGateway\Configuration\CheckboxField;
use Trinavo\PaymentGateway\Configuration\PasswordField;
use Trinavo\PaymentGateway\Configuration\TextField;
use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\CallbackResponse;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Models\RefundResponse;

class MadfoatPaymentPlugin extends PaymentPluginInterface
{
    public static function getLogoUrl(): string
    {
        return asset('vendor/payment-gateway/imgs/madfoat.png');
    }

    public static function getMoreInfoUrl(): string
    {
        return 'https://www.madfoo3at.com';
    }

    public static function getSupportedCountries(): array
    {
        return ['JO'];
    }

    public function getName(): string
    {
        return 'Madfoat (eFAWATEERcom)';
    }

    public function getDescription(): string
    {
        return 'Bill payment via eFAWATEERcom. Customers pay through their bank app using a bill number.';
    }

    public function getConfigurationFields(): array
    {
        return [
            new TextField(
                name: 'sdr_code_test',
                label: 'SdrCode (Test)',
                required: true,
                description: 'Your SdrCode (biller code) assigned by Madfoat/eFAWATEERcom for the test/sandbox environment',
                placeholder: 'e.g. 12345',
            ),
            new TextField(
                name: 'sdr_code_production',
                label: 'SdrCode (Production)',
                required: false,
                description: 'Your SdrCode (biller code) assigned by Madfoat/eFAWATEERcom for the production environment',
                placeholder: 'e.g. 12345',
            ),
            new TextField(
                name: 'service_type',
                label: 'Service Type',
                required: true,
                description: 'Service type code registered with eFAWATEERcom',
                placeholder: 'e.g. Sales',
            ),
            new TextField(
                name: 'bill_expiry_days',
                label: 'Bill Expiry Days',
                required: false,
                default: '7',
                description: 'Number of days before a bill expires',
                placeholder: '7',
            ),
            new TextField(
                name: 'instructions',
                label: 'Customer Instructions',
                required: false,
                default: 'Please pay your bill through your bank app via eFAWATEERcom. Search for our business name and enter your bill number.',
                description: 'Instructions shown to the customer after placing the order',
                maxLength: 500,
            ),
            new TextField(
                name: 'allowed_ips',
                label: 'Allowed IPs',
                required: false,
                default: '',
                description: 'Comma-separated list of allowed IPs for inbound requests. Leave empty to allow all (for testing).',
                placeholder: '10.211.211.249,10.211.211.241',
            ),
            new TextField(
                name: 'auth_username',
                label: 'Basic Auth Username',
                required: false,
                default: '',
                description: 'Username for HTTP Basic Authentication on inbound requests from Madfoat. Leave empty to disable Basic Auth.',
                placeholder: 'e.g. madfoat_user',
            ),
            new PasswordField(
                name: 'auth_password',
                label: 'Basic Auth Password',
                required: false,
                description: 'Password for HTTP Basic Authentication on inbound requests from Madfoat.',
            ),
            new CheckboxField(
                name: 'test_mode',
                label: 'Test Mode',
                default: true,
                description: 'Enable test/sandbox mode for Madfoat (eFAWATEERcom).'
            ),
        ];
    }

    public function validateConfiguration(): bool
    {
        if (empty($this->paymentMethod->getSetting('service_type'))) {
            return false;
        }

        return ! empty($this->getSdrCode());
    }

    public function processPayment(PaymentOrder $paymentOrder)
    {
        $service = $this->getMadfoatService();
        $orderId = $paymentOrder->customer_data['order_id'] ?? null;
        $billingNumber = $orderId ? $service->generateBillingNumber((int) $orderId) : $paymentOrder->order_code;

        $service->log('Bill displayed to customer', [
            'payment_order_id' => $paymentOrder->id,
            'order_code' => $paymentOrder->order_code,
            'order_id' => $orderId,
            'billing_number' => $billingNumber,
            'amount' => $paymentOrder->amount,
            'currency' => $paymentOrder->currency,
        ]);

        $instructions = $this->paymentMethod->getSetting(
            'instructions',
            'Please pay your bill through your bank app via eFAWATEERcom. Search for our business name and enter your bill number.'
        );

        return view('payment-gateway::plugins.madfoat-pending-payment', [
            'paymentOrder' => $paymentOrder,
            'paymentMethod' => $this->paymentMethod,
            'billingNumber' => $billingNumber,
            'instructions' => $instructions,
            'successUrl' => $this->getSuccessUrl($paymentOrder),
        ]);
    }

    public function handleCallback(array $callbackData): CallbackResponse
    {
        $orderCode = $callbackData['order_code'] ?? null;

        if (! $orderCode) {
            return CallbackResponse::failure(
                orderCode: 'unknown',
                message: 'Order code is required'
            );
        }

        return CallbackResponse::success(
            orderCode: $orderCode,
            transactionId: 'madfoat_'.uniqid(),
            message: 'Madfoat payment confirmed'
        );
    }

    public function refund(PaymentOrder $paymentOrder): RefundResponse
    {
        return RefundResponse::failure(
            orderCode: $paymentOrder->order_code,
            message: 'Refunds are not supported for eFAWATEERcom payments. Please process refunds manually.'
        );
    }

    public function supportsInboundRequests(): bool
    {
        return true;
    }

    public function handleInboundRequest(string $action, array $data): JsonResponse
    {
        $service = $this->getMadfoatService();

        // IP validation
        if (! $this->isIpAllowed(request()->ip())) {
            Log::warning('Madfoat: Unauthorized IP attempt', [
                'ip' => request()->ip(),
                'action' => $action,
            ]);

            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Basic Auth validation
        if (! $this->isBasicAuthValid()) {
            Log::warning('Madfoat: Basic Auth failed', [
                'ip' => request()->ip(),
                'action' => $action,
            ]);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $service->log("Inbound request: {$action}", ['data' => $data]);

        return match ($action) {
            'bill-pull' => $this->handleBillPull($data, $service),
            'payment-notification' => $this->handlePaymentNotification($data, $service),
            'payment-acknowledgment' => $this->handlePaymentAcknowledgment($data, $service),
            'prepaid-validation' => $this->handlePrepaidValidation($data, $service),
            default => response()->json(
                $service->buildErrorResponse('UNKNOWN', $service->extractGuid($data), 99, 'Unknown action: '.$action),
                400
            ),
        };
    }

    /**
     * Handle bill pull request (BILPULRQ → BILPULRS).
     *
     * Reads bill details from the PaymentOrder, not from a host-app Order
     * model. The PaymentOrder already carries amount/customer details set
     * at cart-checkout time, and this plugin doesn't need (or want) to
     * know what host model the order_id refers to.
     */
    protected function handleBillPull(array $data, MadfoatService $service): JsonResponse
    {
        $billingNo = $data['MFEP']['MsgBody']['AcctInfo']['BillingNo'] ?? '';
        $guid = $service->extractGuid($data);

        if (empty($billingNo)) {
            $response = $service->buildBillPullErrorResponse($guid, 1, 'BillingNo is required');
            $service->log('Bill pull error: missing BillingNo', ['response' => $response]);

            return response()->json($response);
        }

        $orderId = $service->parseBillingNumber($billingNo);
        $paymentOrder = $this->findPaymentOrderByAppOrderId($orderId);

        // A CANCELLED PaymentOrder means the customer abandoned this draft and
        // started a new checkout for the same cart (CartService::abandonExistingDraftsForCart
        // flips the status). The bill is stale — reject it so the bank refuses
        // payment and the customer is steered to the current bill.
        if (! $paymentOrder || $paymentOrder->isCancelled()) {
            $billNo = $data['MFEP']['MsgBody']['AcctInfo']['BillNo'] ?? '';
            $response = $service->buildBillPullInvalidBillResponse($guid, $billingNo, $billNo);
            $service->log('Bill pull: invalid billing number', [
                'billing_no' => $billingNo,
                'order_id' => $orderId,
                'payment_order_status' => $paymentOrder?->status,
            ]);

            return response()->json($response);
        }

        $orderData = [
            'final_total' => $paymentOrder->amount ?? 0,
            'paid' => $paymentOrder->isCompleted(),
            'customer_name' => $paymentOrder->customer_name ?? '',
            'customer_email' => $paymentOrder->customer_email ?? '',
            'customer_mobile' => $paymentOrder->customer_phone ?? '',
            'created_at' => $paymentOrder->created_at ?? now(),
        ];

        $response = $service->buildBillPullResponse($data, $paymentOrder, $orderData);
        $service->log('Bill pull success', ['billing_no' => $billingNo, 'response' => $response]);

        $this->attachInboundRequestToPaymentOrder($paymentOrder->id);

        return response()->json($response);
    }

    /**
     * Handle payment notification (BLRPMTNTFRQ → BLRPMTNTFRS).
     *
     * Bank-confirms-payment webhook. The host's order side is updated by
     * delegating to CartService::setOrderAsPaid — which has the
     * withTrashed/restore/log/report logic for the
     * draft-soft-deleted-while-bill-was-pending race. This plugin no
     * longer references App\Models\Order or guesses a model class.
     */
    protected function handlePaymentNotification(array $data, MadfoatService $service): JsonResponse
    {
        $guid = $service->extractGuid($data);
        $billingInfo = $data['MFEP']['MsgBody']['BillingInfo']
            ?? $data['MFEP']['MsgBody']['Transactions']['TrxInf']
            ?? [];
        $billingNo = $billingInfo['AcctInfo']['BillingNo'] ?? '';
        $joebppsTrx = $billingInfo['JOEBPPSTrx'] ?? '';
        $paidAmt = $billingInfo['PaidAmt'] ?? '0.000';
        $processDate = $billingInfo['ProcessDate'] ?? now()->format('Y-m-d\TH:i:s');
        $stmtDate = $billingInfo['StmtDate'] ?? $billingInfo['STMTDate'] ?? now()->format('Y-m-d');

        if (empty($billingNo)) {
            $response = $service->buildPaymentNotificationResponse($guid, $joebppsTrx, $processDate, $stmtDate, 1, 'BillingNo is required', 'Error');
            $service->log('Payment notification error: missing BillingNo', ['response' => $response]);

            return response()->json($response);
        }

        $orderId = $service->parseBillingNumber($billingNo);
        $paymentOrder = $this->findPaymentOrderByAppOrderId($orderId);

        if (! $paymentOrder) {
            $response = $service->buildPaymentNotificationResponse($guid, $joebppsTrx, $processDate, $stmtDate, 1, 'Order not found', 'Error');
            $service->log('Payment notification: payment order not found', [
                'billing_no' => $billingNo,
                'order_id' => $orderId,
            ]);

            return response()->json($response);
        }

        if ($paymentOrder->isCompleted()) {
            // Idempotent re-delivery: bank may re-send the notification.
            $service->log('Payment notification: already completed (idempotent)', [
                'order_id' => $orderId,
                'joebpps_trx' => $joebppsTrx,
            ]);
        } else {
            // Delegate to the host. CartService::setOrderAsPaid handles
            // withTrashed restore, status promotion, notifications, cart
            // conversion, and audit logging (Log::warning + report() on a
            // restored draft). This plugin doesn't need to know any of that.
            \App\Services\CartService::setOrderAsPaid(
                orderId: $orderId,
                paymentMethodId: $paymentOrder->payment_method_id,
                paymentOrder: $paymentOrder,
            );

            $paymentOrder->markAsCompleted([
                'transaction_id' => $joebppsTrx,
                'madfoat_data' => $data['MFEP']['MsgBody'] ?? [],
            ]);

            $service->log('Payment notification: order marked as paid', [
                'order_id' => $orderId,
                'paid_amount' => $paidAmt,
                'joebpps_trx' => $joebppsTrx,
            ]);
        }

        $this->attachInboundRequestToPaymentOrder($paymentOrder->id);

        $response = $service->buildPaymentNotificationResponse($guid, $joebppsTrx, $processDate, $stmtDate);

        return response()->json($response);
    }

    /**
     * Handle payment acknowledgment (PMTACKRQ → PMTACKRS).
     */
    protected function handlePaymentAcknowledgment(array $data, MadfoatService $service): JsonResponse
    {
        $guid = $service->extractGuid($data);
        $billingInfo = $data['MFEP']['MsgBody']['BillingInfo']
            ?? $data['MFEP']['MsgBody']['Transactions']['TrxInf']
            ?? [];
        $joebppsTrx = $billingInfo['JOEBPPSTrx'] ?? '';
        $processDate = $billingInfo['ProcessDate'] ?? now()->format('Y-m-d\TH:i:s');
        $stmtDate = $billingInfo['StmtDate'] ?? $billingInfo['STMTDate'] ?? now()->format('Y-m-d');

        $service->log('Payment acknowledgment received', [
            'joebpps_trx' => $joebppsTrx,
            'billing_no' => $billingInfo['AcctInfo']['BillingNo'] ?? '',
        ]);

        $response = $service->buildPaymentAcknowledgmentResponse($guid, $joebppsTrx, $processDate, $stmtDate);

        return response()->json($response);
    }

    /**
     * Handle prepaid validation (BILRPREPADVALRQ → BILRPREPADVALRS).
     */
    protected function handlePrepaidValidation(array $data, MadfoatService $service): JsonResponse
    {
        $guid = $service->extractGuid($data);

        // For e-commerce, prepaid validation returns the order amount
        $billingInfo = $data['MFEP']['MsgBody']['BillingInfo']
            ?? $data['MFEP']['MsgBody']['Transactions']['TrxInf']
            ?? [];
        $billingNo = $billingInfo['AcctInfo']['BillingNo'] ?? '';
        $validationCode = $billingInfo['ValidationCode'] ?? '';

        if (empty($billingNo)) {
            $response = $service->buildErrorResponse('BILRPREPADVALRS', $guid, 1, 'BillingNo is required');

            return response()->json($response);
        }

        $orderId = $service->parseBillingNumber($billingNo);
        $paymentOrder = $this->findPaymentOrderByAppOrderId($orderId);

        if (! $paymentOrder) {
            $response = $service->buildErrorResponse('BILRPREPADVALRS', $guid, 1, 'Order not found');

            return response()->json($response);
        }

        $dueAmt = number_format($paymentOrder->amount ?? 0, 3, '.', '');
        $customerName = $paymentOrder->customer_name ?? '';

        $response = $service->buildPrepaidValidationResponse(
            guid: $guid,
            billingNo: $billingNo,
            dueAmt: $dueAmt,
            validationCode: $validationCode,
            serviceType: $this->paymentMethod->getSetting('service_type', ''),
            customerName: $customerName,
            freeText: 'Order #' . $billingNo,
        );

        $service->log('Prepaid validation success', ['billing_no' => $billingNo, 'due_amt' => $dueAmt]);

        return response()->json($response);
    }

    /**
     * Create a MadfoatService instance from plugin configuration.
     */
    protected function getMadfoatService(): MadfoatService
    {
        return new MadfoatService(
            sdrCode: $this->getSdrCode(),
            serviceType: $this->paymentMethod->getSetting('service_type', ''),
            billExpiryDays: (int) $this->paymentMethod->getSetting('bill_expiry_days', '7'),
        );
    }

    /**
     * Resolve the active SdrCode based on test_mode.
     */
    protected function getSdrCode(): string
    {
        $testMode = $this->paymentMethod->getSetting('test_mode', true);

        return $testMode
            ? (string) $this->paymentMethod->getSetting('sdr_code_test', '')
            : (string) $this->paymentMethod->getSetting('sdr_code_production', '');
    }

    /**
     * Check if the given IP is allowed to make inbound requests.
     */
    protected function isIpAllowed(string $ip): bool
    {
        $allowedIps = $this->paymentMethod->getSetting('allowed_ips', '');

        if (empty($allowedIps)) {
            return true; // No restriction — allow all (for testing)
        }

        $allowed = array_map('trim', explode(',', $allowedIps));

        return in_array($ip, $allowed);
    }

    /**
     * Validate HTTP Basic Authentication credentials against plugin settings.
     */
    protected function isBasicAuthValid(): bool
    {
        $expectedUsername = $this->paymentMethod->getSetting('auth_username', '');
        $expectedPassword = $this->paymentMethod->getSetting('auth_password', '');

        // If no credentials configured, skip Basic Auth check (backward compatible)
        if (empty($expectedUsername) && empty($expectedPassword)) {
            return true;
        }

        $providedUsername = request()->getUser();
        $providedPassword = request()->getPassword();

        return $providedUsername === $expectedUsername
            && $providedPassword === $expectedPassword;
    }

    /**
     * Resolve the PaymentOrder linked to the app-level order id (matched via customer_data->order_id).
     */
    protected function findPaymentOrderByAppOrderId(int $orderId): ?PaymentOrder
    {
        return PaymentOrder::whereJsonContains('customer_data->order_id', $orderId)
            ->orWhereJsonContains('customer_data->order_id', (string) $orderId)
            ->first();
    }

    /**
     * Attach the resolved PaymentOrder id to the current inbound request audit row, if any.
     */
    protected function attachInboundRequestToPaymentOrder(?int $paymentOrderId): void
    {
        if (! $paymentOrderId) {
            return;
        }

        $record = request()->attributes->get('inbound_request_record');

        if ($record instanceof \Trinavo\PaymentGateway\Models\PaymentGatewayInboundRequest) {
            $record->update(['payment_order_id' => $paymentOrderId]);
        }
    }
}
