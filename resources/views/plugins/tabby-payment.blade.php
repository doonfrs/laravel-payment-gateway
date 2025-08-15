@extends('payment-gateway::layouts.payment-gateway')

@section('title', __('tabby_payment_gateway'))

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <div class="bg-white dark:bg-gray-900 rounded-lg shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-purple-600 to-pink-600 text-white px-6 py-4">
                    <h1 class="text-2xl font-bold">{{ $paymentMethod->getLocalizedDisplayName() }}</h1>
                    <p class="text-purple-100 mt-1">{{ __('pay_now_or_split_into_installments') }}</p>
                </div>
                <div class="p-6">
                    <div class="mb-6">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">{{ __('order_summary') }}
                        </h2>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-300">{{ __('order_code') }}:</span>
                                <span
                                    class="font-medium text-gray-900 dark:text-gray-100">{{ $paymentOrder->order_code }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-300">{{ __('amount') }}:</span>
                                <span
                                    class="font-bold text-xl text-green-600 dark:text-green-400">{{ $paymentOrder->formatted_amount }}</span>
                            </div>
                            @if ($paymentOrder->getLocalizedDescription())
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-300">{{ __('description') }}:</span>
                                    <span
                                        class="font-medium text-gray-900 dark:text-gray-100">{{ $paymentOrder->getLocalizedDescription() }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="mb-6">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">{{ __('customer_details') }}
                        </h2>
                        <div class="space-y-2">
                            @if ($paymentOrder->customer_name)
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-300">{{ __('customer_name') }}:</span>
                                    <span
                                        class="font-medium text-gray-900 dark:text-gray-100">{{ $paymentOrder->customer_name }}</span>
                                </div>
                            @endif
                            @if ($paymentOrder->customer_email)
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-300">{{ __('customer_email') }}:</span>
                                    <span
                                        class="font-medium text-gray-900 dark:text-gray-100">{{ $paymentOrder->customer_email }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    @if ($paymentProduct === 'installments')
                        <div
                            class="mb-6 bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-700 rounded-lg p-4">
                            <div class="flex items-center mb-2">
                                <svg class="w-5 h-5 text-purple-600 dark:text-purple-400 mr-2" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                </svg>
                                <h3 class="text-sm font-semibold text-purple-900 dark:text-purple-100">
                                    {{ __('pay_in_4_installments') }}</h3>
                            </div>
                            <p class="text-sm text-purple-700 dark:text-purple-300">
                                {{ __('split_your_payment_into_4_interest_free_installments', ['amount' => $paymentOrder->currency . ' ' . number_format($paymentOrder->amount / 4, 2)]) }}
                            </p>
                        </div>
                    @endif

                    <hr class="border-gray-200 dark:border-gray-700 mb-8">

                    <div class="space-y-4">
                        <!-- Loading State -->
                        <div id="tabby-loading" class="text-center py-8">
                            <div
                                class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-500 dark:text-gray-400">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-purple-600"
                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                </svg>
                                {{ __('initializing_payment') }}...
                            </div>
                        </div>

                        <!-- Error State -->
                        <div id="tabby-error"
                            class="hidden bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-lg p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
                                        {{ __('payment_initialization_failed') }}
                                    </h3>
                                    <div class="mt-2 text-sm text-red-700 dark:text-red-300">
                                        <p id="tabby-error-message">{{ __('unable_to_load_payment_form') }}</p>
                                    </div>
                                    <div class="mt-4">
                                        <button onclick="window.location.href='{{ $failureUrl }}'"
                                            class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-md text-sm">
                                            {{ __('go_back') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tabby Payment Container -->
                        <div id="tabby-checkout" class="hidden">
                            <!-- Tabby will inject its payment form here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <!-- Tabby Web SDK -->
    <script src="https://checkout.tabby.ai/tabby-card.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loadingEl = document.getElementById('tabby-loading');
            const errorEl = document.getElementById('tabby-error');
            const checkoutEl = document.getElementById('tabby-checkout');
            const errorMessageEl = document.getElementById('tabby-error-message');

            function showError(message) {
                loadingEl.classList.add('hidden');
                errorMessageEl.textContent = message;
                errorEl.classList.remove('hidden');
            }

            function showCheckout() {
                loadingEl.classList.add('hidden');
                checkoutEl.classList.remove('hidden');
            }

            // Tabby configuration
            const config = {
                selector: '#tabby-checkout',
                currency: '{{ $currency }}',
                lang: '{{ app()->getLocale() }}',
                payment: {
                    amount: '{{ round($paymentOrder->amount, 2) }}',
                    currency: '{{ $currency }}',
                    description: '{{ $paymentOrder->description ?: 'Order #' . $paymentOrder->order_code }}',
                    buyer: {
                        phone: '{{ $paymentOrder->customer_phone ?: '' }}',
                        email: '{{ $paymentOrder->customer_email ?: '' }}',
                        name: '{{ $paymentOrder->customer_name ?: '' }}',
                    },
                    order: {
                        tax_amount: '0.00',
                        shipping_amount: '0.00',
                        discount_amount: '0.00',
                        reference_id: '{{ $paymentOrder->order_code }}',
                        items: [{
                            title: '{{ $paymentOrder->description ?: 'Payment' }}',
                            description: 'Payment for order #{{ $paymentOrder->order_code }}',
                            quantity: 1,
                            unit_price: '{{ round($paymentOrder->amount, 2) }}',
                            discount_amount: '0.00',
                            reference_id: '{{ $paymentOrder->order_code }}',
                            image_url: '',
                            product_url: '',
                            category: 'general'
                        }]
                    },
                    buyer_history: {
                        registered_since: '{{ now()->subYear()->toISOString() }}',
                        loyalty_level: 0,
                    },
                    order_history: [{
                        purchased_at: '{{ now()->subMonth()->toISOString() }}',
                        amount: '{{ round($paymentOrder->amount, 2) }}',
                        payment_method: 'card',
                        status: 'new'
                    }],
                    meta: {
                        order_id: '{{ $paymentOrder->order_code }}',
                        customer: @json($paymentOrder->customer_data ?? [])
                    }
                },
                merchant_code: '{{ $publicKey }}',
                lang: '{{ app()->getLocale() }}',
                merchant_urls: {
                    success: '{{ $successUrl }}',
                    cancel: '{{ $failureUrl }}',
                    failure: '{{ $failureUrl }}'
                },
                @if ($sandboxMode)
                    sandbox: true,
                @endif
                onResult: function(result) {
                    console.log('Tabby payment result:', result);

                    if (result.status === 'created' || result.status === 'authorized') {
                        // Payment created or authorized successfully
                        const callbackData = {
                            status: result.status,
                            order_code: '{{ $paymentOrder->order_code }}',
                            payment_id: result.payment?.id,
                            tabby_id: result.id,
                            message: 'Payment processed successfully'
                        };

                        // Send callback data to our server
                        fetch('{{ $callbackUrl }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                    ?.getAttribute('content'),
                            },
                            body: JSON.stringify(callbackData)
                        }).then(response => {
                            if (response.ok) {
                                window.location.href = '{{ $successUrl }}';
                            } else {
                                window.location.href = '{{ $failureUrl }}';
                            }
                        }).catch(error => {
                            console.error('Callback error:', error);
                            window.location.href = '{{ $failureUrl }}';
                        });
                    } else {
                        // Payment failed or cancelled
                        window.location.href = '{{ $failureUrl }}';
                    }
                },
                onClose: function() {
                    console.log('Tabby checkout closed');
                    // Optionally redirect to failure URL when user closes
                    // window.location.href = '{{ $failureUrl }}';
                }
            };

            try {
                // Initialize Tabby
                if (window.TabbyCard) {
                    window.TabbyCard.init(config);
                    showCheckout();
                } else {
                    showError('{{ __('tabby_sdk_not_loaded') }}');
                }
            } catch (error) {
                console.error('Tabby initialization error:', error);
                showError('{{ __('failed_to_initialize_tabby_payment') }}');
            }
        });
    </script>
@endpush
