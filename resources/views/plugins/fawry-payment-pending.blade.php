@extends('payment-gateway::layouts.payment-gateway')

@section('title', __('Fawry Payment Reference'))

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <div class="bg-white dark:bg-gray-900 rounded-lg shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-yellow-500 to-yellow-600 text-white px-6 py-4">
                    <h1 class="text-2xl font-bold">{{ __('Fawry Payment Reference') }}</h1>
                    <p class="text-yellow-100 mt-1">{{ __('Use this reference number to pay at any Fawry outlet') }}</p>
                </div>
                <div class="p-6">
                    <div class="mb-6">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">{{ __('Order Summary') }}</h2>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-300">{{ __('Order Code') }}:</span>
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $paymentOrder->order_code }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-300">{{ __('Amount') }}:</span>
                                <span class="font-bold text-xl text-green-600 dark:text-green-400">{{ number_format($paymentOrder->amount, 2) }} {{ $paymentOrder->currency }}</span>
                            </div>
                        </div>
                    </div>

                    <hr class="border-gray-200 dark:border-gray-700 mb-6">

                    <!-- Fawry Reference Number -->
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-6 mb-6 text-center">
                        <p class="text-sm text-blue-600 dark:text-blue-300 mb-2">{{ __('Your Fawry Reference Number') }}</p>
                        <p class="text-4xl font-bold text-blue-800 dark:text-blue-100 tracking-wider select-all" id="fawry-ref">{{ $referenceNumber }}</p>
                        <button onclick="navigator.clipboard.writeText('{{ $referenceNumber }}')"
                            class="mt-3 text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200 underline">
                            {{ __('Copy Reference Number') }}
                        </button>
                    </div>

                    <!-- Instructions -->
                    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 mb-6">
                        <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-3">{{ __('How to pay') }}:</h3>
                        <ol class="list-decimal list-inside space-y-2 text-sm text-gray-700 dark:text-gray-300">
                            <li>{{ __('Visit any Fawry outlet or use the Fawry app') }}</li>
                            <li>{{ __('Provide the reference number shown above') }}</li>
                            <li>{{ __('Pay the exact amount shown') }}</li>
                            <li>{{ __('You will receive a confirmation once payment is processed') }}</li>
                        </ol>
                    </div>

                    <!-- Expiry Notice -->
                    @if (isset($expiryHours))
                        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg p-3 mb-6">
                            <p class="text-sm text-yellow-800 dark:text-yellow-200">
                                <strong>{{ __('Important') }}:</strong> {{ __('This reference number expires in :hours hours. Please complete payment before it expires.', ['hours' => $expiryHours]) }}
                            </p>
                        </div>
                    @endif

                    <div class="flex flex-col sm:flex-row gap-4">
                        <a href="{{ $successUrl ?? '/' }}"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-6 rounded-md text-sm flex-1 inline-flex items-center justify-center text-center">
                            {{ __('Done') }}
                        </a>
                        <a href="{{ $failureUrl ?? route('payment-gateway.cancel', ['order' => $paymentOrder->order_code]) }}"
                            class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-3 px-6 rounded-md text-sm flex-1 inline-flex items-center justify-center text-center">
                            {{ __('cancel_payment') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
