@extends('payment-gateway::layouts.payment-gateway')

@section('title', __('Paymob Payment'))

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <div class="bg-white dark:bg-gray-900 rounded-lg shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-green-600 to-emerald-600 text-white px-6 py-4">
                    <h1 class="text-2xl font-bold">{{ __('Paymob Payment') }}</h1>
                    <p class="text-green-100 mt-1">{{ __('Complete your secure payment using Paymob') }}</p>
                </div>
                <div class="p-6">
                    <div class="mb-6">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">{{ __('Order Summary') }}
                        </h2>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-300">{{ __('Order Code') }}:</span>
                                <span
                                    class="font-medium text-gray-900 dark:text-gray-100">{{ $paymentOrder->order_code }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-300">{{ __('Amount') }}:</span>
                                <span
                                    class="font-bold text-xl text-green-600 dark:text-green-400">{{ number_format($paymentOrder->amount, 2) }} {{ $paymentOrder->currency }}</span>
                            </div>
                            @if ($paymentOrder->description)
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-300">{{ __('Description') }}:</span>
                                    <span
                                        class="font-medium text-gray-900 dark:text-gray-100">{{ $paymentOrder->description }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="mb-6">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">{{ __('Customer Details') }}
                        </h2>
                        <div class="space-y-2">
                            @if ($paymentOrder->customer_name)
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-300">{{ __('Customer Name') }}:</span>
                                    <span
                                        class="font-medium text-gray-900 dark:text-gray-100">{{ $paymentOrder->customer_name }}</span>
                                </div>
                            @endif
                            @if ($paymentOrder->customer_email)
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-300">{{ __('Customer Email') }}:</span>
                                    <span
                                        class="font-medium text-gray-900 dark:text-gray-100">{{ $paymentOrder->customer_email }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                    <hr class="border-gray-200 dark:border-gray-700 mb-0">
                </div>
                <!-- Iframe Container - Full width without padding -->
                <div class="w-full" style="margin: 0; padding: 0;">
                    <iframe
                        src="{{ $baseUrl }}/api/acceptance/iframes/{{ $iframeId }}?payment_token={{ $paymentKey }}"
                        class="w-full"
                        style="width: 100%; height: 1000px; min-height: 1000px; border: none; margin: 0; padding: 0; display: block;"
                        allowpaymentrequest
                        scrolling="no"
                        frameborder="0"
                    ></iframe>
                </div>
            </div>
        </div>
    </div>

   
@endsection


