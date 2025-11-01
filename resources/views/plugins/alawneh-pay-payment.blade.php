@extends('payment-gateway::layouts.payment-gateway')

@section('title', __('alawneh_pay_payment_gateway'))

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <div class="bg-white dark:bg-gray-900 rounded-lg shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-6 py-4">
                    <h1 class="text-2xl font-bold">{{ $paymentMethod->getLocalizedDisplayName() }}</h1>
                    <p class="text-purple-100 mt-1">{{ __('pay_securely_with_alawneh_pay') }}</p>
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

                    <!-- Payment Status -->
                    <div class="text-center mb-8">
                        @if (isset($paymentResponse['status']) && $paymentResponse['status'] === 'ACCEPTED')
                            <div class="bg-gradient-to-br from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 border border-green-200 dark:border-green-700 rounded-lg p-8">
                                <div class="w-20 h-20 bg-green-500 rounded-full flex items-center justify-center mx-auto mb-6">
                                    <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </div>

                                <h2 class="text-2xl font-bold text-green-800 dark:text-green-300 mb-4">
                                    {{ __('payment_accepted') }}</h2>

                                <div class="text-gray-700 dark:text-gray-300 mb-6 max-w-lg mx-auto">
                                    <p class="text-lg leading-relaxed">{{ __('alawneh_pay_payment_success_message') }}</p>
                                </div>

                                @if (isset($paymentResponse['invoiceNumber']))
                                    <div class="bg-white dark:bg-gray-800 rounded-lg p-4 mb-6 border border-green-200 dark:border-green-700">
                                        <div class="flex items-center justify-center space-x-2 text-green-700 dark:text-green-300">
                                            <span class="font-medium">{{ __('invoice_number') }}:</span>
                                            <span class="font-bold">{{ $paymentResponse['invoiceNumber'] }}</span>
                                        </div>
                                        @if (isset($paymentResponse['paymentId']))
                                            <div class="flex items-center justify-center space-x-2 text-green-700 dark:text-green-300 mt-2">
                                                <span class="font-medium">{{ __('payment_id') }}:</span>
                                                <span class="font-mono text-sm">{{ $paymentResponse['paymentId'] }}</span>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-8">
                                <div class="w-20 h-20 bg-purple-500 rounded-full flex items-center justify-center mx-auto mb-6">
                                    <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>

                                <h2 class="text-2xl font-bold text-purple-800 dark:text-purple-300 mb-4">
                                    {{ __('payment_processing') }}</h2>

                                <div class="text-gray-700 dark:text-gray-300 mb-6 max-w-lg mx-auto">
                                    <p class="text-lg leading-relaxed">{{ __('alawneh_pay_payment_processing_message') }}</p>
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Action Buttons -->
                    <div class="text-center space-y-4">
                        @if (isset($paymentResponse['status']) && $paymentResponse['status'] === 'ACCEPTED')
                            <!-- Success - Return to merchant -->
                            <form method="GET" action="{{ $successUrl }}" class="inline-block">
                                <button type="submit"
                                    class="inline-flex items-center px-8 py-4 bg-green-600 text-white text-lg font-semibold rounded-lg hover:bg-green-700 transition-colors duration-200 shadow-lg hover:shadow-xl">
                                    <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    {{ __('continue') }}
                                </button>
                            </form>
                        @else
                            <!-- Processing - Check status -->
                            <a href="{{ payment_gateway_localized_url(route('payment-gateway.status', ['order' => $paymentOrder->order_code])) }}"
                                class="inline-flex items-center px-8 py-4 bg-purple-600 text-white text-lg font-semibold rounded-lg hover:bg-purple-700 transition-colors duration-200 shadow-lg hover:shadow-xl">
                                <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                {{ __('check_payment_status') }}
                            </a>
                        @endif

                        <!-- Back to checkout -->
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
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

