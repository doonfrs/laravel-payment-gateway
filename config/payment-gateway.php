<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Available Payment Plugins
    |--------------------------------------------------------------------------
    |
    | This array contains all the available payment plugins that can be
    | registered as payment methods. Each plugin must implement the
    | PaymentPluginInterface.
    |
    | Simply list the plugin class names. Plugin keys will be automatically
    | generated from the class names (e.g., StripePaymentPlugin -> stripe).
    |
    | Example:
    | \App\PaymentPlugins\StripePaymentPlugin::class,
    | \App\PaymentPlugins\PayPalPaymentPlugin::class,
    |
    */
    'plugins' => [
        \Trinavo\PaymentGateway\Plugins\Dummy\DummyPaymentPlugin::class,
        // Add your custom payment plugins here
        // \App\PaymentPlugins\StripePaymentPlugin::class,
        // \App\PaymentPlugins\PayPalPaymentPlugin::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | The default currency to use for payment orders when none is specified.
    |
    */
    'default_currency' => 'USD',

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the route prefix and middleware for payment gateway routes.
    |
    */
    'routes' => [
        'prefix' => 'payment-gateway',
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | View Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the view namespace and theme for payment gateway views.
    |
    */
    'views' => [
        'namespace' => 'payment-gateway',
        'theme' => 'default',
    ],
];
