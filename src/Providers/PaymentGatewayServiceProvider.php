<?php

namespace Trinavo\PaymentGateway\Providers;

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

        // Register the plugin registry service
        $this->app->singleton(\Trinavo\PaymentGateway\Services\PluginRegistryService::class, function ($app) {
            return new \Trinavo\PaymentGateway\Services\PluginRegistryService;
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
    }
}
