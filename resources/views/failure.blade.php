@extends('payment-gateway::layouts.payment-gateway')

@section('title', __('payment_failed'))

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <!-- Header -->
                <div class="bg-gradient-to-r from-red-500 to-rose-600 text-white px-6 py-8 text-center">
                    <div class="w-20 h-20 bg-white bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12">
                            </path>
                        </svg>
                    </div>
                    <h1 class="text-3xl font-bold mb-2">{{ __('payment_failed') }}</h1>
                    <p class="text-red-100">{{ __('payment_could_not_processed') }}</p>
                </div>

                <div class="p-6">
                    <div class="text-center mb-8">
                        <h2 class="text-2xl font-bold text-gray-900 mb-2">
                            {{ __('payment_not_successful') }}</h2>
                        <p class="text-gray-600">{{ __('unable_process_payment') }}</p>
                    </div>

                    <!-- Action Button -->
                    @if ($paymentOrder->failure_url)
                        <div class="text-center mb-8">
                            <a href="{{ $paymentOrder->failure_url }}"
                                class="inline-flex items-center px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-200">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                </svg>
                                {{ __('return_to_store') }}
                            </a>
                        </div>
                    @endif

                    <!-- Order Details -->
                    <div class="grid md:grid-cols-2 gap-8 mb-8">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                                {{ __('order_information') }}</h3>
                            <div class="bg-gray-50 rounded-lg p-4 space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ __('order_code') }}:</span>
                                    <span class="font-medium text-gray-900">{{ $paymentOrder->order_code }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ __('amount') }}:</span>
                                    <span
                                        class="font-bold text-xl text-gray-900">{{ $paymentOrder->formatted_amount }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ __('status') }}:</span>
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                                clip-rule="evenodd"></path>
                                        </svg>
                                        {{ __('' . $paymentOrder->status) }}
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ __('attempted_at') }}:</span>
                                    <span
                                        class="font-medium text-gray-900">{{ $paymentOrder->updated_at->format('M d, Y H:i:s') }}</span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">
                                {{ __('customer_information') }}</h3>
                            <div class="bg-gray-50 rounded-lg p-4 space-y-3">
                                @if ($paymentOrder->customer_name)
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">{{ __('customer_name') }}:</span>
                                        <span class="font-medium text-gray-900">{{ $paymentOrder->customer_name }}</span>
                                    </div>
                                @endif
                                @if ($paymentOrder->customer_email)
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">{{ __('customer_email') }}:</span>
                                        <span class="font-medium text-gray-900">{{ $paymentOrder->customer_email }}</span>
                                    </div>
                                @endif
                                @if ($paymentOrder->paymentMethod)
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">{{ __('payment_method') }}:</span>
                                        <span
                                            class="font-medium text-gray-900">{{ $paymentOrder->paymentMethod->getLocalizedDisplayName() }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if ($paymentOrder->payment_data && isset($paymentOrder->payment_data['error']))
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold text-gray-900 mb-3">
                                {{ __('error_details') }}</h3>
                            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd"
                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <h4 class="text-sm font-medium text-red-800">
                                            {{ __('payment_error') }}</h4>
                                        <p class="mt-1 text-sm text-red-700">{{ $paymentOrder->payment_data['error'] }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    </div>
@endsection
