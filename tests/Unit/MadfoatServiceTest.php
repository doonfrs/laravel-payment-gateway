<?php

namespace Trinavo\PaymentGateway\Tests\Unit;

use Illuminate\Support\Facades\Log;
use Orchestra\Testbench\TestCase;
use Trinavo\PaymentGateway\Plugins\Madfoat\MadfoatService;
use Trinavo\PaymentGateway\Providers\PaymentGatewayServiceProvider;

class MadfoatServiceTest extends TestCase
{
    protected MadfoatService $service;

    protected function getPackageProviders($app)
    {
        return [
            PaymentGatewayServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MadfoatService(
            billerCode: '12345',
            serviceType: 'Sales',
            billExpiryDays: 7,
            logChannel: 'stack',
        );
    }

    /** @test */
    public function it_generates_billing_number_padded_to_10_digits()
    {
        $this->assertEquals('0000000042', $this->service->generateBillingNumber(42));
        $this->assertEquals('0000001234', $this->service->generateBillingNumber(1234));
        $this->assertEquals('0000000001', $this->service->generateBillingNumber(1));
    }

    /** @test */
    public function it_parses_billing_number_back_to_order_id()
    {
        $this->assertEquals(42, $this->service->parseBillingNumber('0000000042'));
        $this->assertEquals(1234, $this->service->parseBillingNumber('0000001234'));
        $this->assertEquals(1, $this->service->parseBillingNumber('0000000001'));
    }

    /** @test */
    public function it_extracts_guid_from_mfep_request()
    {
        $request = [
            'MFEP' => [
                'MsgHeader' => [
                    'GUID' => 'test-guid-123',
                ],
            ],
        ];

        $this->assertEquals('test-guid-123', $this->service->extractGuid($request));
    }

    /** @test */
    public function it_returns_empty_string_when_guid_missing()
    {
        $this->assertEquals('', $this->service->extractGuid([]));
        $this->assertEquals('', $this->service->extractGuid(['MFEP' => []]));
    }

    /** @test */
    public function it_builds_bill_pull_error_response()
    {
        $response = $this->service->buildBillPullErrorResponse('guid-123', 1, 'Bill not found');

        $this->assertEquals('guid-123', $response['MFEP']['MsgHeader']['GUID']);
        $this->assertEquals('BILPULRS', $response['MFEP']['MsgHeader']['TrsInf']['ResTyp']);
        $this->assertEquals('12345', $response['MFEP']['MsgHeader']['TrsInf']['SdrCode']);
        $this->assertEquals(1, $response['MFEP']['MsgHeader']['Result']['ErrorCode']);
        $this->assertEquals('Bill not found', $response['MFEP']['MsgHeader']['Result']['ErrorDesc']);
        $this->assertEquals('Error', $response['MFEP']['MsgHeader']['Result']['Severity']);
        $this->assertEquals(0, $response['MFEP']['MsgBody']['RecCount']);
    }

    /** @test */
    public function it_builds_payment_notification_success_response()
    {
        $response = $this->service->buildPaymentNotificationResponse(
            guid: 'guid-456',
            joebppsTrx: 'TRX123',
            processDate: '2026-03-01T10:00:00',
            stmtDate: '2026-03-01',
        );

        $this->assertEquals('guid-456', $response['MFEP']['MsgHeader']['GUID']);
        $this->assertEquals('BLRPMTNTFRS', $response['MFEP']['MsgHeader']['TrsInf']['ResTyp']);
        $this->assertEquals(0, $response['MFEP']['MsgHeader']['Result']['ErrorCode']);
        $this->assertEquals('TRX123', $response['MFEP']['MsgBody']['Transactions']['TrxInf']['JOEBPPSTrx']);
    }

    /** @test */
    public function it_builds_payment_notification_error_response()
    {
        $response = $this->service->buildPaymentNotificationResponse(
            guid: 'guid-789',
            joebppsTrx: 'TRX456',
            processDate: '2026-03-01T10:00:00',
            stmtDate: '2026-03-01',
            errorCode: 1,
            errorDesc: 'Order not found',
            severity: 'Error',
        );

        $this->assertEquals(1, $response['MFEP']['MsgHeader']['Result']['ErrorCode']);
        $this->assertEquals('Error', $response['MFEP']['MsgHeader']['Result']['Severity']);
    }

    /** @test */
    public function it_builds_payment_acknowledgment_response()
    {
        $response = $this->service->buildPaymentAcknowledgmentResponse(
            guid: 'guid-ack',
            joebppsTrx: 'TRX789',
            processDate: '2026-03-01T10:00:00',
            stmtDate: '2026-03-01',
        );

        $this->assertEquals('PMTACKRS', $response['MFEP']['MsgHeader']['TrsInf']['ResTyp']);
        $this->assertEquals(0, $response['MFEP']['MsgHeader']['Result']['ErrorCode']);
        $this->assertEquals('TRX789', $response['MFEP']['MsgBody']['Transactions']['TrxInf']['JOEBPPSTrx']);
    }

    /** @test */
    public function it_builds_prepaid_validation_response()
    {
        $response = $this->service->buildPrepaidValidationResponse(
            guid: 'guid-ppv',
            billingNo: '0000000042',
            dueAmt: '25.000',
            validationCode: 'VC123',
            serviceType: 'Sales',
        );

        $this->assertEquals('BILRPREPADVALRS', $response['MFEP']['MsgHeader']['TrsInf']['ResTyp']);
        $this->assertEquals('0000000042', $response['MFEP']['MsgBody']['BillingInfo']['AcctInfo']['BillingNo']);
        $this->assertEquals('25.000', $response['MFEP']['MsgBody']['BillingInfo']['DueAmt']);
        $this->assertEquals('12345', $response['MFEP']['MsgBody']['BillingInfo']['AcctInfo']['BillerCode']);
    }

    /** @test */
    public function it_builds_generic_error_response()
    {
        $response = $this->service->buildErrorResponse('BILPULRS', 'guid-err', 99, 'Unknown error');

        $this->assertEquals('BILPULRS', $response['MFEP']['MsgHeader']['TrsInf']['ResTyp']);
        $this->assertEquals(99, $response['MFEP']['MsgHeader']['Result']['ErrorCode']);
        $this->assertEquals('Error', $response['MFEP']['MsgHeader']['Result']['Severity']);
    }

    /** @test */
    public function it_logs_messages_to_configured_channel()
    {
        Log::shouldReceive('channel')
            ->with('stack')
            ->once()
            ->andReturnSelf();
        Log::shouldReceive('info')
            ->with('Madfoat: Test message', ['key' => 'value'])
            ->once();

        $this->service->log('Test message', ['key' => 'value']);
    }
}
