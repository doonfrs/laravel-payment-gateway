@extends('payment-gateway::layouts.payment-gateway')

@section('title', $paymentMethod->getLocalizedDisplayName())

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <div class="bg-white dark:bg-gray-900 rounded-lg shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-amber-600 to-yellow-600 text-white px-6 py-4">
                    <h1 class="text-2xl font-bold">{{ $paymentMethod->getLocalizedDisplayName() }}</h1>
                    <p class="text-amber-100 mt-1">{{ __('bok.enter_code') }}</p>
                </div>

                <div class="p-6">
                    <div class="mb-6 flex justify-between">
                        <span class="text-gray-600 dark:text-gray-300">{{ __('amount') }}:</span>
                        <span class="font-bold text-xl text-green-600 dark:text-green-400">{{ $paymentOrder->formatted_amount }}</span>
                    </div>

                    @if ($info)
                        <div class="mb-4 rounded-md bg-green-50 dark:bg-green-900/40 px-4 py-3 text-sm text-green-700 dark:text-green-200">
                            {{ $info }}
                        </div>
                    @endif

                    @if ($error)
                        <div class="mb-4 rounded-md bg-red-50 dark:bg-red-900/40 px-4 py-3 text-sm text-red-700 dark:text-red-200">
                            {{ $error }}
                        </div>
                    @endif

                    <form method="POST" action="{{ $interactUrl }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="bok_step" value="verify_otp">

                        <div>
                            <label for="otp" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">{{ __('bok.otp') }}</label>
                            <input type="text" inputmode="numeric" autocomplete="one-time-code" id="otp" name="otp" required
                                class="w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-center tracking-widest text-lg text-gray-900 dark:text-gray-100 focus:border-amber-500 focus:ring-amber-500">
                        </div>

                        <button type="submit"
                            class="w-full rounded-md bg-amber-600 hover:bg-amber-700 text-white font-semibold px-4 py-2.5 transition-colors">
                            {{ __('bok.confirm_payment') }}
                        </button>
                    </form>

                    <form method="POST" action="{{ $interactUrl }}" class="mt-3 text-center">
                        @csrf
                        <input type="hidden" name="bok_step" value="resend_otp">
                        <button type="submit"
                            class="text-sm text-amber-700 dark:text-amber-400 hover:underline">
                            {{ __('bok.resend_code') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
