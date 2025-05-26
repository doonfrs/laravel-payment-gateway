<?php

namespace Trinavo\PaymentGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PaymentOrder extends Model
{
    protected $fillable = [
        'order_code',
        'amount',
        'currency',
        'status',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_data',
        'description',
        'success_callback',
        'failure_callback',
        'success_url',
        'failure_url',
        'payment_method_id',
        'external_transaction_id',
        'payment_data',
        'ignored_plugins',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'customer_data' => 'array',
        'payment_data' => 'array',
        'ignored_plugins' => 'array',
        'paid_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->order_code)) {
                $model->order_code = 'PO-'.strtoupper(Str::random(10));
            }

            if (empty($model->currency)) {
                $model->currency = config('payment-gateway.default_currency', 'USD');
            }
        });
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function markAsProcessing(): void
    {
        $this->update(['status' => self::STATUS_PROCESSING]);
    }

    public function markAsCompleted(array $paymentData = []): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'paid_at' => now(),
            'payment_data' => array_merge($this->payment_data ?? [], $paymentData),
        ]);
    }

    public function markAsFailed(array $paymentData = []): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'payment_data' => array_merge($this->payment_data ?? [], $paymentData),
        ]);
    }

    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 2).' '.$this->currency;
    }

    /**
     * Check if a plugin is ignored for this payment order
     */
    public function isPluginIgnored(string $pluginName): bool
    {
        return in_array($pluginName, $this->ignored_plugins ?? []);
    }

    /**
     * Get the list of ignored plugins
     */
    public function getIgnoredPlugins(): array
    {
        return $this->ignored_plugins ?? [];
    }

    /**
     * Set the ignored plugins for this payment order
     */
    public function setIgnoredPlugins(array $plugins): void
    {
        $this->update(['ignored_plugins' => $plugins]);
    }
}
