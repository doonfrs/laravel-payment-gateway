@extends('payment-gateway::layouts.payment-gateway')

@section('title', $paymentMethod->getLocalizedDisplayName())

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <div class="bg-white dark:bg-gray-900 rounded-lg shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-red-600 to-rose-600 text-white px-6 py-4">
                    <h1 class="text-2xl font-bold">{{ $paymentMethod->getLocalizedDisplayName() }}</h1>
                </div>

                <div class="p-6 text-center">
                    <p class="text-gray-700 dark:text-gray-200 mb-6">{{ $message ?? __('bok.unavailable') }}</p>

                    <a href="{{ $failureUrl }}"
                        class="inline-block rounded-md bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-semibold px-5 py-2.5 hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
                        {{ __('bok.back') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
