<?php

namespace Trinavo\PaymentGateway\Plugins\Madfoat;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MadfoatService
{
    protected string $billerCode;

    protected string $serviceType;

    protected int $billExpiryDays;

    public function __construct(string $billerCode, string $serviceType, int $billExpiryDays = 7)
    {
        $this->billerCode = $billerCode;
        $this->serviceType = $serviceType;
        $this->billExpiryDays = $billExpiryDays;
    }

    /**
     * Generate billing number from order ID.
     * Pads to 10 digits for eFAWATEERcom compatibility.
     */
    public function generateBillingNumber(int $orderId): string
    {
        return str_pad((string) $orderId, 10, '0', STR_PAD_LEFT);
    }

    /**
     * Parse billing number back to order ID.
     */
    public function parseBillingNumber(string $billingNumber): int
    {
        return (int) $billingNumber;
    }

    /**
     * Extract the GUID from an MFEP request.
     */
    public function extractGuid(array $mfepRequest): string
    {
        return $mfepRequest['MFEP']['MsgHeader']['GUID'] ?? '';
    }

    /**
     * Extract the request type from an MFEP request.
     */
    public function extractRequestType(array $mfepRequest): string
    {
        return $mfepRequest['MFEP']['MsgHeader']['TrsInf']['ReqTyp']
            ?? $mfepRequest['MFEP']['MsgHeader']['TrsInf']['ResTyp']
            ?? '';
    }

    /**
     * Handle postpaid bill pull request (BILPULRQ → BILPULRS).
     *
     * @param  array  $mfepRequest  The full MFEP request payload
     * @param  Model|null  $order  The resolved order (or null if not found)
     * @param  array  $orderData  Extracted order data: final_total, paid, customer_name, customer_email, customer_mobile, created_at
     */
    public function buildBillPullResponse(array $mfepRequest, ?Model $order, array $orderData = []): array
    {
        $guid = $this->extractGuid($mfepRequest);
        $billingNo = $mfepRequest['MFEP']['MsgBody']['AcctInfo']['BillingNo'] ?? '';
        $billNo = $mfepRequest['MFEP']['MsgBody']['AcctInfo']['BillNo'] ?? '';

        if (! $order) {
            return $this->buildBillPullErrorResponse($guid, 1, 'Bill not found');
        }

        $isPaid = $orderData['paid'] ?? false;
        $finalTotal = $orderData['final_total'] ?? 0;
        $customerName = $orderData['customer_name'] ?? '';
        $customerEmail = $orderData['customer_email'] ?? '';
        $customerPhone = $orderData['customer_mobile'] ?? '';
        $createdAt = $orderData['created_at'] ?? now();

        // Madfoat sample shows BillStatus "BillNew" even when paid — we match their stated format.
        $billStatus = 'BillNew';
        $dueAmount = $isPaid ? '0' : number_format($finalTotal, 3, '.', '');
        $issueDate = $createdAt->format('Y-m-d\TH:i:s');

        $billRecResult = $isPaid
            ? ['ErrorCode' => 324, 'ErrorDesc' => 'Bill Has Been Paid Previously', 'Severity' => 'Error']
            : ['ErrorCode' => 0, 'ErrorDesc' => 'Success', 'Severity' => 'Info'];

        $pmtLowerUpper = $isPaid ? '0' : $dueAmount;

        return [
            'MFEP' => [
                'MsgHeader' => [
                    'TmStp' => now()->format('Y-m-d\TH:i:s'),
                    'GUID' => $guid,
                    'TrsInf' => [
                        'SdrCode' => (int) $this->billerCode,
                        'ResTyp' => 'BILPULRS',
                    ],
                    'Result' => [
                        'ErrorCode' => 0,
                        'ErrorDesc' => 'Success',
                        'Severity' => 'Info',
                    ],
                ],
                'MsgBody' => [
                    'RecCount' => 1,
                    'BillRec' => [
                        [
                            'Result' => $billRecResult,
                            'AcctInfo' => [
                                'BillingNo' => $billingNo,
                                'BillNo' => $billNo ?: $billingNo,
                            ],
                            'BillStatus' => $billStatus,
                            'DueAmount' => $dueAmount,
                            'IssueDate' => $issueDate,
                            'DueDate' => $issueDate,
                            'ServiceType' => $this->serviceType,
                            'BillType' => 'OneOff',
                            'PmtConst' => [
                                'AllowPart' => false,
                                'Lower' => $pmtLowerUpper,
                                'Upper' => $pmtLowerUpper,
                            ],
                            'AdditionalInfo' => [
                                'CustName' => Str::limit($customerName, 150, ''),
                                'FreeText' => 'Order #' . $billingNo,
                                'Email' => $customerEmail,
                                'Phone' => $customerPhone,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Build a bill-pull response for an invalid (not-found) billing number.
     * Madfoat expects a populated BillRec with inner ErrorCode 408, not an empty array.
     */
    public function buildBillPullInvalidBillResponse(string $guid, string $billingNo, string $billNo = ''): array
    {
        $now = now()->format('Y-m-d\TH:i:s');

        return [
            'MFEP' => [
                'MsgHeader' => [
                    'TmStp' => $now,
                    'GUID' => $guid,
                    'TrsInf' => [
                        'SdrCode' => (int) $this->billerCode,
                        'ResTyp' => 'BILPULRS',
                    ],
                    'Result' => [
                        'ErrorCode' => 0,
                        'ErrorDesc' => 'Success',
                        'Severity' => 'Info',
                    ],
                ],
                'MsgBody' => [
                    'RecCount' => 1,
                    'BillRec' => [
                        [
                            'Result' => [
                                'ErrorCode' => 408,
                                'ErrorDesc' => 'Invalid Billing Number',
                                'Severity' => 'Error',
                            ],
                            'AcctInfo' => [
                                'BillingNo' => $billingNo,
                                'BillNo' => $billNo ?: $billingNo,
                            ],
                            'BillStatus' => 'BillNew',
                            'DueAmount' => '0',
                            'IssueDate' => $now,
                            'DueDate' => $now,
                            'ServiceType' => $this->serviceType,
                            'BillType' => 'OneOff',
                            'PmtConst' => [
                                'AllowPart' => false,
                                'Lower' => '0',
                                'Upper' => '0',
                            ],
                            'AdditionalInfo' => [
                                'CustName' => 'NO CustName',
                                'FreeText' => 'NO FreeText',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Build a bill pull error response.
     */
    public function buildBillPullErrorResponse(string $guid, int $errorCode, string $errorDesc): array
    {
        return [
            'MFEP' => [
                'MsgHeader' => [
                    'TmStp' => now()->format('Y-m-d\TH:i:s'),
                    'GUID' => $guid,
                    'TrsInf' => [
                        'SdrCode' => (int) $this->billerCode,
                        'ResTyp' => 'BILPULRS',
                    ],
                    'Result' => [
                        'ErrorCode' => $errorCode,
                        'ErrorDesc' => $errorDesc,
                        'Severity' => 'Error',
                    ],
                ],
                'MsgBody' => [
                    'RecCount' => 0,
                    'BillRec' => [],
                ],
            ],
        ];
    }

    /**
     * Build payment notification response (BLRPMTNTFRS).
     */
    public function buildPaymentNotificationResponse(string $guid, string $joebppsTrx, string $processDate, string $stmtDate, int $errorCode = 0, string $errorDesc = 'Success', string $severity = 'Info'): array
    {
        return [
            'MFEP' => [
                'MsgHeader' => [
                    'TmStp' => now()->format('Y-m-d\TH:i:s'),
                    'GUID' => $guid,
                    'TrsInf' => [
                        'SdrCode' => (int) $this->billerCode,
                        'ResTyp' => 'BLRPMTNTFRS',
                    ],
                    'Result' => [
                        'ErrorCode' => $errorCode,
                        'ErrorDesc' => $errorDesc,
                        'Severity' => $severity,
                    ],
                ],
                'MsgBody' => [
                    'Transactions' => [
                        'TrxInf' => [
                            'JOEBPPSTrx' => $joebppsTrx,
                            'ProcessDate' => $processDate,
                            'STMTDate' => $stmtDate,
                            'Result' => [
                                'ErrorCode' => $errorCode,
                                'ErrorDesc' => $errorDesc,
                                'Severity' => $severity,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Build payment acknowledgment response (PMTACKRS).
     */
    public function buildPaymentAcknowledgmentResponse(string $guid, string $joebppsTrx, string $processDate, string $stmtDate, int $errorCode = 0, string $errorDesc = 'Success', string $severity = 'Info'): array
    {
        return [
            'MFEP' => [
                'MsgHeader' => [
                    'TmStp' => now()->format('Y-m-d\TH:i:s'),
                    'GUID' => $guid,
                    'TrsInf' => [
                        'SdrCode' => (int) $this->billerCode,
                        'ResTyp' => 'PMTACKRS',
                    ],
                    'Result' => [
                        'ErrorCode' => $errorCode,
                        'ErrorDesc' => $errorDesc,
                        'Severity' => $severity,
                    ],
                ],
                'MsgBody' => [
                    'Transactions' => [
                        'TrxInf' => [
                            'JOEBPPSTrx' => $joebppsTrx,
                            'ProcessDate' => $processDate,
                            'STMTDate' => $stmtDate,
                            'Result' => [
                                'ErrorCode' => $errorCode,
                                'ErrorDesc' => $errorDesc,
                                'Severity' => $severity,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Build prepaid validation response (BILRPREPADVALRS).
     */
    public function buildPrepaidValidationResponse(string $guid, string $billingNo, string $dueAmt, string $validationCode, string $serviceType, int $errorCode = 0, string $errorDesc = 'Success', string $severity = 'Info', string $customerName = '', string $freeText = ''): array
    {
        return [
            'MFEP' => [
                'MsgHeader' => [
                    'TmStp' => now()->format('Y-m-d\TH:i:s'),
                    'GUID' => $guid,
                    'TrsInf' => [
                        'SdrCode' => (int) $this->billerCode,
                        'ResTyp' => 'BILRPREPADVALRS',
                    ],
                    'Result' => [
                        'ErrorCode' => $errorCode,
                        'ErrorDesc' => $errorDesc,
                        'Severity' => $severity,
                    ],
                ],
                'MsgBody' => [
                    'BillingInfo' => [
                        'Result' => [
                            'ErrorCode' => $errorCode,
                            'ErrorDesc' => $errorDesc,
                            'Severity' => $severity,
                        ],
                        'AcctInfo' => [
                            'BillingNo' => $billingNo,
                            'BillerCode' => (int) $this->billerCode,
                        ],
                        'DueAmt' => $dueAmt,
                        'ValidationCode' => $validationCode,
                        'ServiceTypeDetails' => [
                            'ServiceType' => $serviceType,
                        ],
                        'AdditionalInfo' => [
                            'CustName' => $customerName !== '' ? Str::limit($customerName, 150, '') : '-----------',
                            'FreeText' => $freeText !== '' ? $freeText : '-----------',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Build a generic error response for any response type.
     */
    public function buildErrorResponse(string $responseType, string $guid, int $errorCode, string $errorDesc): array
    {
        return [
            'MFEP' => [
                'MsgHeader' => [
                    'TmStp' => now()->format('Y-m-d\TH:i:s'),
                    'GUID' => $guid,
                    'TrsInf' => [
                        'SdrCode' => (int) $this->billerCode,
                        'ResTyp' => $responseType,
                    ],
                    'Result' => [
                        'ErrorCode' => $errorCode,
                        'ErrorDesc' => $errorDesc,
                        'Severity' => 'Error',
                    ],
                ],
            ],
        ];
    }

    /**
     * Log a Madfoat request/response.
     */
    public function log(string $message, array $context = []): void
    {
        Log::info("Madfoat: {$message}", $context);
    }
}
