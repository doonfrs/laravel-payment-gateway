@extends('payment-gateway::layouts.payment-gateway')

@section('title', __('payment-gateway::messages.dummy_payment_gateway'))

@push('styles')
<style>
    .test-card {
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }
    .test-card:hover {
        transform: translateY(-2px);
        transition: all 0.3s ease;
    }
</style>
@endpush

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-purple-600 to-blue-600 text-white px-6 py-4">
                <h1 class="text-2xl font-bold">{{ $paymentMethod->display_name ?: $paymentMethod->name }}</h1>
                <p class="text-purple-100 mt-1">{{ __('payment-gateway::messages.test_payment_gateway') }}</p>
            </div>

            <div class="p-6">
                <!-- Order Summary -->
                <div class="grid md:grid-cols-2 gap-6 mb-8">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ __('payment-gateway::messages.order_summary') }}</h2>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-gray-600">{{ __('payment-gateway::messages.order_code') }}:</span>
                                <span class="font-medium text-gray-900">{{ $paymentOrder->order_code }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">{{ __('payment-gateway::messages.amount') }}:</span>
                                <span class="font-bold text-xl text-green-600">{{ $paymentOrder->formatted_amount }}</span>
                            </div>
                            @if($paymentOrder->description)
                            <div class="flex justify-between">
                                <span class="text-gray-600">{{ __('payment-gateway::messages.description') }}:</span>
                                <span class="font-medium text-gray-900">{{ $paymentOrder->description }}</span>
                            </div>
                            @endif
                        </div>
                    </div>

                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ __('payment-gateway::messages.customer_details') }}</h2>
                        <div class="space-y-3">
                            @if($paymentOrder->customer_name)
                            <div class="flex justify-between">
                                <span class="text-gray-600">{{ __('payment-gateway::messages.customer_name') }}:</span>
                                <span class="font-medium text-gray-900">{{ $paymentOrder->customer_name }}</span>
                            </div>
                            @endif
                            @if($paymentOrder->customer_email)
                            <div class="flex justify-between">
                                <span class="text-gray-600">{{ __('payment-gateway::messages.customer_email') }}:</span>
                                <span class="font-medium text-gray-900">{{ $paymentOrder->customer_email }}</span>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                <hr class="border-gray-200 mb-8">

                <!-- Test Payment Scenarios -->
                <div class="text-center">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">{{ __('payment-gateway::messages.test_payment_scenarios') }}</h2>
                    <p class="text-gray-600 mb-8 max-w-2xl mx-auto">
                        {{ __('payment-gateway::messages.dummy_payment_description') }}
                    </p>

                    <div class="grid md:grid-cols-3 gap-6 mb-8">
                        <!-- Direct Success -->
                        <div class="bg-gradient-to-br from-green-50 to-emerald-50 border border-green-200 rounded-lg p-6 hover:shadow-lg transition-all duration-300">
                            <div class="text-center">
                                <div class="w-16 h-16 bg-green-500 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-green-800 mb-2">{{ __('payment-gateway::messages.direct_success') }}</h3>
                                <p class="text-sm text-green-600 mb-4">{{ __('payment-gateway::messages.simulate_immediate_success') }}</p>
                                <a href="{{ route('payment-gateway.dummy-action', ['order' => $paymentOrder->order_code, 'action' => 'success']) }}" 
                                   class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-200">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    {{ __('payment-gateway::messages.pay_successfully') }}
                                </a>
                            </div>
                        </div>

                        <!-- Direct Failure -->
                        <div class="bg-gradient-to-br from-red-50 to-rose-50 border border-red-200 rounded-lg p-6 hover:shadow-lg transition-all duration-300">
                            <div class="text-center">
                                <div class="w-16 h-16 bg-red-500 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-red-800 mb-2">{{ __('payment-gateway::messages.direct_failure') }}</h3>
                                <p class="text-sm text-red-600 mb-4">{{ __('payment-gateway::messages.simulate_immediate_failure') }}</p>
                                <a href="{{ route('payment-gateway.dummy-action', ['order' => $paymentOrder->order_code, 'action' => 'failure']) }}" 
                                   class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-200">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                    {{ __('payment-gateway::messages.fail_payment') }}
                                </a>
                            </div>
                        </div>

                        <!-- External Callback -->
                        <div class="bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-6 hover:shadow-lg transition-all duration-300">
                            <div class="text-center">
                                <div class="w-16 h-16 bg-blue-500 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold text-blue-800 mb-2">{{ __('payment-gateway::messages.external_callback') }}</h3>
                                <p class="text-sm text-blue-600 mb-4">{{ __('payment-gateway::messages.simulate_external_callback') }}</p>
                                <a href="{{ route('payment-gateway.dummy-action', ['order' => $paymentOrder->order_code, 'action' => 'callback']) }}" 
                                   class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-200">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                    </svg>
                                    {{ __('payment-gateway::messages.external_payment') }}
                                </a>
                            </div>
                        </div>
                    </div>

                    <hr class="border-gray-200 my-8">

                    <!-- Manual Callback Testing -->
                    <div class="bg-gray-50 rounded-lg p-6 mb-8">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('payment-gateway::messages.manual_callback_testing') }}</h3>
                        <p class="text-gray-600 mb-6">
                            {{ __('payment-gateway::messages.test_callback_functionality') }}
                        </p>
                        
                        <div class="flex flex-wrap justify-center gap-4">
                            <form method="POST" action="{{ $callbackUrl }}" class="inline-block">
                                @csrf
                                <input type="hidden" name="status" value="success">
                                <input type="hidden" name="order_code" value="{{ $paymentOrder->order_code }}">
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-100 text-green-800 border border-green-300 rounded-lg hover:bg-green-200 transition-colors duration-200">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    {{ __('payment-gateway::messages.send_success_callback') }}
                                </button>
                            </form>

                            <form method="POST" action="{{ $callbackUrl }}" class="inline-block">
                                @csrf
                                <input type="hidden" name="status" value="failed">
                                <input type="hidden" name="order_code" value="{{ $paymentOrder->order_code }}">
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-100 text-red-800 border border-red-300 rounded-lg hover:bg-red-200 transition-colors duration-200">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                    {{ __('payment-gateway::messages.send_failure_callback') }}
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Back to checkout -->
                    <a href="{{ route('payment-gateway.checkout', ['order' => $paymentOrder->order_code]) }}" 
                       class="inline-flex items-center px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors duration-200">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        {{ __('payment-gateway::messages.back_to_payment_methods') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 