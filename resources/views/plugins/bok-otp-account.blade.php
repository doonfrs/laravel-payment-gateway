@extends('payment-gateway::layouts.payment-gateway')

@section('title', $paymentMethod->getLocalizedDisplayName())

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <div class="bg-white dark:bg-gray-900 rounded-lg shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-amber-600 to-yellow-600 text-white px-6 py-4">
                    <h1 class="text-2xl font-bold">{{ $paymentMethod->getLocalizedDisplayName() }}</h1>
                    <p class="text-amber-100 mt-1">{{ __('bok.pay_with') }}</p>
                </div>

                <div class="p-6">
                    {{-- Order summary --}}
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
                        </div>
                    </div>

                    @if ($instructions)
                        <div class="mb-4 text-sm text-gray-600 dark:text-gray-300">{{ $instructions }}</div>
                    @endif

                    @if ($error)
                        <div class="mb-4 rounded-md bg-red-50 dark:bg-red-900/40 px-4 py-3 text-sm text-red-700 dark:text-red-200">
                            {{ $error }}
                        </div>
                    @endif

                    <form method="POST" action="{{ $interactUrl }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="bok_step" value="request_otp">

                        <div>
                            <label for="cif" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">{{ __('bok.cif') }}</label>
                            <input type="text" inputmode="numeric" id="cif" name="cif" value="{{ $cif }}" required
                                class="w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-gray-900 dark:text-gray-100 focus:border-amber-500 focus:ring-amber-500">
                        </div>

                        <div>
                            <label for="mobile" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">{{ __('bok.mobile') }}</label>
                            <input type="text" inputmode="numeric" id="mobile" name="mobile" value="{{ $mobile }}" required
                                placeholder="2499XXXXXXXX"
                                class="w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-gray-900 dark:text-gray-100 focus:border-amber-500 focus:ring-amber-500">
                        </div>

                        <button type="submit"
                            class="w-full rounded-md bg-amber-600 hover:bg-amber-700 text-white font-semibold px-4 py-2.5 transition-colors">
                            {{ __('bok.send_code') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
