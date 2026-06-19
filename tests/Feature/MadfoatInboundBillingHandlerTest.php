<?php

namespace Trinavo\PaymentGateway\Tests\Feature;

use Orchestra\Testbench\TestCase;
use Trinavo\PaymentGateway\Contracts\InboundBillingHandler;
use Trinavo\PaymentGateway\Models\PaymentMethod;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Plugins\Madfoat\MadfoatPaymentPlugin;
use Trinavo\PaymentGateway\Providers\PaymentGatewayServiceProvider;
use Trinavo\PaymentGateway\Support\Inbound\BillDescriptor;
use Trinavo\PaymentGateway\Support\Inbound\InboundBillContext;
use Trinavo\PaymentGateway\Support\Inbound\InboundPaymentContext;
use Trinavo\PaymentGateway\Tests\Feature\Fakes\FakeInboundBillingHandler;

class MadfoatInboundBillingHandlerTest extends TestCase
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
        $this->paymentMethod->setSetting('bill_expiry_days', '7', false);

        $this->plugin = new MadfoatPaymentPlugin($this->paymentMethod);
    }

    /**
     * Bind a fake handler that claims a fixed reference, like the host would.
     */
    protected function bindHandler(): FakeInboundBillingHandler
    {
        $fake = new FakeInboundBillingHandler;
        $this->app->instance(InboundBillingHandler::class, $fake);

        return $fake;
    }

    public function test_config_fields_no_longer_expose_wallet_or_prepaid_settings()
    {
        $names = array_map(fn ($f) => $f->getName(), $this->plugin->getConfigurationFields());
        $this->assertNotContains('prepaid_service_type', $names);
        $this->assertNotContains('enable_wallet_funding', $names);
    }

    public function test_bill_pull_uses_descriptor_and_allows_overpayment()
    {
        $fake = $this->bindHandler();
        $fake->describe = new BillDescriptor(customerName: 'Vendor X', amount: '5.000', allowOverpayment: true, note: 'Top-up');

        $response = $this->plugin->handleInboundRequest('bill-pull', $this->makeBilpulrq('REF-1'));
        $rec = json_decode($response->getContent(), true)['MFEP']['MsgBody']['BillRec'][0];

        $this->assertSame(0, $rec['Result']['ErrorCode']);
        $this->assertSame('5.000', $rec['DueAmount']);
        $this->assertSame('Vendor X', $rec['AdditionalInfo']['CustName']);
        $this->assertTrue($rec['PmtConst']['AllowPart']);
        $this->assertGreaterThan((float) $rec['PmtConst']['Lower'], (float) $rec['PmtConst']['Upper']);

        // The handler saw the bill-pull with no proposed amount.
        $this->assertInstanceOf(InboundBillContext::class, $fake->lastBillContext);
        $this->assertSame('REF-1', $fake->lastBillContext->reference);
        $this->assertNull($fake->lastBillContext->proposedAmount);
    }

    public function test_bill_pull_with_default_null_handler_returns_invalid_bill()
    {
        // No binding override → NullInboundBillingHandler declines.
        $response = $this->plugin->handleInboundRequest('bill-pull', $this->makeBilpulrq('REF-UNKNOWN'));
        $body = json_decode($response->getContent(), true);

        $this->assertSame(408, $body['MFEP']['MsgBody']['BillRec'][0]['Result']['ErrorCode']);
    }

    public function test_prepaid_validation_passes_entered_amount_as_proposed_and_echoes_descriptor()
    {
        $fake = $this->bindHandler();
        $fake->describe = new BillDescriptor(customerName: 'Vendor X', amount: '12.500');

        $response = $this->plugin->handleInboundRequest('prepaid-validation', $this->makeBilrprepadvalrq('REF-1', '12.500'));
        $body = json_decode($response->getContent(), true);

        $this->assertSame(0, $body['MFEP']['MsgHeader']['Result']['ErrorCode']);
        $this->assertSame('12.500', $body['MFEP']['MsgBody']['BillingInfo']['DueAmt']);
        $this->assertSame('Vendor X', $body['MFEP']['MsgBody']['BillingInfo']['AdditionalInfo']['CustName']);
        $this->assertSame('12.500', $fake->lastBillContext->proposedAmount);
    }

    public function test_payment_notification_creates_completed_payment_order_when_handled()
    {
        $fake = $this->bindHandler();
        $fake->handle = true;

        $response = $this->plugin->handleInboundRequest('payment-notification', $this->makeBlrpmtntfrq('REF-1', 'TX1', '10.000'));
        $body = json_decode($response->getContent(), true);

        $this->assertSame(0, $body['MFEP']['MsgHeader']['Result']['ErrorCode'] ?? null);
        $this->assertCount(1, $fake->payments);

        $po = PaymentOrder::where('external_transaction_id', 'TX1')->first();
        $this->assertNotNull($po);
        $this->assertStringStartsWith('IN-', $po->order_code);
        $this->assertSame(10.00, (float) $po->amount);
        $this->assertSame('REF-1', $po->customer_data['reference']);
        $this->assertSame(PaymentOrder::STATUS_COMPLETED, $po->status);

        // The context carried the pre-created PaymentOrder.
        $this->assertInstanceOf(InboundPaymentContext::class, $fake->payments[0]);
        $this->assertSame($po->id, $fake->payments[0]->paymentOrder->id);
    }

    public function test_repeated_reference_different_transactions_create_separate_payment_orders()
    {
        $fake = $this->bindHandler();
        $fake->handle = true;

        $this->plugin->handleInboundRequest('payment-notification', $this->makeBlrpmtntfrq('REF-1', 'TX_A', '10.000'));
        $this->plugin->handleInboundRequest('payment-notification', $this->makeBlrpmtntfrq('REF-1', 'TX_B', '5.000'));

        $this->assertCount(2, $fake->payments);
        $this->assertSame(2, PaymentOrder::where('customer_data->reference', 'REF-1')->count());
    }

    public function test_replayed_transaction_does_not_create_second_payment_order_or_re_handle()
    {
        $fake = $this->bindHandler();
        $fake->handle = true;

        $payload = $this->makeBlrpmtntfrq('REF-1', 'TX_REPLAY', '10.000');
        $this->plugin->handleInboundRequest('payment-notification', $payload);
        $response2 = $this->plugin->handleInboundRequest('payment-notification', $payload);
        $body2 = json_decode($response2->getContent(), true);

        $this->assertSame(0, $body2['MFEP']['MsgHeader']['Result']['ErrorCode'] ?? null);
        $this->assertCount(1, $fake->payments);
        $this->assertSame(1, PaymentOrder::where('external_transaction_id', 'TX_REPLAY')->count());
    }

    public function test_unhandled_payment_returns_order_not_found_and_leaves_no_payment_order()
    {
        $fake = $this->bindHandler();
        $fake->handle = false;

        $response = $this->plugin->handleInboundRequest('payment-notification', $this->makeBlrpmtntfrq('REF-NOPE', 'TX_FAIL', '3.000'));
        $body = json_decode($response->getContent(), true);

        // Declined reference falls back to the original "Order not found" error,
        // and the provisional PaymentOrder is dropped (raw request stays in the
        // gateway's inbound-request audit log). Byte-identical to the default path.
        $this->assertSame(1, $body['MFEP']['MsgHeader']['Result']['ErrorCode']);
        $this->assertSame(0, PaymentOrder::where('external_transaction_id', 'TX_FAIL')->count());
    }

    public function test_unhandled_payment_with_default_null_handler_returns_order_not_found()
    {
        // No binding override → NullInboundBillingHandler declines → original behaviour.
        $response = $this->plugin->handleInboundRequest('payment-notification', $this->makeBlrpmtntfrq('REF-NONE', 'TX_NONE', '3.000'));
        $body = json_decode($response->getContent(), true);

        $this->assertSame(1, $body['MFEP']['MsgHeader']['Result']['ErrorCode']);
        $this->assertSame(0, PaymentOrder::where('external_transaction_id', 'TX_NONE')->count());
    }

    public function test_order_billing_no_never_consults_the_handler()
    {
        $fake = $this->bindHandler();

        // Seed a real order PaymentOrder so the order path resolves.
        PaymentOrder::create([
            'order_code' => 'PO-REAL',
            'amount' => 1.00,
            'currency' => 'JOD',
            'status' => PaymentOrder::STATUS_PENDING,
            'customer_data' => ['order_id' => 52],
            'payment_method_id' => $this->paymentMethod->id,
        ]);

        $response = $this->plugin->handleInboundRequest('bill-pull', $this->makeBilpulrq('0000000052'));
        $rec = json_decode($response->getContent(), true)['MFEP']['MsgBody']['BillRec'][0];

        $this->assertSame(0, $rec['Result']['ErrorCode']);
        $this->assertNull($fake->lastBillContext, 'Handler must not be consulted when the reference resolves to an order');
    }

    /** @return array<string, mixed> */
    protected function makeBilpulrq(string $billingNo): array
    {
        return [
            'MFEP' => [
                'MsgHeader' => [
                    'TmStp' => '2026-05-11T20:00:00',
                    'TrsInf' => ['SdrCode' => 1, 'RcvCode' => 12345, 'ReqTyp' => 'BILPULRQ'],
                    'GUID' => 'test-guid-'.uniqid(),
                ],
                'MsgBody' => [
                    'AcctInfo' => ['BillingNo' => $billingNo, 'BillNo' => $billingNo],
                    'ServiceType' => 'Pay_Bill',
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    protected function makeBilrprepadvalrq(string $billingNo, string $dueAmt): array
    {
        return [
            'MFEP' => [
                'MsgHeader' => [
                    'TmStp' => '2026-05-11T20:00:00',
                    'TrsInf' => ['SdrCode' => 1, 'RcvCode' => 12345, 'ReqTyp' => 'BILRPREPADVALRQ'],
                    'GUID' => 'test-guid-'.uniqid(),
                ],
                'MsgBody' => [
                    'BillingInfo' => [
                        'AcctInfo' => ['BillingNo' => $billingNo, 'BillerCode' => 12345],
                        'ServiceTypeDetails' => ['ServiceType' => 'Pay_Fees', 'PrepaidCat' => 'general'],
                        'DueAmt' => $dueAmt,
                        'ValidationCode' => str_pad('', 50, 'V'),
                    ],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    protected function makeBlrpmtntfrq(string $billingNo, string $joebppsTrx, string $paidAmt): array
    {
        return [
            'MFEP' => [
                'MsgHeader' => [
                    'TmStp' => '2026-05-11T20:00:00',
                    'TrsInf' => ['SdrCode' => 1, 'RcvCode' => 12345, 'ReqTyp' => 'BLRPMTNTFRQ'],
                    'GUID' => 'test-guid-'.uniqid(),
                ],
                'MsgBody' => [
                    'BillingInfo' => [
                        'AcctInfo' => ['BillingNo' => $billingNo, 'BillerCode' => 12345],
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
