@extends('payment-gateway::layouts.payment-gateway')

@section('title', __('mamopay_payment_error'))

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <div class="bg-white dark:bg-gray-900 rounded-lg shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-red-600 to-rose-600 text-white px-6 py-4">
                    <h1 class="text-2xl font-bold">{{ $paymentMethod->getLocalizedDisplayName() }}</h1>
                    <p class="text-red-100 mt-1">{{ __('payment_error') }}</p>
                </div>

                <div class="p-6">
                    <div class="mb-6">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">{{ __('order_summary') }}</h2>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-300">{{ __('order_code') }}:</span>
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $paymentOrder->order_code }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-300">{{ __('amount') }}:</span>
                                <span class="font-bold text-xl text-gray-900 dark:text-gray-100">{{ $paymentOrder->formatted_amount }}</span>
                            </div>
                            @if ($paymentOrder->getLocalizedDescription())
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-300">{{ __('description') }}:</span>
                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ $paymentOrder->getLocalizedDescription() }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <hr class="border-gray-200 dark:border-gray-700 mb-8">

                    <div class="text-center mb-8">
                        <div class="bg-gradient-to-br from-red-50 to-rose-50 dark:from-red-900/20 dark:to-rose-900/20 border border-red-200 dark:border-red-700 rounded-lg p-8">
                            <div class="w-20 h-20 bg-red-500 rounded-full flex items-center justify-center mx-auto mb-6">
                                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>

                            <h2 class="text-2xl font-bold text-red-800 dark:text-red-300 mb-4">{{ __('payment_failed') }}</h2>

                            <div class="text-gray-700 dark:text-gray-300 mb-6 max-w-lg mx-auto">
                                <p class="text-lg leading-relaxed mb-4">{{ __('mamopay_payment_failed_message') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="text-center space-y-4">
                        <a href="{{ payment_gateway_localized_url(route('payment-gateway.checkout', ['order' => $paymentOrder->order_code])) }}"
                            class="inline-flex items-center px-8 py-4 bg-blue-600 text-white text-lg font-semibold rounded-lg hover:bg-blue-700 transition-colors duration-200 shadow-lg hover:shadow-xl">
                            {{ __('try_again') }}
                        </a>

                        <div class="pt-2">
                            <a href="{{ route('payment-gateway.cancel', ['order' => $paymentOrder->order_code]) }}"
                                class="inline-flex items-center px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-200">
                                {{ __('cancel_payment') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
