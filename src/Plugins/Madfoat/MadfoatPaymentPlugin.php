<?php

namespace Trinavo\PaymentGateway\Plugins\Madfoat;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Trinavo\PaymentGateway\Configuration\TextField;
use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\CallbackResponse;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Models\RefundResponse;

class MadfoatPaymentPlugin extends PaymentPluginInterface
{
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
                name: 'biller_code',
                label: 'Biller Code',
                required: true,
                description: 'Your biller code assigned by Madfoat/eFAWATEERcom',
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
                name: 'log_channel',
                label: 'Log Channel',
                required: false,
                default: 'stack',
                description: 'Laravel log channel name for Madfoat request/response logging',
                placeholder: 'madfoat',
            ),
        ];
    }

    public function validateConfiguration(): bool
    {
        return ! empty($this->paymentMethod->getSetting('biller_code'))
            && ! empty($this->paymentMethod->getSetting('service_type'));
    }

    public function processPayment(PaymentOrder $paymentOrder)
    {
        $orderId = $paymentOrder->customer_data['order_id'] ?? null;
        $billingNumber = $orderId ? $this->getMadfoatService()->generateBillingNumber((int) $orderId) : $paymentOrder->order_code;

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

        // Use the app's Order model dynamically to avoid tight coupling
        $orderClass = $this->getOrderModelClass();
        $order = $orderClass::find($orderId);

        if (! $order) {
            $response = $service->buildBillPullErrorResponse($guid, 1, 'Bill not found');
            $service->log('Bill pull: order not found', ['billing_no' => $billingNo, 'order_id' => $orderId]);

            return response()->json($response);
        }

        $orderData = [
            'final_total' => $order->final_total ?? $order->amount ?? 0,
            'paid' => (bool) ($order->paid ?? false),
            'customer_name' => $order->customer_name ?? '',
            'customer_email' => $order->customer_email ?? '',
            'customer_mobile' => $order->customer_mobile ?? $order->customer_phone ?? '',
            'created_at' => $order->created_at ?? now(),
        ];

        $response = $service->buildBillPullResponse($data, $order, $orderData);
        $service->log('Bill pull success', ['billing_no' => $billingNo, 'response' => $response]);

        return response()->json($response);
    }

    /**
     * Handle payment notification (BLRPMTNTFRQ → BLRPMTNTFRS).
     */
    protected function handlePaymentNotification(array $data, MadfoatService $service): JsonResponse
    {
        $guid = $service->extractGuid($data);
        $billingInfo = $data['MFEP']['MsgBody']['BillingInfo'] ?? [];
        $billingNo = $billingInfo['AcctInfo']['BillingNo'] ?? '';
        $joebppsTrx = $billingInfo['JOEBPPSTrx'] ?? '';
        $paidAmt = $billingInfo['PaidAmt'] ?? '0.000';
        $processDate = $billingInfo['ProcessDate'] ?? now()->format('Y-m-d\TH:i:s');
        $stmtDate = $billingInfo['StmtDate'] ?? now()->format('Y-m-d');

        if (empty($billingNo)) {
            $response = $service->buildPaymentNotificationResponse($guid, $joebppsTrx, $processDate, $stmtDate, 1, 'BillingNo is required', 'Error');
            $service->log('Payment notification error: missing BillingNo', ['response' => $response]);

            return response()->json($response);
        }

        $orderId = $service->parseBillingNumber($billingNo);
        $orderClass = $this->getOrderModelClass();
        $order = $orderClass::find($orderId);

        if (! $order) {
            $response = $service->buildPaymentNotificationResponse($guid, $joebppsTrx, $processDate, $stmtDate, 1, 'Order not found', 'Error');
            $service->log('Payment notification: order not found', ['billing_no' => $billingNo]);

            return response()->json($response);
        }

        // Mark order as paid if not already
        if (! ($order->paid ?? false)) {
            $order->paid = true;
            $order->save();

            // Also try to update the PaymentOrder if one exists
            $this->markPaymentOrderCompleted($orderId, $joebppsTrx, $data);

            $service->log('Payment notification: order marked as paid', [
                'order_id' => $orderId,
                'paid_amount' => $paidAmt,
                'joebpps_trx' => $joebppsTrx,
            ]);
        } else {
            $service->log('Payment notification: order already paid (idempotent)', [
                'order_id' => $orderId,
                'joebpps_trx' => $joebppsTrx,
            ]);
        }

        $response = $service->buildPaymentNotificationResponse($guid, $joebppsTrx, $processDate, $stmtDate);

        return response()->json($response);
    }

    /**
     * Handle payment acknowledgment (PMTACKRQ → PMTACKRS).
     */
    protected function handlePaymentAcknowledgment(array $data, MadfoatService $service): JsonResponse
    {
        $guid = $service->extractGuid($data);
        $billingInfo = $data['MFEP']['MsgBody']['BillingInfo'] ?? [];
        $joebppsTrx = $billingInfo['JOEBPPSTrx'] ?? '';
        $processDate = $billingInfo['ProcessDate'] ?? now()->format('Y-m-d\TH:i:s');
        $stmtDate = $billingInfo['StmtDate'] ?? now()->format('Y-m-d');

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
        $billingInfo = $data['MFEP']['MsgBody']['BillingInfo'] ?? [];
        $billingNo = $billingInfo['AcctInfo']['BillingNo'] ?? '';
        $validationCode = $billingInfo['ValidationCode'] ?? '';

        if (empty($billingNo)) {
            $response = $service->buildErrorResponse('BILRPREPADVALRS', $guid, 1, 'BillingNo is required');

            return response()->json($response);
        }

        $orderId = $service->parseBillingNumber($billingNo);
        $orderClass = $this->getOrderModelClass();
        $order = $orderClass::find($orderId);

        if (! $order) {
            $response = $service->buildErrorResponse('BILRPREPADVALRS', $guid, 1, 'Order not found');

            return response()->json($response);
        }

        $dueAmt = number_format($order->final_total ?? $order->amount ?? 0, 3, '.', '');

        $response = $service->buildPrepaidValidationResponse(
            guid: $guid,
            billingNo: $billingNo,
            dueAmt: $dueAmt,
            validationCode: $validationCode,
            serviceType: $this->paymentMethod->getSetting('service_type', ''),
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
            billerCode: $this->paymentMethod->getSetting('biller_code', ''),
            serviceType: $this->paymentMethod->getSetting('service_type', ''),
            billExpiryDays: (int) $this->paymentMethod->getSetting('bill_expiry_days', '7'),
            logChannel: $this->paymentMethod->getSetting('log_channel', 'stack'),
        );
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
     * Get the Order model class.
     * Uses the app's Order model if available, falls back to generic lookup.
     */
    protected function getOrderModelClass(): string
    {
        // Check common locations for the Order model
        if (class_exists('App\\Models\\Order')) {
            return 'App\\Models\\Order';
        }

        return 'App\\Order';
    }

    /**
     * Try to mark the corresponding PaymentOrder as completed.
     */
    protected function markPaymentOrderCompleted(int $orderId, string $transactionId, array $mfepData): void
    {
        $paymentOrder = PaymentOrder::where('status', 'pending')
            ->whereJsonContains('customer_data->order_id', $orderId)
            ->first();

        if (! $paymentOrder) {
            // Also try with string version of order_id
            $paymentOrder = PaymentOrder::where('status', 'pending')
                ->whereJsonContains('customer_data->order_id', (string) $orderId)
                ->first();
        }

        if ($paymentOrder) {
            $paymentOrder->markAsCompleted([
                'transaction_id' => $transactionId,
                'madfoat_data' => $mfepData['MFEP']['MsgBody']['BillingInfo'] ?? [],
            ]);
        }
    }
}
