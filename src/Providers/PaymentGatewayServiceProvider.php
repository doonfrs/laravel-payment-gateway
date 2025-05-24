<?php

namespace Trinavo\PaymentGateway\Providers;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\ServiceProvider;
use Trinavo\PaymentGateway\Services\PaymentGatewayService;

class PaymentGatewayServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Merge package configuration
        $this->mergeConfigFrom(
            __DIR__.'/../../config/payment-gateway.php',
            'payment-gateway'
        );

        // Register the main service
        $this->app->singleton(PaymentGatewayService::class, function ($app) {
            return new PaymentGatewayService;
        });

        // Register facade alias
        $this->app->alias(PaymentGatewayService::class, 'payment-gateway');
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

        // Load translations
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'payment-gateway');

        Lang::addNamespace('payment-gateway', realpath(__DIR__.'/../../lang'));

        // Load views
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'payment-gateway');

        // Publish views
        $this->publishes([
            __DIR__.'/../../resources/views' => resource_path('views/vendor/payment-gateway'),
        ], 'views');

        // Publish translation files
        $this->publishes([
            __DIR__.'/../../lang' => lang_path('vendor/payment-gateway'),
        ], 'payment-gateway-translations');

    }
}
