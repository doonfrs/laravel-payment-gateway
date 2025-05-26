@extends('payment-gateway::layouts.payment-gateway')

@section('title', __('payment_checkout'))

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                <!-- Header -->
                <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white px-6 py-4">
                    <h1 class="text-2xl font-bold">{{ __('payment_checkout') }}</h1>
                    <p class="text-blue-100 mt-1">{{ __('complete_payment_securely') }}</p>
                </div>

                <div class="p-6">
                    <!-- Order Summary -->
                    <div class="grid md:grid-cols-2 gap-6 mb-8">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">
                                {{ __('order_details') }}</h2>
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ __('order_code') }}:</span>
                                    <span class="font-medium text-gray-900">{{ $paymentOrder->order_code }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ __('amount') }}:</span>
                                    <span
                                        class="font-bold text-xl text-green-600">{{ $paymentOrder->formatted_amount }}</span>
                                </div>
                                @if ($paymentOrder->description)
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">{{ __('description') }}:</span>
                                        <span class="font-medium text-gray-900">{{ $paymentOrder->description }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">
                                {{ __('customer_information') }}</h2>
                            <div class="space-y-3">
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
                                @if ($paymentOrder->customer_phone)
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">{{ __('customer_phone') }}:</span>
                                        <span class="font-medium text-gray-900">{{ $paymentOrder->customer_phone }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <hr class="border-gray-200 mb-8">

                    <!-- Payment Methods -->
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 mb-6">
                            {{ __('select_payment_method') }}</h2>

                        @if (($errors ?? false) && $errors->any())
                            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd"
                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-red-800">
                                            {{ __('errors_with_submission') }}</h3>
                                        <div class="mt-2 text-sm text-red-700">
                                            <ul class="list-disc pl-5 space-y-1">
                                                @foreach ($errors->all() as $error)
                                                    <li>{{ $error }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <form method="POST"
                            action="{{ route('payment-gateway.process', ['order' => $paymentOrder->order_code]) }}"
                            id="payment-form">
                            @csrf
                            <input type="hidden" name="payment_method_id" id="selected-method" required>

                            <div class="grid md:grid-cols-2 gap-4 mb-8">
                                @forelse($paymentMethods as $method)
                                    <div class="payment-method-card border-2 border-gray-200 rounded-lg p-6 cursor-pointer hover:shadow-md hover:-translate-y-0.5 transition-all duration-300 ease-in-out"
                                        data-method-id="{{ $method->id }}">
                                        <div class="text-center">
                                            @if ($method->logo_url)
                                                <img src="{{ $method->logo_url }}" alt="{{ $method->display_name }}"
                                                    class="h-12 mx-auto mb-4 object-contain">
                                            @else
                                                <div
                                                    class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-600 rounded-lg mx-auto mb-4 flex items-center justify-center">
                                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z">
                                                        </path>
                                                    </svg>
                                                </div>
                                            @endif
                                            <h3 class="font-semibold text-gray-900 mb-2">
                                                {{ $method->display_name ?: $method->name }}</h3>
                                            @if ($method->description)
                                                <p class="text-sm text-gray-600">{{ $method->description }}</p>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <div class="col-span-2">
                                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                            <div class="flex">
                                                <div class="flex-shrink-0">
                                                    <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20"
                                                        fill="currentColor">
                                                        <path fill-rule="evenodd"
                                                            d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                                            clip-rule="evenodd" />
                                                    </svg>
                                                </div>
                                                <div class="ml-3">
                                                    <h3 class="text-sm font-medium text-yellow-800">
                                                        {{ __('no_payment_methods_available') }}
                                                    </h3>
                                                    <p class="mt-1 text-sm text-yellow-700">
                                                        {{ __('contact_support_assistance') }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforelse
                            </div>

                            @if ($paymentMethods->count() > 0)
                                <div class="flex justify-center">
                                    <button type="submit"
                                        class="bg-gradient-to-r from-blue-600 to-purple-600 text-white px-8 py-3 rounded-lg font-semibold text-lg hover:from-blue-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
                                        id="proceed-btn" disabled>
                                        <span class="flex items-center">
                                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z">
                                                </path>
                                            </svg>
                                            {{ __('proceed_to_payment') }}
                                        </span>
                                    </button>
                                </div>
                            @endif
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const paymentCards = document.querySelectorAll('.payment-method-card');
            const selectedMethodInput = document.getElementById('selected-method');
            const proceedBtn = document.getElementById('proceed-btn');

            paymentCards.forEach(card => {
                card.addEventListener('click', function() {
                    // Remove selected class from all cards
                    paymentCards.forEach(c => {
                        c.classList.remove('ring-2', 'ring-blue-500', 'bg-blue-50');
                    });

                    // Add selected class to clicked card
                    this.classList.add('ring-2', 'ring-blue-500', 'bg-blue-50');

                    // Set the selected method ID
                    const methodId = this.getAttribute('data-method-id');
                    selectedMethodInput.value = methodId;

                    // Enable proceed button
                    proceedBtn.disabled = false;
                    proceedBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                });
            });
        });
    </script>
@endpush
