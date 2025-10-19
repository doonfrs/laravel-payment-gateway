<?php

namespace Trinavo\PaymentGateway\Models;

class RefundResponse
{
    public function __construct(
        public bool $success,
        public string $orderCode,
        public ?string $refundTransactionId = null,
        public ?string $originalTransactionId = null,
        public ?float $refundedAmount = null,
        public ?string $status = null,
        public ?string $message = null,
        public array $additionalData = []
    ) {}

    /**
     * Create a successful refund response
     */
    public static function success(
        string $orderCode,
        float $refundedAmount,
        ?string $refundTransactionId = null,
        ?string $originalTransactionId = null,
        ?string $message = null,
        array $additionalData = []
    ): self {
        return new self(
            success: true,
            orderCode: $orderCode,
            refundTransactionId: $refundTransactionId,
            originalTransactionId: $originalTransactionId,
            refundedAmount: $refundedAmount,
            status: 'refunded',
            message: $message ?? 'Refund completed successfully',
            additionalData: $additionalData
        );
    }

    /**
     * Create a failed refund response
     */
    public static function failure(
        string $orderCode,
        ?string $message = null,
        ?string $status = null,
        array $additionalData = []
    ): self {
        return new self(
            success: false,
            orderCode: $orderCode,
            refundTransactionId: null,
            originalTransactionId: null,
            refundedAmount: null,
            status: $status ?? 'refund_failed',
            message: $message ?? 'Refund failed',
            additionalData: $additionalData
        );
    }

    /**
     * Convert to array for backward compatibility
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'order_code' => $this->orderCode,
            'refund_transaction_id' => $this->refundTransactionId,
            'original_transaction_id' => $this->originalTransactionId,
            'refunded_amount' => $this->refundedAmount,
            'status' => $this->status,
            'message' => $this->message,
            ...$this->additionalData,
        ];
    }

    /**
     * Get all refund data for storing in refund_data field
     */
    public function getRefundData(): array
    {
        $data = [];

        if ($this->refundTransactionId) {
            $data['refund_transaction_id'] = $this->refundTransactionId;
        }

        if ($this->originalTransactionId) {
            $data['original_transaction_id'] = $this->originalTransactionId;
        }

        if ($this->refundedAmount !== null) {
            $data['refunded_amount'] = $this->refundedAmount;
        }

        if ($this->status) {
            $data['status'] = $this->status;
        }

        if ($this->message) {
            $data['message'] = $this->message;
        }

        return array_merge($data, $this->additionalData);
    }
}
