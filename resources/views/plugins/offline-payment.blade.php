@extends('payment-gateway::layouts.payment-gateway')

@section('title', __('offline_payment_gateway'))

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <!-- Header -->
                <div class="bg-gradient-to-r from-green-600 to-emerald-600 text-white px-6 py-4">
                    <h1 class="text-2xl font-bold">{{ $paymentMethod->getLocalizedDisplayName() }}</h1>
                </div>

                <div class="p-6">
                    <!-- Order Summary -->
                    <div class="grid md:grid-cols-2 gap-6 mb-8">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">
                                {{ __('order_summary') }}</h2>
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ __('amount') }}:</span>
                                    <span
                                        class="font-bold text-xl text-green-600">{{ $paymentOrder->formatted_amount }}</span>
                                </div>
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
                                {{ $paymentMethod->getLocalizedDisplayName() }}</h2>

                            @if ($description)
                                <div class="text-gray-700 mb-6 max-w-lg mx-auto">
                                    <div class="text-lg leading-relaxed">{!! $description !!}</div>
                                </div>
                            @endif

                        </div>
                    </div>

                    <!-- Action Buttons - Desktop -->
                    <div class="hidden md:flex justify-between items-center">
                        <a href="{{ route('payment-gateway.cancel', ['order' => $paymentOrder->order_code]) }}"
                            class="inline-flex items-center px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors duration-200">
                            {{ __('Cancel') }}
                        </a>

                        <form method="POST"
                            action="{{ payment_gateway_localized_url(route('payment-gateway.offline-confirm', ['order' => $paymentOrder->order_code])) }}">
                            @csrf
                            <button type="submit"
                                class="inline-flex items-center px-8 py-3 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 transition-colors duration-200">
                                {{ __('confirm_order') }}
                            </button>
                        </form>
                    </div>

                    <!-- Action Buttons - Mobile (sticky footer) -->
                    <div class="md:hidden fixed bottom-0 start-0 end-0 bg-white border-t border-gray-200 px-4 py-3 flex justify-between items-center z-50">
                        <a href="{{ route('payment-gateway.cancel', ['order' => $paymentOrder->order_code]) }}"
                            class="inline-flex items-center px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors duration-200">
                            {{ __('Cancel') }}
                        </a>

                        <form method="POST"
                            action="{{ payment_gateway_localized_url(route('payment-gateway.offline-confirm', ['order' => $paymentOrder->order_code])) }}">
                            @csrf
                            <button type="submit"
                                class="inline-flex items-center px-8 py-3 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 transition-colors duration-200">
                                {{ __('confirm_order') }}
                            </button>
                        </form>
                    </div>
                    <!-- Spacer for mobile sticky footer -->
                    <div class="md:hidden h-16"></div>
                </div>
            </div>
        </div>
    </div>
@endsection
