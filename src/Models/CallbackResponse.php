<?php

namespace Trinavo\PaymentGateway\Models;

class CallbackResponse
{
    public function __construct(
        public bool $success,
        public string $orderCode,
        public ?string $transactionId = null,
        public ?string $status = null,
        public ?string $message = null,
        public array $additionalData = []
    ) {}

    /**
     * Create a successful callback response
     */
    public static function success(
        string $orderCode,
        ?string $transactionId = null,
        ?string $message = null,
        array $additionalData = []
    ): self {
        return new self(
            success: true,
            orderCode: $orderCode,
            transactionId: $transactionId,
            status: 'completed',
            message: $message ?? 'Payment completed successfully',
            additionalData: $additionalData
        );
    }

    /**
     * Create a failed callback response
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
            transactionId: null,
            status: $status ?? 'failed',
            message: $message ?? 'Payment failed',
            additionalData: $additionalData
        );
    }

    /**
     * Create a pending callback response
     */
    public static function pending(
        string $orderCode,
        ?string $transactionId = null,
        ?string $message = null,
        array $additionalData = []
    ): self {
        return new self(
            success: false,
            orderCode: $orderCode,
            transactionId: $transactionId,
            status: 'pending',
            message: $message ?? 'Payment is pending',
            additionalData: $additionalData
        );
    }

    /**
     * Create a cancelled callback response
     */
    public static function cancelled(
        string $orderCode,
        ?string $message = null,
        array $additionalData = []
    ): self {
        return new self(
            success: false,
            orderCode: $orderCode,
            transactionId: null,
            status: 'cancelled',
            message: $message ?? 'Payment was cancelled',
            additionalData: $additionalData
        );
    }

    /**
     * Check if this response indicates a cancellation
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Convert to array for backward compatibility
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'order_code' => $this->orderCode,
            'transaction_id' => $this->transactionId,
            'status' => $this->status,
            'message' => $this->message,
            ...$this->additionalData,
        ];
    }

    /**
     * Get all payment data for storing in payment_data field
     */
    public function getPaymentData(): array
    {
        $data = [];

        if ($this->transactionId) {
            $data['transaction_id'] = $this->transactionId;
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
