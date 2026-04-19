<?php

namespace Trinavo\PaymentGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $plugin
 * @property string $action
 * @property int|null $payment_order_id
 * @property array $payload
 * @property array|null $headers
 * @property string|null $ip_address
 * @property int|null $response_status
 * @property array|null $response_body
 * @property string|null $handler_exception
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Trinavo\PaymentGateway\Models\PaymentOrder|null $paymentOrder
 */
class PaymentGatewayInboundRequest extends Model
{
    protected $fillable = [
        'plugin',
        'action',
        'payment_order_id',
        'payload',
        'headers',
        'ip_address',
        'response_status',
        'response_body',
        'handler_exception',
    ];

    protected $casts = [
        'payload' => 'array',
        'headers' => 'array',
        'response_body' => 'array',
    ];

    public function paymentOrder(): BelongsTo
    {
        return $this->belongsTo(PaymentOrder::class);
    }
}
