<?php

namespace Trinavo\PaymentGateway\Tests\Unit\MadfoatDirectPay;

use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Trinavo\PaymentGateway\Plugins\MadfoatDirectPay\MadfoatDirectPayService;
use Trinavo\PaymentGateway\Providers\PaymentGatewayServiceProvider;

class MadfoatDirectPayServiceTest extends TestCase
{
    protected MadfoatDirectPayService $service;

    protected function getPackageProviders($app)
    {
        return [PaymentGatewayServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MadfoatDirectPayService;
    }

    #[Test]
    public function getGatewayUrl_returns_staging_in_test_mode_and_production_otherwise(): void
    {
        $this->assertSame(
            'https://staging.efawateercom.jo/DirectPayService/DirectPay.aspx',
            $this->service->getGatewayUrl(testMode: true),
        );
        $this->assertSame(
            'https://www.efawateercom.jo/DirectPayService/DirectPay.aspx',
            $this->service->getGatewayUrl(testMode: false),
        );
    }

    #[Test]
    public function buildRequestParams_orders_fields_per_spec_and_appends_bcrypt_hash(): void
    {
        $params = [
            'BilrTrxNo' => 'PO-ABCDE12345',
            'BillerCode' => '23',
            'ServiceCode' => '50',
            'PaymentType' => '1',
            'Currency' => 'JOD',
            'BillingNo' => 'UID-USER-001',
            'PrepaidCatCode' => '',
            'Amount' => '5.000',
            'StatmntNartive' => '',
            'CustEmail' => 'user@example.com',
            'Language' => 'EN',
            'OtherDetails' => '',
        ];

        $signed = $this->service->buildRequestParams($params, 'secret-token-xyz');

        // 12 protocol fields + SecureHash = 13 pipe-separated values
        $this->assertCount(13, explode('|', $signed));

        // $expectedPrefix = the 12 protocol fields joined by | (paramsString) + the | separator before the hash.
        $expectedPrefix = 'PO-ABCDE12345|23|50|1|JOD|UID-USER-001||5.000||user@example.com|EN||';
        $this->assertStringStartsWith($expectedPrefix, $signed);

        // paramsString is everything before the final | (the | that separates the hash).
        $paramsString = substr($expectedPrefix, 0, -1);
        $hash = substr($signed, strlen($expectedPrefix));
        $this->assertTrue(
            password_verify($paramsString.'secret-token-xyz', $hash),
            'SecureHash must verify against the exact paramsString + secret per spec §2.1.1.3',
        );
    }

    #[Test]
    public function buildRequestUrl_assembles_endpoint_and_urlencoded_request_params(): void
    {
        $params = [
            'BilrTrxNo' => 'PO-1',
            'BillerCode' => '10',
            'ServiceCode' => '2',
            'PaymentType' => '1',
            'Currency' => 'JOD',
            'BillingNo' => 'UID-A',
            'Amount' => '1.000',
            'Language' => 'AR',
        ];

        $url = $this->service->buildRequestUrl($params, 'sek', testMode: true);

        $this->assertStringStartsWith(
            'https://staging.efawateercom.jo/DirectPayService/DirectPay.aspx?RequestParams=',
            $url,
        );

        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertArrayHasKey('RequestParams', $query);
        // 13 pipe-separated values once decoded
        $this->assertCount(13, explode('|', $query['RequestParams']));
    }

    #[Test]
    public function signRequestParams_and_verifyResponseSignature_round_trip(): void
    {
        $payload = 'PO-1|10|2|1|JOD|UID|1.000';
        $secret = 's3cr3t';

        $hash = $this->service->signRequestParams($payload, $secret);

        $this->assertTrue(
            $this->service->verifyResponseSignature($payload, $hash, $secret),
            'A hash signed with the same secret must verify',
        );
        $this->assertFalse(
            $this->service->verifyResponseSignature($payload, $hash, 'wrong-secret'),
        );
        $this->assertFalse(
            $this->service->verifyResponseSignature($payload.'-tampered', $hash, $secret),
        );
    }

    #[Test]
    public function verifyResponseSignature_accepts_2a_dollar_4_prefix_produced_by_other_languages(): void
    {
        // eFAWATEERcom samples use $2a$04$ (low work factor, common in .NET/Java).
        // PHP's password_verify must still accept these.
        $payload = 'hello-world';
        $secret = 'pepper';
        $crypted2a = password_hash($payload.$secret, PASSWORD_BCRYPT, ['cost' => 4]);

        // Force $2a$ prefix (PHP usually emits $2y$); the two are interoperable.
        $crypted2a = '$2a$'.substr($crypted2a, 4);

        $this->assertTrue(
            $this->service->verifyResponseSignature($payload, $crypted2a, $secret),
        );
    }

    #[Test]
    public function verifyResponseSignature_rejects_empty_hash_or_secret(): void
    {
        $this->assertFalse($this->service->verifyResponseSignature('payload', '', 'secret'));
        $this->assertFalse($this->service->verifyResponseSignature('payload', 'somehash', ''));
    }

    #[Test]
    public function sanitize_strips_each_forbidden_character_individually(): void
    {
        foreach (['~', '"', "'", '&', '#', '%'] as $char) {
            $this->assertStringNotContainsString(
                $char,
                $this->service->sanitize("hello{$char}world"),
                "Character '{$char}' must be stripped",
            );
        }
    }

    #[Test]
    public function sanitize_leaves_normal_characters_untouched(): void
    {
        $this->assertSame(
            'PO-ABCDE12345',
            $this->service->sanitize('PO-ABCDE12345'),
        );
        $this->assertSame(
            'user.name+test@example.co',
            $this->service->sanitize('user.name+test@example.co'),
        );
    }

    #[Test]
    public function parseResponseParams_decodes_a_success_response_per_spec_sample(): void
    {
        // Sample from PDF §2.1.2.2 (Success Response). 6 elements: last is SecureHash.
        $raw = '5461|1|2017052332||2|$2a$04$0XSPQde0lqeGE6ttTsYo3OSJRny5KfsOxkMpEf8rbMfCWerCvYVNC';

        $parsed = $this->service->parseResponseParams($raw);

        $this->assertSame('5461', $parsed['BilrTrxNo']);
        $this->assertSame('1', $parsed['TrxStatus']);
        $this->assertSame('2017052332', $parsed['DirectPayTrxNo']);
        $this->assertNull($parsed['Amount']);
        $this->assertSame('2', $parsed['PaymentStatus']);
        $this->assertNull($parsed['OtherDetails']);
        $this->assertSame('$2a$04$0XSPQde0lqeGE6ttTsYo3OSJRny5KfsOxkMpEf8rbMfCWerCvYVNC', $parsed['SecureHash']);
        $this->assertSame('5461|1|2017052332||2', $parsed['_paramsWithoutHash']);
    }

    #[Test]
    public function parseResponseParams_decodes_a_truncated_failure_response(): void
    {
        // Sample from PDF §2.1.2.2 (Failed Response). Only 3 elements.
        $raw = '5461|3|$2a$04$Og8THTV27sRAvXi3q9tksuRsWmttYrqYtmf2PBBfIdIs/GU4Uc6f.';

        $parsed = $this->service->parseResponseParams($raw);

        $this->assertSame('5461', $parsed['BilrTrxNo']);
        $this->assertSame('3', $parsed['TrxStatus']);
        $this->assertNull($parsed['DirectPayTrxNo']);
        $this->assertNull($parsed['Amount']);
        $this->assertNull($parsed['PaymentStatus']);
        $this->assertNull($parsed['OtherDetails']);
        $this->assertSame('$2a$04$Og8THTV27sRAvXi3q9tksuRsWmttYrqYtmf2PBBfIdIs/GU4Uc6f.', $parsed['SecureHash']);
        $this->assertSame('5461|3', $parsed['_paramsWithoutHash']);
    }

    #[Test]
    public function parseResponseParams_returns_empty_shape_for_garbage_input(): void
    {
        foreach (['', 'no-pipes-here'] as $raw) {
            $parsed = $this->service->parseResponseParams($raw);
            $this->assertNull($parsed['BilrTrxNo']);
            $this->assertNull($parsed['TrxStatus']);
            $this->assertNull($parsed['SecureHash']);
        }
    }

    #[Test]
    public function getStatusCodeMessage_covers_all_documented_codes_1_through_20(): void
    {
        for ($code = 1; $code <= 20; $code++) {
            $message = $this->service->getStatusCodeMessage($code);
            $this->assertNotSame('', $message);
            $this->assertStringNotContainsString('Unknown', $message, "Code {$code} should have a documented message");
        }
    }

    #[Test]
    public function getStatusCodeMessage_falls_back_for_unknown_codes(): void
    {
        $this->assertStringContainsString('Unknown', $this->service->getStatusCodeMessage(999));
    }

    #[Test]
    public function formatAmount_produces_18_3_decimal_format(): void
    {
        $this->assertSame('10.511', $this->service->formatAmount(10.511));
        $this->assertSame('5.000', $this->service->formatAmount(5));
        $this->assertSame('0.001', $this->service->formatAmount(0.001));
        $this->assertSame('1234.567', $this->service->formatAmount(1234.567));
    }

    #[Test]
    public function buildRequestParams_sanitises_forbidden_characters_in_dynamic_fields(): void
    {
        $params = [
            'BilrTrxNo' => 'PO-1',
            'BillerCode' => '10',
            'ServiceCode' => '2',
            'PaymentType' => '1',
            'Currency' => 'JOD',
            'BillingNo' => 'UID-A',
            'Amount' => '1.000',
            'StatmntNartive' => "Hello & welcome 'friend'",  // contains forbidden chars
            'Language' => 'EN',
            'OtherDetails' => 'Why #1 100%?',                // contains forbidden chars
        ];

        $signed = $this->service->buildRequestParams($params, 'sek');

        $this->assertStringNotContainsString('&', $signed);
        $this->assertStringNotContainsString("'", $signed);
        $this->assertStringNotContainsString('#', $signed);
        $this->assertStringNotContainsString('%', $signed);

        // The unsanitised parts still appear (proves sanitisation is targeted).
        $this->assertStringContainsString('Hello  welcome friend', $signed);
        $this->assertStringContainsString('Why 1 100', $signed);
    }
}
