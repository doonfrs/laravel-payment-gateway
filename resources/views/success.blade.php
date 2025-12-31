@extends('payment-gateway::layouts.payment-gateway')

@section('title', __('payment_successful'))

@section('content')
    <div class="container mx-auto px-4 py-4">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <!-- Header -->
                <div class="bg-gradient-to-r from-green-500 to-emerald-600 text-white px-6 py-6 text-center">
                    <div class="w-16 h-16 bg-white bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-3">
                        <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <h1 class="text-2xl font-bold mb-1">{{ __('payment_successful') }}!</h1>
                    <p class="text-green-100 text-sm">{{ __('payment_processed_successfully') }}</p>
                </div>

                <div class="p-6">
                    <div class="text-center mb-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-1">{{ __('thank_you_payment') }}</h2>
                        <p class="text-gray-600 text-sm">{{ __('transaction_completed_successfully') }}</p>
                    </div>

                    <!-- Action Button -->
                    @php
                        $successRedirect = config('payment-gateway.success_redirect', 'home');
                        $redirectUrl = $successRedirect === 'home' ? '/' : ($paymentOrder->success_url ?? '/');
                    @endphp
                    <div class="text-center mb-6">
                        <a href="{{ $redirectUrl }}"
                            class="inline-flex items-center px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-200">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                            </svg>
                            {{ __('continue') }}
                        </a>
                    </div>

                    <!-- Order Details -->
                    <div class="grid md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('order_information') }}</h3>
                            <div class="bg-gray-50 rounded-lg p-4 space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ __('amount') }}:</span>
                                    <span
                                        class="font-bold text-xl text-green-600">{{ $paymentOrder->formatted_amount }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ __('status') }}:</span>
                                    <span
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                clip-rule="evenodd"></path>
                                        </svg>
                                        {{ __('' . $paymentOrder->status) }}
                                    </span>
                                </div>
                                @if ($paymentOrder->paid_at)
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">{{ __('paid_at') }}:</span>
                                        <span
                                            class="font-medium text-gray-900">{{ $paymentOrder->paid_at->format('M d, Y H:i:s') }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('customer_information') }}</h3>
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

                    @if ($paymentOrder->getLocalizedDescription())
                        <div class="mb-4">
                            <h3 class="text-base font-semibold text-gray-900 mb-2">{{ __('description') }}</h3>
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                                <p class="text-blue-800 text-sm">{{ $paymentOrder->getLocalizedDescription() }}</p>
                            </div>
                        </div>
                    @endif

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                        <div class="flex items-center justify-center">
                            <svg class="w-4 h-4 text-blue-500 mr-2" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                                </path>
                            </svg>
                            <p class="text-blue-800 text-sm">
                                {{ __('confirmation_email_sent') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
@endsection
