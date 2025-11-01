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
        \Trinavo\PaymentGateway\Plugins\Offline\OfflinePaymentPlugin::class,
        \Trinavo\PaymentGateway\Plugins\Moyasar\MoyasarPaymentPlugin::class,
        \Trinavo\PaymentGateway\Plugins\Tabby\TabbyPaymentPlugin::class,
        \Trinavo\PaymentGateway\Plugins\Tamara\TamaraPaymentPlugin::class,
        \Trinavo\PaymentGateway\Plugins\AlawnehPay\AlawnehPayPaymentPlugin::class,
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

    /*
    |--------------------------------------------------------------------------
    | Locale Configuration
    |--------------------------------------------------------------------------
    |
    | Configure locale handling for payment gateway routes.
    | - default_locale: The default locale code (e.g., 'en')
    | - available_locales: Array of available locale codes (e.g., ['en', 'ar'])
    | - detect_from_url: Automatically detect locale from URL path
    |
    */
    'locale' => [
        'default_locale' => 'en',
        'available_locales' => ['en', 'ar'],
        'detect_from_url' => true,
    ],
];
