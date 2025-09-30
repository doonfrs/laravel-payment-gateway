<?php

namespace Trinavo\PaymentGateway\Services;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;

/**
 * Service class for handling locale-related operations in the payment gateway package
 *
 * This service provides functionality for managing locales without database interaction:
 * - URL localization
 * - Locale detection from URL
 * - Available locales from config
 * - Default locale handling
 */
class LocaleService
{
    /**
     * Runtime override for default locale
     */
    protected ?string $defaultLocale = null;

    /**
     * Runtime override for available locales
     */
    protected ?array $availableLocales = null;

    /**
     * Set the default locale at runtime
     *
     * @return $this
     */
    public function setDefaultLocale(string $locale): self
    {
        $this->defaultLocale = $locale;

        return $this;
    }

    /**
     * Set the available locales at runtime
     *
     * @return $this
     */
    public function setAvailableLocales(array $locales): self
    {
        $this->availableLocales = $locales;

        return $this;
    }

    /**
     * Extract locale code from a given URL
     *
     * @param  string  $url  The URL to analyze
     * @return string|null The locale code if found in the URL, null otherwise
     */
    public function getUrlLocale(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        $segments = array_values(array_filter(explode('/', $path), 'strlen'));
        $availableLocales = $this->getAvailableLocales();

        // Check if any segment is a locale
        foreach ($segments as $segment) {
            if (in_array($segment, $availableLocales)) {
                return $segment;
            }
        }

        return null;
    }

    /**
     * Generate a localized URL for a given locale
     *
     * @param  string|null  $url  The base URL to localize (defaults to current URL)
     * @param  string|null  $locale  The target locale (defaults to current locale)
     * @return string The localized URL
     */
    public function getLocalizedUrl(?string $url = null, ?string $locale = null): string
    {
        $url = $url ?? URL::current();
        $locale = $locale ?? App::getLocale();

        $path = parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);

        if (! $path) {
            return url($locale);
        }

        // Clean and split the path
        $segments = array_values(array_filter(explode('/', $path), 'strlen'));
        $availableLocales = $this->getAvailableLocales();

        // Check if any segment is a locale and remove it
        foreach ($segments as $index => $segment) {
            if (in_array($segment, $availableLocales)) {
                unset($segments[$index]);
                break;
            }
        }

        // Reindex array and add new locale at the beginning
        $segments = array_values($segments);

        if ($locale !== $this->getDefaultLocale()) {
            array_unshift($segments, $locale);
        }

        $url = url(implode('/', $segments));

        if ($query) {
            $url .= '?'.$query;
        }

        return $url;
    }

    /**
     * Get array of available locale codes from config or runtime override
     *
     * @return array Array of locale codes
     */
    public function getAvailableLocales(): array
    {
        return $this->availableLocales ?? config('payment-gateway.locale.available_locales', ['en']);
    }

    /**
     * Get the default locale code from config or runtime override
     *
     * @return string The default locale code
     */
    public function getDefaultLocale(): string
    {
        return $this->defaultLocale ?? config('payment-gateway.locale.default_locale', 'en');
    }

    /**
     * Check if locale detection from URL is enabled
     */
    public function shouldDetectFromUrl(): bool
    {
        return config('payment-gateway.locale.detect_from_url', true);
    }

    /**
     * Detect and set locale from current URL if enabled
     */
    public function detectAndSetLocale(): void
    {
        if (! $this->shouldDetectFromUrl()) {
            return;
        }

        $locale = $this->getUrlLocale(URL::current());

        if ($locale && in_array($locale, $this->getAvailableLocales())) {
            App::setLocale($locale);
        } else {
            App::setLocale($this->getDefaultLocale());
        }
    }
}
