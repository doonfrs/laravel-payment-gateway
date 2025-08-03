<?php

use Illuminate\Support\Facades\Route;
use Trinavo\PaymentGateway\Http\Controllers\PaymentController;

Route::group([
    'prefix' => config('payment-gateway.routes.prefix', 'payment-gateway'),
    'middleware' => config('payment-gateway.routes.middleware', ['web']),
    'as' => 'payment-gateway.',
], function () {
    // Checkout page - shows available payment methods
    Route::get('checkout/{order}', [PaymentController::class, 'checkout'])
        ->name('checkout');

    // Process payment with selected method
    Route::post('checkout/{order}/process', [PaymentController::class, 'processPayment'])
        ->name('process');

    // Payment callback from external gateways
    Route::any('callback/{plugin}', [PaymentController::class, 'callback'])
        ->name('callback');

    // Payment result pages
    Route::get('success/{order}', [PaymentController::class, 'success'])
        ->name('success');

    Route::get('failure/{order}', [PaymentController::class, 'failure'])
        ->name('failure');

    Route::get('status/{order}', [PaymentController::class, 'status'])
        ->name('status');

    // Dummy payment actions for testing
    Route::get('dummy/{order}/{action}', [PaymentController::class, 'dummyAction'])
        ->name('dummy-action')
        ->where('action', 'success|failure|callback');

    // Offline payment confirmation
    Route::post('offline/{order}/confirm', [PaymentController::class, 'offlineConfirm'])
        ->name('offline-confirm');
});
