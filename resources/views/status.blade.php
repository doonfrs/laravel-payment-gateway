@extends('payment-gateway::layouts.payment-gateway')

@section('title', __('payment_status'))

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <!-- Header -->
                <div class="bg-gradient-to-r from-indigo-600 to-blue-600 text-white px-6 py-4">
                    <h1 class="text-2xl font-bold">{{ __('payment_status') }}</h1>
                    <p class="text-indigo-100 mt-1">{{ __('order_code') }}:
                        {{ $paymentOrder->order_code }}</p>
                </div>

                <div class="p-6">
                    <!-- Status Indicator -->
                    <div class="text-center mb-8">
                        @if ($paymentOrder->status === 'pending')
                            <div
                                class="w-20 h-20 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4 animate-pulse">
                                <svg class="w-12 h-12 text-yellow-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <h2 class="text-2xl font-bold text-yellow-800 mb-2">
                                {{ __('pending') }}</h2>
                            <p class="text-gray-600">{{ __('status_pending_message') }}</p>
                        @elseif($paymentOrder->status === 'processing')
                            <div
                                class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4 animate-pulse">
                                <svg class="w-12 h-12 text-blue-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                                    </path>
                                </svg>
                            </div>
                            <h2 class="text-2xl font-bold text-blue-800 mb-2">
                                {{ __('processing') }}</h2>
                            <p class="text-gray-600">{{ __('status_processing_message') }}</p>
                        @elseif($paymentOrder->status === 'completed')
                            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <svg class="w-12 h-12 text-green-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            <h2 class="text-2xl font-bold text-green-800 mb-2">
                                {{ __('completed') }}</h2>
                            <p class="text-gray-600">{{ __('status_completed_message') }}</p>
                        @elseif($paymentOrder->status === 'failed')
                            <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <svg class="w-12 h-12 text-red-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </div>
                            <h2 class="text-2xl font-bold text-red-800 mb-2">{{ __('failed') }}
                            </h2>
                            <p class="text-gray-600">{{ __('status_failed_message') }}</p>
                        @elseif($paymentOrder->status === 'cancelled')
                            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <svg class="w-12 h-12 text-gray-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18.364 5.636M5.636 18.364l12.728-12.728">
                                    </path>
                                </svg>
                            </div>
                            <h2 class="text-2xl font-bold text-gray-800 mb-2">
                                {{ __('cancelled') }}</h2>
                            <p class="text-gray-600">{{ __('status_cancelled_message') }}</p>
                        @else
                            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <svg class="w-12 h-12 text-gray-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                                    </path>
                                </svg>
                            </div>
                            <h2 class="text-2xl font-bold text-gray-800 mb-2">{{ __('status') }}
                            </h2>
                            <p class="text-gray-600">{{ __('status_unknown_message') }}</p>
                        @endif
                    </div>

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
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                    @if ($paymentOrder->status === 'completed') bg-green-100 text-green-800
                                    @elseif($paymentOrder->status === 'failed') bg-red-100 text-red-800
                                    @elseif($paymentOrder->status === 'processing') bg-blue-100 text-blue-800
                                    @elseif($paymentOrder->status === 'pending') bg-yellow-100 text-yellow-800
                                    @else bg-gray-100 text-gray-800 @endif">
                                        {{ __('' . $paymentOrder->status) }}
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ __('created_at') }}:</span>
                                    <span
                                        class="font-medium text-gray-900">{{ $paymentOrder->created_at->format('M d, Y H:i:s') }}</span>
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

                    @if ($paymentOrder->getLocalizedDescription())
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold text-gray-900 mb-3">
                                {{ __('description') }}</h3>
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <p class="text-blue-800">{{ $paymentOrder->getLocalizedDescription() }}</p>
                            </div>
                        </div>
                    @endif

                    @if ($paymentOrder->payment_data && isset($paymentOrder->payment_data['error']))
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold text-gray-900 mb-3">
                                {{ __('error') }}</h3>
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
                                        <p class="text-sm text-red-700">{{ $paymentOrder->payment_data['error'] }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <hr class="border-gray-200 mb-8">

                    <div class="text-center">
                        <div class="flex flex-wrap justify-center gap-4">
                            @if ($paymentOrder->status === 'pending' || $paymentOrder->status === 'processing')
                                <button onclick="location.reload()"
                                    class="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-200">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                                        </path>
                                    </svg>
                                    {{ __('refresh_status') }}
                                </button>
                            @endif

                            @if ($paymentOrder->status === 'pending')
                                <a href="{{ payment_gateway_localized_url(route('payment-gateway.checkout', ['order' => $paymentOrder->order_code])) }}"
                                    class="inline-flex items-center px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-200">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z">
                                        </path>
                                    </svg>
                                    {{ __('continue_payment') }}
                                </a>
                            @endif

                            @if ($paymentOrder->status === 'completed' && $paymentOrder->success_url)
                                <a href="{{ $paymentOrder->success_url }}"
                                    class="inline-flex items-center px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-200">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                                    </svg>
                                    {{ __('continue') }}
                                </a>
                            @endif

                            @if ($paymentOrder->status === 'failed')
                                <a href="{{ payment_gateway_localized_url(route('payment-gateway.checkout', ['order' => $paymentOrder->order_code])) }}"
                                    class="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-200">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                                        </path>
                                    </svg>
                                    {{ __('try_again') }}
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Auto-refresh for pending/processing orders
        @if (in_array($paymentOrder->status, ['pending', 'processing']))
            setTimeout(function() {
                location.reload();
            }, 10000); // Refresh every 10 seconds
        @endif
    </script>
@endpush
