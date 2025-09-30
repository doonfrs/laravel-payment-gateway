@extends('payment-gateway::layouts.payment-gateway')

@section('title', __('offline_payment_gateway'))

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <!-- Header -->
                <div class="bg-gradient-to-r from-green-600 to-emerald-600 text-white px-6 py-4">
                    <h1 class="text-2xl font-bold">{{ $paymentMethod->getLocalizedDisplayName() }}</h1>
                    <p class="text-green-100 mt-1">{{ __('cash_on_delivery') }}</p>
                </div>

                <div class="p-6">
                    <!-- Order Summary -->
                    <div class="grid md:grid-cols-2 gap-6 mb-8">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">
                                {{ __('order_summary') }}</h2>
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ __('order_code') }}:</span>
                                    <span class="font-medium text-gray-900">{{ $paymentOrder->order_code }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ __('amount') }}:</span>
                                    <span
                                        class="font-bold text-xl text-green-600">{{ $paymentOrder->formatted_amount }}</span>
                                </div>
                                @if ($paymentOrder->getLocalizedDescription())
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">{{ __('description') }}:</span>
                                        <span
                                            class="font-medium text-gray-900">{{ $paymentOrder->getLocalizedDescription() }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">
                                {{ __('customer_details') }}</h2>
                            <div class="space-y-3">
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
                            </div>
                        </div>
                    </div>

                    <hr class="border-gray-200 mb-8">

                    <!-- Payment Instructions -->
                    <div class="text-center mb-8">
                        <div class="bg-gradient-to-br from-green-50 to-emerald-50 border border-green-200 rounded-lg p-8">
                            <div class="w-20 h-20 bg-green-500 rounded-full flex items-center justify-center mx-auto mb-6">
                                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z">
                                    </path>
                                </svg>
                            </div>

                            <h2 class="text-2xl font-bold text-green-800 mb-4">
                                {{ __('cash_on_delivery_payment') }}</h2>

                            <div class="text-gray-700 mb-6 max-w-lg mx-auto">
                                <p class="text-lg leading-relaxed">{{ $description }}</p>
                            </div>

                            <div class="bg-white rounded-lg p-4 mb-6 border border-green-200">
                                <div class="flex items-center justify-center space-x-2 text-green-700">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span class="font-medium">{{ __('no_payment_required_now') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="text-center space-y-4">
                        <!-- Confirm Order Button -->
                        <form method="POST"
                            action="{{ payment_gateway_localized_url(route('payment-gateway.offline-confirm', ['order' => $paymentOrder->order_code])) }}"
                            class="inline-block">
                            @csrf
                            <button type="submit"
                                class="inline-flex items-center px-8 py-4 bg-green-600 text-white text-lg font-semibold rounded-lg hover:bg-green-700 transition-colors duration-200 shadow-lg hover:shadow-xl">
                                <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7"></path>
                                </svg>
                                {{ __('confirm_order') }}
                            </button>
                        </form>

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
