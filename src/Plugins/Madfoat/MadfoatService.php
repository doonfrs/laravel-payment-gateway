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

        $billStatus = $isPaid ? 'Paid' : 'BillNew';
        $dueAmount = $isPaid ? '0.000' : number_format($finalTotal, 3, '.', '');
        $issueDate = $createdAt->format('Y-m-d\TH:i:s');

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
                            'Result' => [
                                'ErrorCode' => 0,
                                'ErrorDesc' => 'Success',
                                'Severity' => 'Info',
                            ],
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
                                'Lower' => $isPaid ? '0.000' : $dueAmount,
                                'Upper' => $isPaid ? '0.000' : $dueAmount,
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
    public function buildPrepaidValidationResponse(string $guid, string $billingNo, string $dueAmt, string $validationCode, string $serviceType, int $errorCode = 0, string $errorDesc = 'Success', string $severity = 'Info'): array
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
                            'BillerCode' => $this->billerCode,
                        ],
                        'DueAmt' => $dueAmt,
                        'ValidationCode' => $validationCode,
                        'ServiceTypeDetails' => [
                            'ServiceType' => $serviceType,
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
