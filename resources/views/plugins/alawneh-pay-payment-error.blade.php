@extends('payment-gateway::layouts.payment-gateway')

@section('title', __('alawneh_pay_payment_error'))

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <div class="bg-white dark:bg-gray-900 rounded-lg shadow-lg overflow-hidden">
                <!-- Header -->
                <div class="bg-gradient-to-r from-red-600 to-rose-600 text-white px-6 py-4">
                    <h1 class="text-2xl font-bold">{{ $paymentMethod->getLocalizedDisplayName() }}</h1>
                    <p class="text-red-100 mt-1">{{ __('payment_error') }}</p>
                </div>

                <div class="p-6">
                    <!-- Order Summary -->
                    <div class="mb-6">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">{{ __('order_summary') }}
                        </h2>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-300">{{ __('order_code') }}:</span>
                                <span
                                    class="font-medium text-gray-900 dark:text-gray-100">{{ $paymentOrder->order_code }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-300">{{ __('amount') }}:</span>
                                <span
                                    class="font-bold text-xl text-gray-900 dark:text-gray-100">{{ $paymentOrder->formatted_amount }}</span>
                            </div>
                            @if ($paymentOrder->getLocalizedDescription())
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-300">{{ __('description') }}:</span>
                                    <span
                                        class="font-medium text-gray-900 dark:text-gray-100">{{ $paymentOrder->getLocalizedDescription() }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Customer Details -->
                    <div class="mb-6">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">{{ __('customer_details') }}
                        </h2>
                        <div class="space-y-2">
                            @if ($paymentOrder->customer_name)
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-300">{{ __('customer_name') }}:</span>
                                    <span
                                        class="font-medium text-gray-900 dark:text-gray-100">{{ $paymentOrder->customer_name }}</span>
                                </div>
                            @endif
                            @if ($paymentOrder->customer_email)
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-300">{{ __('customer_email') }}:</span>
                                    <span
                                        class="font-medium text-gray-900 dark:text-gray-100">{{ $paymentOrder->customer_email }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <hr class="border-gray-200 dark:border-gray-700 mb-8">

                    <!-- Error Message -->
                    <div class="text-center mb-8">
                        <div class="bg-gradient-to-br from-red-50 to-rose-50 dark:from-red-900/20 dark:to-rose-900/20 border border-red-200 dark:border-red-700 rounded-lg p-8">
                            <div class="w-20 h-20 bg-red-500 rounded-full flex items-center justify-center mx-auto mb-6">
                                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>

                            <h2 class="text-2xl font-bold text-red-800 dark:text-red-300 mb-4">
                                {{ __('payment_failed') }}</h2>

                            <div class="text-gray-700 dark:text-gray-300 mb-6 max-w-lg mx-auto">
                                <p class="text-lg leading-relaxed mb-4">{{ __('alawneh_pay_payment_failed_message') }}</p>
                            </div>

                            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 mb-6 border border-red-200 dark:border-red-700">
                                <div class="text-red-700 dark:text-red-300 text-left">
                                    <p class="font-medium mb-2">{{ __('what_you_can_do') }}:</p>
                                    <ul class="list-disc list-inside space-y-1 text-sm">
                                        <li>{{ __('check_payment_details') }}</li>
                                        <li>{{ __('ensure_sufficient_balance') }}</li>
                                        <li>{{ __('try_different_payment_method') }}</li>
                                        <li>{{ __('contact_support_if_problem_persists') }}</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="text-center space-y-4">
                        <!-- Try Again Button -->
                        <a href="{{ payment_gateway_localized_url(route('payment-gateway.checkout', ['order' => $paymentOrder->order_code])) }}"
                            class="inline-flex items-center px-8 py-4 bg-purple-600 text-white text-lg font-semibold rounded-lg hover:bg-purple-700 transition-colors duration-200 shadow-lg hover:shadow-xl">
                            <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            {{ __('try_again') }}
                        </a>

                        <!-- Back to Payment Methods -->
                        <div class="pt-4">
                            <a href="{{ payment_gateway_localized_url(route('payment-gateway.checkout', ['order' => $paymentOrder->order_code])) }}"
                                class="inline-flex items-center px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors duration-200">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                </svg>
                                {{ __('back_to_payment_methods') }}
                            </a>
                        </div>

                        <!-- Cancel Payment -->
                        <div class="pt-2">
                            <a href="{{ route('payment-gateway.cancel', ['order' => $paymentOrder->order_code]) }}"
                                class="inline-flex items-center px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-200">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                                {{ __('cancel_payment') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

