@extends('payment-gateway::layouts.payment-gateway')

@section('title', __('Payment Initialization Failed'))

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <div class="bg-white dark:bg-gray-900 rounded-lg shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-red-600 to-red-800 text-white px-6 py-4">
                    <h1 class="text-2xl font-bold">{{ __('Payment Initialization Failed') }}</h1>
                    <p class="text-red-100 mt-1">{{ __('Failed to initialize Paymob payment') }}</p>
                </div>
                <div class="p-6">
                    <div class="mb-6">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">{{ __('Order Summary') }}
                        </h2>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-300">{{ __('Order Code') }}:</span>
                                <span
                                    class="font-medium text-gray-900 dark:text-gray-100">{{ $paymentOrder->order_code }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-300">{{ __('Amount') }}:</span>
                                <span
                                    class="font-bold text-xl text-green-600 dark:text-green-400">{{ number_format($paymentOrder->amount, 2) }} {{ $paymentOrder->currency }}</span>
                            </div>
                            @if ($paymentOrder->description)
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-300">{{ __('Description') }}:</span>
                                    <span
                                        class="font-medium text-gray-900 dark:text-gray-100">{{ $paymentOrder->description }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="mb-6">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">{{ __('Customer Details') }}
                        </h2>
                        <div class="space-y-2">
                            @if ($paymentOrder->customer_name)
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-300">{{ __('Customer Name') }}:</span>
                                    <span
                                        class="font-medium text-gray-900 dark:text-gray-100">{{ $paymentOrder->customer_name }}</span>
                                </div>
                            @endif
                            @if ($paymentOrder->customer_email)
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-300">{{ __('Customer Email') }}:</span>
                                    <span
                                        class="font-medium text-gray-900 dark:text-gray-100">{{ $paymentOrder->customer_email }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <hr class="border-gray-200 dark:border-gray-700 mb-8">

                    <!-- Error Message -->
                    <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-lg p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
                                    {{ __('Payment Initialization Failed') }}
                                </h3>
                                <div class="mt-2 text-sm text-red-700 dark:text-red-300">
                                    <p>
                                        @php
                                            $userFriendlyMessage = __('We were unable to initialize your Paymob payment. Please try again or use a different payment method.');

                                            // Check for specific error types and provide appropriate messages
                                            if (isset($errorMessage)) {
                                                if (
                                                    str_contains(strtolower($errorMessage), 'unauthorized') ||
                                                    str_contains(strtolower($errorMessage), 'invalid') ||
                                                    str_contains(strtolower($errorMessage), 'api_key') ||
                                                    str_contains(strtolower($errorMessage), 'token')
                                                ) {
                                                    $userFriendlyMessage = __('Invalid Paymob configuration. Please check your API credentials.');
                                                } elseif (
                                                    str_contains(strtolower($errorMessage), 'network') ||
                                                    str_contains(strtolower($errorMessage), 'timeout') ||
                                                    str_contains(strtolower($errorMessage), 'connection')
                                                ) {
                                                    $userFriendlyMessage = __('Network error occurred. Please check your internet connection and try again.');
                                                } elseif (
                                                    str_contains(strtolower($errorMessage), 'duplicate') ||
                                                    str_contains(strtolower($errorMessage), 'already exists')
                                                ) {
                                                    $userFriendlyMessage = __('This order has already been processed. Please create a new order.');
                                                } elseif (
                                                    str_contains(strtolower($errorMessage), 'currency') ||
                                                    str_contains(strtolower($errorMessage), 'invalid currency')
                                                ) {
                                                    $userFriendlyMessage = __('Invalid currency for this payment method. Please contact support.');
                                                } else {
                                                    // Show the actual error message if it's user-friendly
                                                    $userFriendlyMessage = $errorMessage;
                                                }
                                            }
                                        @endphp
                                        {{ $userFriendlyMessage }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col sm:flex-row gap-4">
                        <button onclick="window.location.reload()"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-6 rounded-md text-sm flex-1 inline-flex items-center justify-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            {{ __('Try Again') }}
                        </button>

                        <button onclick="window.location.href='{{ $failureUrl }}'"
                            class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-3 px-6 rounded-md text-sm flex-1 inline-flex items-center justify-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            {{ __('Go Back') }}
                        </button>
                    </div>

                    <!-- Help Section -->
                    <div class="text-center mt-6">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('If the problem persists, please contact our support team for assistance.') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection


