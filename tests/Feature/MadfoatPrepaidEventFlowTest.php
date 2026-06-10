<?php

namespace Trinavo\PaymentGateway\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\TestCase;
use Trinavo\PaymentGateway\Events\Dto\PrepaidCustomerIdentity;
use Trinavo\PaymentGateway\Events\PrepaidPaymentReceived;
use Trinavo\PaymentGateway\Events\PrepaidPaymentValidationRequested;
use Trinavo\PaymentGateway\Models\PaymentMethod;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Plugins\Madfoat\MadfoatPaymentPlugin;
use Trinavo\PaymentGateway\Providers\PaymentGatewayServiceProvider;

class MadfoatPrepaidEventFlowTest extends TestCase
{
    protected PaymentMethod $paymentMethod;

    protected MadfoatPaymentPlugin $plugin;

    protected function getPackageProviders($app)
    {
        return [PaymentGatewayServiceProvider::class];
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->paymentMethod = PaymentMethod::create([
            'name' => json_encode(['en' => 'Madfoat (eFAWATEERcom)']),
            'plugin_class' => MadfoatPaymentPlugin::class,
            'enabled' => true,
        ]);

        $this->paymentMethod->setSetting('sdr_code_test', '12345', false);
        $this->paymentMethod->setSetting('service_type', 'Pay_Bill', false);
        $this->paymentMethod->setSetting('prepaid_service_type', 'Pay_Fees', false);
        $this->paymentMethod->setSetting('bill_expiry_days', '7', false);

        $this->plugin = new MadfoatPaymentPlugin($this->paymentMethod);
    }

    public function test_prepaid_service_type_field_is_exposed()
    {
        $fields = $this->plugin->getConfigurationFields();
        $names = array_map(fn ($f) => $f->getName(), $fields);

        $this->assertContains('prepaid_service_type', $names);
    }

    public function test_prepaid_validation_dispatches_event_and_returns_listener_customer_name()
    {
        Event::listen(PrepaidPaymentValidationRequested::class, function (PrepaidPaymentValidationRequested $event) {
            if ($event->billingNo === '0000099999') {
                $event->identity = new PrepaidCustomerIdentity(
                    userId: 99,
                    userIdentifier: $event->billingNo,
                    name: 'Vendor X',
                );
            }
        });

        $payload = $this->makeBilrprepadvalrq(
            billingNo: '0000099999',
            serviceType: 'Pay_Fees',
            dueAmt: '12.500',
        );

        $response = $this->plugin->handleInboundRequest('prepaid-validation', $payload);
        $body = json_decode($response->getContent(), true);

        $this->assertSame(0, $body['MFEP']['MsgHeader']['Result']['ErrorCode']);
        $this->assertSame('12.500', $body['MFEP']['MsgBody']['BillingInfo']['DueAmt']);
        $this->assertSame('Vendor X', $body['MFEP']['MsgBody']['BillingInfo']['AdditionalInfo']['CustName']);
    }

    public function test_prepaid_validation_without_listener_returning_customer_name_returns_user_not_found()
    {
        $payload = $this->makeBilrprepadvalrq(
            billingNo: 'UNKNOWN',
            serviceType: 'Pay_Fees',
            dueAmt: '5.000',
        );

        $response = $this->plugin->handleInboundRequest('prepaid-validation', $payload);
        $body = json_decode($response->getContent(), true);

        $this->assertNotSame(0, $body['MFEP']['MsgHeader']['Result']['ErrorCode']);
        $this->assertStringContainsStringIgnoringCase(
            'user not found',
            $body['MFEP']['MsgHeader']['Result']['ErrorDesc']
        );
    }

    public function test_payment_notification_dispatches_validation_then_received_event_and_creates_synthetic_payment_order()
    {
        Event::listen(PrepaidPaymentValidationRequested::class, function (PrepaidPaymentValidationRequested $event) {
            $event->identity = new PrepaidCustomerIdentity(
                userId: 99,
                userIdentifier: $event->billingNo,
                name: 'Vendor X',
            );
        });

        $received = [];
        Event::listen(PrepaidPaymentReceived::class, function (PrepaidPaymentReceived $event) use (&$received) {
            $received[] = $event;
        });

        $payload = $this->makeBlrpmtntfrq(
            billingNo: '0000099999',
            serviceType: 'Pay_Fees',
            joebppsTrx: 'TX1',
            paidAmt: '12.500',
        );

        $response = $this->plugin->handleInboundRequest('payment-notification', $payload);
        $body = json_decode($response->getContent(), true);

        $this->assertSame(0, $body['MFEP']['MsgHeader']['Result']['ErrorCode'] ?? null);
        $this->assertCount(1, $received);

        /** @var PaymentOrder $po */
        $po = $received[0]->paymentOrder;
        $this->assertSame('TX1', $po->external_transaction_id);
        $this->assertSame(12.50, (float) $po->amount);
        $this->assertSame(99, $po->customer_data['user_id']);
        $this->assertSame('0000099999', $po->customer_data['user_identifier']);
        $this->assertSame('Vendor X', $po->customer_name);
        $this->assertSame(PaymentOrder::STATUS_COMPLETED, $po->status);
        $this->assertSame($this->paymentMethod->id, $received[0]->paymentMethod->id);
        $this->assertSame('TX1', $received[0]->providerTransactionId);
        // Identity is propagated as a typed object, not array-fished from PaymentOrder.
        $this->assertInstanceOf(PrepaidCustomerIdentity::class, $received[0]->identity);
        $this->assertSame(99, $received[0]->identity->userId);
        $this->assertSame('0000099999', $received[0]->identity->userIdentifier);
        $this->assertSame('Vendor X', $received[0]->identity->name);
    }

    public function test_replayed_payment_notification_does_not_create_second_payment_order_or_refire_received_event()
    {
        Event::listen(PrepaidPaymentValidationRequested::class, function (PrepaidPaymentValidationRequested $event) {
            $event->identity = new PrepaidCustomerIdentity(
                userId: 99,
                userIdentifier: $event->billingNo,
                name: 'Vendor X',
            );
        });

        $receivedCount = 0;
        Event::listen(PrepaidPaymentReceived::class, function () use (&$receivedCount) {
            $receivedCount++;
        });

        $payload = $this->makeBlrpmtntfrq(
            billingNo: '0000099999',
            serviceType: 'Pay_Fees',
            joebppsTrx: 'TX_REPLAY',
            paidAmt: '7.000',
        );

        $this->plugin->handleInboundRequest('payment-notification', $payload);
        $response2 = $this->plugin->handleInboundRequest('payment-notification', $payload);
        $body2 = json_decode($response2->getContent(), true);

        $this->assertSame(0, $body2['MFEP']['MsgHeader']['Result']['ErrorCode'] ?? null);
        $this->assertSame(1, $receivedCount);
        $this->assertSame(1, PaymentOrder::where('external_transaction_id', 'TX_REPLAY')->count());
    }

    public function test_payment_notification_with_unknown_user_logs_and_returns_success_without_creating_payment_order()
    {
        // No validation listener registered → customerName stays null → unknown user.
        $received = false;
        Event::listen(PrepaidPaymentReceived::class, function () use (&$received) {
            $received = true;
        });

        $payload = $this->makeBlrpmtntfrq(
            billingNo: 'NOSUCH',
            serviceType: 'Pay_Fees',
            joebppsTrx: 'TX_UNKNOWN',
            paidAmt: '3.000',
        );

        $response = $this->plugin->handleInboundRequest('payment-notification', $payload);
        $body = json_decode($response->getContent(), true);

        $this->assertSame(0, $body['MFEP']['MsgHeader']['Result']['ErrorCode'] ?? null);
        $this->assertFalse($received);
        $this->assertSame(0, PaymentOrder::where('external_transaction_id', 'TX_UNKNOWN')->count());
    }

    public function test_pay_bill_payment_notification_does_not_trigger_prepaid_event_flow()
    {
        Event::listen(PrepaidPaymentReceived::class, function () {
            $this->fail('PrepaidPaymentReceived must not fire for Pay_Bill ServiceType');
        });

        // Seed a PaymentOrder the way the postpaid path expects.
        $paymentOrder = PaymentOrder::create([
            'order_code' => 'PO-LEGACY',
            'amount' => 1.00,
            'currency' => 'JOD',
            'status' => PaymentOrder::STATUS_PENDING,
            'customer_data' => ['order_id' => 52],
            'payment_method_id' => $this->paymentMethod->id,
            'success_callback' => '// no-op for this test',
        ]);

        $payload = $this->makeBlrpmtntfrq(
            billingNo: '0000000052',
            serviceType: 'Pay_Bill',
            joebppsTrx: 'TX_ORDER',
            paidAmt: '1.000',
        );

        // The legacy path hardcodes \App\Services\CartService::setOrderAsPaid — that
        // class doesn't exist under the package's testbench, but we can swallow the
        // error here: the assertion that matters is that NO PrepaidPaymentReceived
        // was dispatched. We use try/catch to keep the test focused.
        try {
            $this->plugin->handleInboundRequest('payment-notification', $payload);
        } catch (\Throwable $e) {
            // Expected: \App\Services\CartService not found in package test environment.
            $this->assertStringContainsString('CartService', $e->getMessage());
        }

        // Regression: the synthetic prepaid PaymentOrder path must not have run.
        $this->assertSame(0, PaymentOrder::where('order_code', 'like', 'PF-%')->count());
    }

    public function test_pay_bill_flow_unchanged_when_prepaid_service_type_setting_is_empty()
    {
        $this->paymentMethod->setSetting('prepaid_service_type', '', false);
        $plugin = new MadfoatPaymentPlugin($this->paymentMethod->fresh());

        Event::listen(PrepaidPaymentReceived::class, function () {
            $this->fail('PrepaidPaymentReceived must not fire when prepaid_service_type is empty');
        });
        Event::listen(PrepaidPaymentValidationRequested::class, function () {
            $this->fail('PrepaidPaymentValidationRequested must not fire when prepaid_service_type is empty');
        });

        $payload = $this->makeBilrprepadvalrq(
            billingNo: '0000099999',
            serviceType: 'Pay_Fees',
            dueAmt: '5.000',
        );

        $response = $plugin->handleInboundRequest('prepaid-validation', $payload);
        $body = json_decode($response->getContent(), true);

        // Falls through to the legacy postpaid path → "Order not found".
        $this->assertNotSame(0, $body['MFEP']['MsgHeader']['Result']['ErrorCode']);
    }

    /**
     * Build a minimal valid BILRPREPADVALRQ MFEP payload.
     *
     * @return array<string, mixed>
     */
    protected function makeBilrprepadvalrq(string $billingNo, string $serviceType, string $dueAmt): array
    {
        return [
            'MFEP' => [
                'MsgHeader' => [
                    'TmStp' => '2026-05-11T20:00:00',
                    'TrsInf' => [
                        'SdrCode' => 1,
                        'RcvCode' => 12345,
                        'ReqTyp' => 'BILRPREPADVALRQ',
                    ],
                    'GUID' => 'test-guid-'.uniqid(),
                ],
                'MsgBody' => [
                    'BillingInfo' => [
                        'AcctInfo' => [
                            'BillingNo' => $billingNo,
                            'BillerCode' => 12345,
                        ],
                        'ServiceTypeDetails' => [
                            'ServiceType' => $serviceType,
                            'PrepaidCat' => 'general',
                        ],
                        'DueAmt' => $dueAmt,
                        'ValidationCode' => str_pad('', 50, 'V'),
                    ],
                ],
            ],
        ];
    }

    /**
     * Build a minimal valid BLRPMTNTFRQ MFEP payload.
     *
     * @return array<string, mixed>
     */
    protected function makeBlrpmtntfrq(string $billingNo, string $serviceType, string $joebppsTrx, string $paidAmt): array
    {
        return [
            'MFEP' => [
                'MsgHeader' => [
                    'TmStp' => '2026-05-11T20:00:00',
                    'TrsInf' => [
                        'SdrCode' => 1,
                        'RcvCode' => 12345,
                        'ReqTyp' => 'BLRPMTNTFRQ',
                    ],
                    'GUID' => 'test-guid-'.uniqid(),
                ],
                'MsgBody' => [
                    'BillingInfo' => [
                        'AcctInfo' => [
                            'BillingNo' => $billingNo,
                            'BillerCode' => 12345,
                        ],
                        'ServiceTypeDetails' => [
                            'ServiceType' => $serviceType,
                        ],
                        'JOEBPPSTrx' => $joebppsTrx,
                        'PaidAmt' => $paidAmt,
                        'ProcessDate' => '2026-05-11T20:00:00',
                        'StmtDate' => '2026-05-11',
                    ],
                ],
            ],
        ];
    }
}
