@extends('payment-gateway::layouts.payment-gateway')

@section('title', __('Paymob Payment'))

@section('content')
    {{-- Full height flex container using dvh for mobile compatibility --}}
    <div class="flex flex-col h-dvh">
        {{-- Header - shrinks to content --}}
        <div class="shrink-0 bg-gradient-to-r from-green-600 to-emerald-600 text-white px-4 py-3">
            <h1 class="text-xl font-bold">{{ __('Paymob Payment') }}</h1>
            <p class="text-green-100 text-sm">{{ __('Complete your secure payment using Paymob') }}</p>
        </div>

        {{-- Order Summary - shrinks to content --}}
        <div class="shrink-0 bg-white dark:bg-gray-900 px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                <div class="flex gap-2">
                    <span class="text-gray-500 dark:text-gray-400">{{ __('Order Code') }}:</span>
                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ $paymentOrder->order_code }}</span>
                </div>
                <div class="flex gap-2">
                    <span class="text-gray-500 dark:text-gray-400">{{ __('Amount') }}:</span>
                    <span class="font-bold text-green-600 dark:text-green-400">{{ number_format($paymentOrder->amount, 2) }} {{ $paymentOrder->currency }}</span>
                </div>
                @if ($paymentOrder->customer_name)
                    <div class="flex gap-2">
                        <span class="text-gray-500 dark:text-gray-400">{{ __('Customer Name') }}:</span>
                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ $paymentOrder->customer_name }}</span>
                    </div>
                @endif
                @if ($paymentOrder->customer_email)
                    <div class="flex gap-2">
                        <span class="text-gray-500 dark:text-gray-400">{{ __('Customer Email') }}:</span>
                        <span class="font-medium text-gray-900 dark:text-gray-100">{{ $paymentOrder->customer_email }}</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Iframe Container - grows to fill remaining space --}}
        <div class="grow min-h-0">
            <iframe
                src="{{ $baseUrl }}/api/acceptance/iframes/{{ $iframeId }}?payment_token={{ $paymentKey }}"
                class="w-full h-full border-0"
                allowpaymentrequest
                frameborder="0"
            ></iframe>
        </div>
    </div>
@endsection
