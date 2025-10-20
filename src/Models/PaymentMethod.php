<?php

namespace Trinavo\PaymentGateway\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

/**
 * @method static Builder|PaymentMethod enabled()
 * @method static Builder|PaymentMethod ordered()
 */
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

    /**
     * Get the localized display name
     */
    public function getLocalizedDisplayName(): string
    {
        if (! $this->display_name) {
            return $this->name;
        }

        // Check if display_name is JSON format
        $decoded = json_decode($this->display_name, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            // It's JSON, get the current locale
            $locale = app()->getLocale();

            // Return the localized text if available, otherwise fallback to 'en' or first available
            if (isset($decoded[$locale])) {
                return $decoded[$locale];
            } elseif (isset($decoded['en'])) {
                return $decoded['en'];
            } elseif (! empty($decoded)) {
                $firstValue = reset($decoded);

                return $firstValue ?: $this->name; // Return first available translation or fallback to name
            }
        }

        // Not JSON or no valid translations, return as plain text
        return is_string($this->display_name) ? $this->display_name : $this->name;
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

    public function getSetting(string $key, $default = null)
    {
        $setting = $this->settings()->where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        $value = $setting->value;

        if ($setting->encrypted) {
            // Handle NULL or empty values gracefully
            if ($value === null || $value === '') {
                return $default;
            }
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
