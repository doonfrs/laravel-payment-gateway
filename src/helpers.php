<?php

use Trinavo\PaymentGateway\Services\LocaleService;

if (! function_exists('payment_gateway_localized_url')) {
    /**
     * Generate a localized URL for the payment gateway
     */
    function payment_gateway_localized_url(?string $url = null, ?string $locale = null): string
    {
        $service = app(LocaleService::class);

        return $service->getLocalizedUrl($url, $locale);
    }
}

if (! function_exists('payment_gateway_url_locale')) {
    /**
     * Get locale from a URL
     */
    function payment_gateway_url_locale(string $url): ?string
    {
        $service = app(LocaleService::class);

        return $service->getUrlLocale($url);
    }
}

if (! function_exists('payment_gateway_current_locale')) {
    /**
     * Get current locale for payment gateway
     */
    function payment_gateway_current_locale(): string
    {
        return app()->getLocale();
    }
}
