@extends('payment-gateway::layouts.payment-gateway')

@section('title', __('moyasar_payment_gateway'))

@section('content')
{{dd($paymentOrder)}}
<div class="container mx-auto px-4 py-8">
    <div class="max-w-2xl mx-auto">
        <div class="bg-white dark:bg-gray-900 rounded-lg shadow-lg overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-cyan-600 text-white px-6 py-4">
                <h1 class="text-2xl font-bold">{{ $paymentMethod->display_name ?: $paymentMethod->name }}</h1>
                <p class="text-blue-100 mt-1">{{ __('pay_securely_with_moyasar') }}</p>
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
                            <span class="font-bold text-xl text-green-600 dark:text-green-400">{{ $paymentOrder->formatted_amount }}</span>
                        </div>
                        @if ($paymentOrder->description)
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-300">{{ __('description') }}:</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $paymentOrder->description }}</span>
                        </div>
                        @endif
                    </div>
                </div>
                <div class="mb-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">{{ __('customer_details') }}</h2>
                    <div class="space-y-2">
                        @if ($paymentOrder->customer_name)
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-300">{{ __('customer_name') }}:</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $paymentOrder->customer_name }}</span>
                        </div>
                        @endif
                        @if ($paymentOrder->customer_email)
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-300">{{ __('customer_email') }}:</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $paymentOrder->customer_email }}</span>
                        </div>
                        @endif
                    </div>
                </div>
                <hr class="border-gray-200 dark:border-gray-700 mb-8">
                <div>
                    <div class="mysr-form"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<link rel="stylesheet" href="https://unpkg.com/moyasar-payment-form@2.0.11/dist/moyasar.css" />
<script src="https://unpkg.com/moyasar-payment-form@2.0.11/dist/moyasar.umd.js"></script>
<script>
    Moyasar.init({
        element: '.mysr-form'
        , amount: intval('{{$paymentOrder->amount}}')
        , currency: '{{ $paymentOrder->currency }}'
        , description: '{{ $paymentOrder->description ?? "" }}'
        , publishable_api_key: '{{ $publishable_api_key }}'
        , secret_api_key: '{{ $secret_api_key }}'
        , callback_url: '{{ $callbackUrl }}'
        , methods: ['creditcard']
    , });

</script>
@endpush
