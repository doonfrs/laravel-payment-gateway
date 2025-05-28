<?php

namespace Trinavo\PaymentGateway\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class PaymentMethod extends Model
{
    protected $fillable = [
        'name',
        'plugin_class',
        'enabled',
        'display_name',
        'description',
        'logo_url',
        'sort_order',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function settings(): HasMany
    {
        return $this->hasMany(PaymentMethodSetting::class);
    }

    public function paymentOrders(): HasMany
    {
        return $this->hasMany(PaymentOrder::class);
    }

    public function getSetting(string $key, $default = null)
    {
        $setting = $this->settings()->where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        $value = $setting->value;

        if ($setting->encrypted) {
            $value = Crypt::decryptString($value);
        }

        return $value;
    }

    public function setSetting(string $key, $value, bool $encrypted = false): void
    {
        if ($encrypted && $value !== null) {
            $value = Crypt::encryptString($value);
        }

        $this->settings()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'encrypted' => $encrypted,
            ]
        );
    }

    public function getSettings(): array
    {
        $settings = [];

        foreach ($this->settings as $setting) {
            $value = $setting->value;

            if ($setting->encrypted) {
                $value = Crypt::decryptString($value);
            }

            $settings[$setting->key] = $value;
        }

        return $settings;
    }

    public function getPluginInstance()
    {
        if (! class_exists($this->plugin_class)) {
            throw new \Exception("Payment plugin class {$this->plugin_class} not found");
        }

        return new $this->plugin_class($this);
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
