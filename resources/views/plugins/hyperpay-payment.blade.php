@extends('payment-gateway::layouts.payment-gateway')

@section('title', __('hyperpay_payment_gateway'))

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <div class="bg-white dark:bg-gray-900 rounded-lg shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-6 py-4">
                    <h1 class="text-2xl font-bold">{{ $paymentMethod->getLocalizedDisplayName() }}</h1>
                    <p class="text-blue-100 mt-1">{{ __('pay_securely_with_hyperpay') }}</p>
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
                                    class="font-bold text-xl text-green-600 dark:text-green-400">{{ $paymentOrder->formatted_amount }}</span>
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

                    <!-- Payment Form -->
                    <div class="mb-8">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">{{ __('payment_details') }}
                        </h2>
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-6">
                            <!-- HyperPay will handle form submission and redirect to shopperResultUrl -->
                            <form class="paymentWidgets"
                                  data-brands="{{ $paymentMethod->getSetting('supported_brands', 'VISA MASTER AMEX') }}">
                            </form>
                        </div>
                    </div>

                    <!-- Loading Indicator -->
                    <div id="payment-loading" class="text-center mb-6 hidden">
                        <div class="inline-flex items-center px-4 py-2 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded-lg">
                            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-blue-800 dark:text-blue-200" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ __('processing_payment') }}
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="text-center space-y-4">
                        <!-- Back to checkout -->
                        <div>
                            <a href="{{ payment_gateway_localized_url(route('payment-gateway.checkout', ['order' => $paymentOrder->order_code])) }}"
                                class="inline-flex items-center px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors duration-200">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                </svg>
                                {{ __('back_to_payment_methods') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- HyperPay Widget Script -->
    <script src="{{ $baseUrl }}/v1/paymentWidgets.js?checkoutId={{ $checkoutId }}"
        @if($integrity)
        integrity="{{ $integrity }}"
        @endif
        crossorigin="anonymous">
    </script>

    <script>
        // Show loading indicator when form is submitted
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.paymentWidgets');
            if (form) {
                form.addEventListener('submit', function() {
                    document.getElementById('payment-loading').classList.remove('hidden');
                });
            }
        });
    </script>
@endsection

