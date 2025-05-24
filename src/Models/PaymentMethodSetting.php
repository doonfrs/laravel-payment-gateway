<?php

namespace Trinavo\PaymentGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMethodSetting extends Model
{
    protected $fillable = [
        'payment_method_id',
        'key',
        'value',
        'encrypted',
    ];

    protected $casts = [
        'encrypted' => 'boolean',
    ];

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
