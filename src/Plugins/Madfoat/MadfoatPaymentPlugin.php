<?php

namespace Trinavo\PaymentGateway\Plugins\Madfoat;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Trinavo\PaymentGateway\Configuration\CheckboxField;
use Trinavo\PaymentGateway\Configuration\PasswordField;
use Trinavo\PaymentGateway\Configuration\TextField;
use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Events\PrepaidPaymentReceived;
use Trinavo\PaymentGateway\Events\PrepaidPaymentValidationRequested;
use Trinavo\PaymentGateway\Models\CallbackResponse;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Models\RefundResponse;
use Trinavo\PaymentGateway\Plugins\Madfoat\Concerns\MadfoatTransportTrait;

class MadfoatPaymentPlugin extends PaymentPluginInterface
{
    use MadfoatTransportTrait;

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
                name: 'prepaid_service_type',
                label: 'Prepaid Service Type',
                required: false,
                description: 'Optional. Inbound MFEP requests whose ServiceType equals this value are treated as PREPAID payments. The plugin fires PrepaidPaymentValidationRequested + PrepaidPaymentReceived events; host listeners resolve the customer by BillingNo and credit (e.g. wallet top-up). Leave empty to keep this method as postpaid-only.',
                placeholder: 'e.g. Pay_Fees',
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
        $incomingServiceType = $billingInfo['ServiceTypeDetails']['ServiceType']
            ?? $data['MFEP']['MsgBody']['ServiceType']
            ?? '';

        if (empty($billingNo)) {
            $response = $service->buildPaymentNotificationResponse($guid, $joebppsTrx, $processDate, $stmtDate, 1, 'BillingNo is required', 'Error');
            $service->log('Payment notification error: missing BillingNo', ['response' => $response]);

            return response()->json($response);
        }

        $prepaidServiceType = (string) $this->paymentMethod->getSetting('prepaid_service_type', '');
        if ($prepaidServiceType !== '' && $incomingServiceType === $prepaidServiceType) {
            return $this->handlePaymentNotificationForPrepaid(
                billingNo: $billingNo,
                joebppsTrx: $joebppsTrx,
                paidAmt: (string) $paidAmt,
                incomingServiceType: $incomingServiceType,
                processDate: $processDate,
                stmtDate: $stmtDate,
                guid: $guid,
                rawData: $data,
                service: $service,
            );
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
     * Payment notification path for the prepaid wallet-top-up flow.
     *
     * Idempotent on the provider transaction id (JOEBPPSTrx): a re-delivered
     * notification for an already-recorded transaction is acknowledged as
     * success without re-firing PrepaidPaymentReceived. Otherwise the plugin
     * dispatches PrepaidPaymentValidationRequested to resolve the customer,
     * records a synthetic PaymentOrder (carrying the listener-supplied
     * customer_data + the BillingNo), marks it completed, and dispatches
     * PrepaidPaymentReceived. The host listener for that event does the
     * provider-agnostic credit work (e.g. wallet top-up).
     *
     * Unknown BillingNo is logged + acknowledged as success rather than
     * rejected, because the customer has already been debited by the bank;
     * bouncing on the provider only causes retries. Reconciliation is via
     * the inbound-request audit log.
     */
    protected function handlePaymentNotificationForPrepaid(
        string $billingNo,
        string $joebppsTrx,
        string $paidAmt,
        string $incomingServiceType,
        string $processDate,
        string $stmtDate,
        string $guid,
        array $rawData,
        MadfoatService $service,
    ): JsonResponse {
        $existing = PaymentOrder::query()
            ->where('external_transaction_id', $joebppsTrx)
            ->where('payment_method_id', $this->paymentMethod->id)
            ->first();

        if ($existing) {
            $service->log('Prepaid payment notification: duplicate JOEBPPSTrx (idempotent)', [
                'billing_no' => $billingNo,
                'joebpps_trx' => $joebppsTrx,
                'payment_order_id' => $existing->id,
            ]);
            $this->attachInboundRequestToPaymentOrder($existing->id);

            $response = $service->buildPaymentNotificationResponse($guid, $joebppsTrx, $processDate, $stmtDate);

            return response()->json($response);
        }

        $event = new PrepaidPaymentValidationRequested(
            paymentMethod: $this->paymentMethod,
            billingNo: $billingNo,
            dueAmount: $paidAmt,
        );
        event($event);

        if ($event->identity === null) {
            $service->log('Prepaid payment notification: user not found (manual reconcile needed)', [
                'billing_no' => $billingNo,
                'joebpps_trx' => $joebppsTrx,
                'paid_amount' => $paidAmt,
                'service_type' => $incomingServiceType,
            ]);
            report(new \RuntimeException(sprintf(
                'Prepaid payment received for unknown BillingNo "%s" (JOEBPPSTrx %s, amount %s)',
                $billingNo,
                $joebppsTrx,
                $paidAmt,
            )));

            $response = $service->buildPaymentNotificationResponse($guid, $joebppsTrx, $processDate, $stmtDate);

            return response()->json($response);
        }

        $identity = $event->identity;

        $paymentOrder = PaymentOrder::create([
            'order_code' => 'PF-' . substr($joebppsTrx, 0, 14),
            'amount' => (float) $paidAmt,
            'currency' => 'JOD',
            'status' => PaymentOrder::STATUS_PENDING,
            'customer_name' => $identity->name,
            'customer_data' => array_merge($identity->meta, [
                'user_id' => $identity->userId,
                'user_identifier' => $identity->userIdentifier,
            ]),
            'payment_method_id' => $this->paymentMethod->id,
            'external_transaction_id' => $joebppsTrx,
            'description' => 'Prepaid payment via ' . ($this->paymentMethod->getLocalizedDisplayName() ?: 'Madfoat'),
        ]);

        $paymentOrder->markAsCompleted([
            'transaction_id' => $joebppsTrx,
            'madfoat_data' => $rawData['MFEP']['MsgBody'] ?? [],
        ]);

        event(new PrepaidPaymentReceived(
            paymentOrder: $paymentOrder->refresh(),
            paymentMethod: $this->paymentMethod,
            identity: $identity,
            providerTransactionId: $joebppsTrx,
        ));

        $this->attachInboundRequestToPaymentOrder($paymentOrder->id);

        $service->log('Prepaid payment notification: credited via PrepaidPaymentReceived event', [
            'billing_no' => $billingNo,
            'joebpps_trx' => $joebppsTrx,
            'paid_amount' => $paidAmt,
            'payment_order_id' => $paymentOrder->id,
            'customer_name' => $identity->name,
        ]);

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
     *
     * Two code paths share this handler:
     *  - Existing postpaid e-commerce flow (BillingNo = padded order id) when
     *    the inbound ServiceType matches the method's `service_type` setting
     *    or no `prepaid_service_type` is configured. Looks up a PaymentOrder
     *    by app order id and echoes its fixed amount.
     *  - New prepaid wallet-top-up flow when the inbound ServiceType matches
     *    the method's `prepaid_service_type` setting. Dispatches
     *    PrepaidPaymentValidationRequested so the host can resolve the
     *    BillingNo against its own customer identifier and supply a name; we
     *    echo the customer-entered DueAmt unchanged.
     */
    protected function handlePrepaidValidation(array $data, MadfoatService $service): JsonResponse
    {
        $guid = $service->extractGuid($data);

        $billingInfo = $data['MFEP']['MsgBody']['BillingInfo']
            ?? $data['MFEP']['MsgBody']['Transactions']['TrxInf']
            ?? [];
        $billingNo = $billingInfo['AcctInfo']['BillingNo'] ?? '';
        $validationCode = $billingInfo['ValidationCode'] ?? '';
        $incomingServiceType = $billingInfo['ServiceTypeDetails']['ServiceType']
            ?? $data['MFEP']['MsgBody']['ServiceType']
            ?? '';
        $incomingDueAmt = (string) ($billingInfo['DueAmt'] ?? '0.000');

        if (empty($billingNo)) {
            $response = $service->buildErrorResponse('BILRPREPADVALRS', $guid, 1, 'BillingNo is required');

            return response()->json($response);
        }

        $prepaidServiceType = (string) $this->paymentMethod->getSetting('prepaid_service_type', '');
        if ($prepaidServiceType !== '' && $incomingServiceType === $prepaidServiceType) {
            return $this->handlePrepaidValidationForPrepaid(
                billingNo: $billingNo,
                validationCode: $validationCode,
                dueAmt: $incomingDueAmt,
                incomingServiceType: $incomingServiceType,
                guid: $guid,
                service: $service,
            );
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
     * Prepaid validation path for the wallet-top-up flow.
     *
     * Dispatches PrepaidPaymentValidationRequested synchronously; a host
     * listener populates `customerName` and `customerData`. Null
     * `customerName` after dispatch means the BillingNo did not resolve to
     * any known customer, which we report to the provider as "User not
     * found" so the bank app shows an error before the customer pays. The
     * customer-entered DueAmt is echoed unchanged.
     */
    protected function handlePrepaidValidationForPrepaid(
        string $billingNo,
        string $validationCode,
        string $dueAmt,
        string $incomingServiceType,
        string $guid,
        MadfoatService $service,
    ): JsonResponse {
        $event = new PrepaidPaymentValidationRequested(
            paymentMethod: $this->paymentMethod,
            billingNo: $billingNo,
            dueAmount: $dueAmt,
        );
        event($event);

        if ($event->identity === null) {
            $service->log('Prepaid validation: user not found for billing_no', [
                'billing_no' => $billingNo,
                'service_type' => $incomingServiceType,
            ]);
            $response = $service->buildErrorResponse('BILRPREPADVALRS', $guid, 1, 'User not found');

            return response()->json($response);
        }

        $response = $service->buildPrepaidValidationResponse(
            guid: $guid,
            billingNo: $billingNo,
            dueAmt: $dueAmt,
            validationCode: $validationCode,
            serviceType: $incomingServiceType,
            customerName: $event->identity->name,
            freeText: 'Top-up for ' . $event->identity->name,
        );

        $service->log('Prepaid validation (prepaid mode) success', [
            'billing_no' => $billingNo,
            'service_type' => $incomingServiceType,
            'due_amt' => $dueAmt,
            'customer_name' => $event->identity->name,
        ]);

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
     * Resolve the PaymentOrder linked to the app-level order id (matched via customer_data->order_id).
     */
    protected function findPaymentOrderByAppOrderId(int $orderId): ?PaymentOrder
    {
        return PaymentOrder::whereJsonContains('customer_data->order_id', $orderId)
            ->orWhereJsonContains('customer_data->order_id', (string) $orderId)
            ->first();
    }
}
