<?php

namespace Trinavo\PaymentGateway\Providers;

use Illuminate\Support\ServiceProvider;
use Trinavo\PaymentGateway\Services\PaymentGatewayService;

class PaymentGatewayServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Load helper functions
        require_once __DIR__.'/../helpers.php';

        // Merge package configuration
        $this->mergeConfigFrom(
            __DIR__.'/../../config/payment-gateway.php',
            'payment-gateway'
        );

        // Bind the amount formatter contract — hosts can override by setting
        // `payment-gateway.amount_formatter` in their published config.
        $this->app->bind(
            \Trinavo\PaymentGateway\Contracts\AmountFormatter::class,
            fn ($app) => $app->make(
                config('payment-gateway.amount_formatter', \Trinavo\PaymentGateway\Support\DefaultAmountFormatter::class)
            )
        );

        // Bind the inbound billing handler contract — hosts can claim inbound
        // bills/payments for references the gateway doesn't own by setting
        // `payment-gateway.inbound_billing_handler` in their published config.
        $this->app->bind(
            \Trinavo\PaymentGateway\Contracts\InboundBillingHandler::class,
            fn ($app) => $app->make(
                config('payment-gateway.inbound_billing_handler', \Trinavo\PaymentGateway\Support\NullInboundBillingHandler::class)
            )
        );

        // Register the main service
        $this->app->singleton(PaymentGatewayService::class, function ($app) {
            return new PaymentGatewayService;
        });

        // Register the plugin registry service
        $this->app->singleton(\Trinavo\PaymentGateway\Services\PluginRegistryService::class, function ($app) {
            return new \Trinavo\PaymentGateway\Services\PluginRegistryService;
        });

        // Register the locale service
        $this->app->singleton(\Trinavo\PaymentGateway\Services\LocaleService::class, function ($app) {
            return new \Trinavo\PaymentGateway\Services\LocaleService;
        });

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Trinavo\PaymentGateway\Console\InstallPaymentGatewayCommand::class,
            ]);
        }
    }

    public function boot()
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../../config/payment-gateway.php' => config_path('payment-gateway.php'),
        ], 'config');

        // Publish migrations
        $this->publishes([
            __DIR__.'/../../database/migrations/' => database_path('migrations'),
        ], 'migrations');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        // Load routes
        $this->loadRoutesFrom(__DIR__.'/../Http/routes.php');

        // Load views
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'payment-gateway');

        // Publish views
        $this->publishes([
            __DIR__.'/../../resources/views' => resource_path('views/vendor/payment-gateway'),
        ], 'views');

        // Load translations from JSON files
        $this->loadJsonTranslationsFrom(__DIR__.'/../../lang');

        // Publish language files
        $this->publishes([
            __DIR__.'/../../lang' => $this->app->langPath(),
        ], 'lang');

        // Publish public assets (images, etc.)
        $this->publishes([
            __DIR__.'/../../public' => public_path('vendor/payment-gateway'),
        ], 'payment-gateway-assets');
    }
}
