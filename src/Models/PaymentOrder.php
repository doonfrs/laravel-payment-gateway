<?php

namespace Trinavo\PaymentGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $order_code
 * @property float $amount
 * @property string $currency
 * @property string $status
 * @property string|null $customer_name
 * @property string|null $customer_email
 * @property string|null $customer_phone
 * @property array|null $customer_data
 * @property string|null $description
 * @property string|null $success_callback
 * @property string|null $failure_callback
 * @property string|null $success_url
 * @property string|null $failure_url
 * @property int|null $payment_method_id
 * @property string|null $external_transaction_id
 * @property array|null $payment_data
 * @property array|null $ignored_plugins
 * @property \Illuminate\Support\Carbon|null $paid_at
 * @property bool $refunded
 * @property array|null $refund_data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Trinavo\PaymentGateway\Models\PaymentMethod|null $paymentMethod
 * @property-read string $formatted_amount
 */
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
        'refunded',
        'refund_data',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'customer_data' => 'array',
        'payment_data' => 'array',
        'ignored_plugins' => 'array',
        'paid_at' => 'datetime',
        'refunded' => 'boolean',
        'refund_data' => 'array',
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

    /**
     * Get the localized description
     */
    public function getLocalizedDescription(): ?string
    {
        if (! $this->description) {
            return null;
        }

        // Check if description is JSON format
        $decoded = json_decode($this->description, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            // It's JSON, get the current locale
            $locale = app()->getLocale();

            // Return the localized text if available, otherwise fallback to 'en' or first available
            if (isset($decoded[$locale])) {
                return $decoded[$locale];
            } elseif (isset($decoded['en'])) {
                return $decoded['en'];
            } elseif (! empty($decoded)) {
                return reset($decoded); // Return first available translation
            }
        }

        // Not JSON or no valid translations, return as plain text
        return $this->description;
    }

    /**
     * Mark the payment order as refunded
     */
    public function markAsRefunded(array $refundData = []): void
    {
        $this->update([
            'refunded' => true,
            'refund_data' => array_merge($this->refund_data ?? [], $refundData),
        ]);
    }

    /**
     * Check if the payment order is refunded
     */
    public function isRefunded(): bool
    {
        return $this->refunded === true;
    }
}
