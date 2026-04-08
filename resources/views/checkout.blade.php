@extends('payment-gateway::layouts.payment-gateway')

@section('title', __('payment_checkout'))

@section('content')
    <!-- Header Bar -->
    <div class="bg-base-100 shadow-sm sticky top-0 z-40">
        <div class="container mx-auto max-w-lg px-4">
            <div class="flex items-center h-14">
                <button type="button" onclick="history.back()"
                    class="btn btn-ghost btn-sm btn-square -ms-2 me-2">
                    <svg class="w-5 h-5 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <h1 class="text-lg font-bold text-base-content">{{ __('payment_checkout') }}</h1>
            </div>
        </div>
    </div>

    <div class="container mx-auto max-w-lg px-4 py-4 md:py-6">

        <!-- Order Summary -->
        <div class="bg-base-100 rounded-xl shadow-sm p-5 mb-5">
            <div class="flex justify-between items-start">
                <div>
                    <div class="text-xs text-base-content/50 uppercase tracking-wide">{{ __('order_code') }}</div>
                    <div class="font-semibold text-base-content mt-1">{{ $paymentOrder->order_code }}</div>
                </div>
                <div class="text-end">
                    <div class="text-xs text-base-content/50 uppercase tracking-wide">{{ __('amount') }}</div>
                    <div class="text-2xl font-bold text-primary mt-1">{{ $paymentOrder->formatted_amount }}</div>
                </div>
            </div>
            @if ($paymentOrder->customer_name || $paymentOrder->customer_email || $paymentOrder->customer_phone || $paymentOrder->getLocalizedDescription())
                <div class="border-t border-base-200 mt-4 pt-3 text-sm text-base-content/50 space-y-0.5">
                    @if ($paymentOrder->customer_name)
                        <div>{{ $paymentOrder->customer_name }}</div>
                    @endif
                    @if ($paymentOrder->customer_email)
                        <div>{{ $paymentOrder->customer_email }}</div>
                    @endif
                    @if ($paymentOrder->customer_phone)
                        <div>{{ $paymentOrder->customer_phone }}</div>
                    @endif
                    @if ($paymentOrder->getLocalizedDescription())
                        <div>{{ $paymentOrder->getLocalizedDescription() }}</div>
                    @endif
                </div>
            @endif
        </div>

        @if (($errors ?? false) && $errors->any())
            <div class="alert alert-error mb-5">
                <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd"
                        d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                        clip-rule="evenodd" />
                </svg>
                <div>
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Payment Methods -->
        <form method="POST"
            action="{{ payment_gateway_localized_url(route('payment-gateway.process', ['order' => $paymentOrder->order_code])) }}"
            id="payment-form">
            @csrf
            <input type="hidden" name="payment_method_id" id="selected-method" required>

            <div class="flex items-center justify-between mb-3">
                <h2 class="text-base font-semibold text-base-content">{{ __('select_payment_method') }}</h2>
                <span class="text-xs text-base-content/40 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                    {{ __('complete_payment_securely') }}
                </span>
            </div>

            <div class="bg-base-100 rounded-xl shadow-sm overflow-hidden">
                @forelse($paymentMethods as $method)
                    <div class="payment-method-card flex items-center gap-4 px-4 py-4 cursor-pointer hover:bg-base-200/60 active:bg-base-200 transition-colors duration-100 {{ !$loop->last ? 'border-b border-base-200' : '' }}"
                        data-method-id="{{ $method->id }}">
                        {{-- Logo / Icon --}}
                        @if ($method->logo_url)
                            <div class="w-12 h-12 bg-base-200/50 rounded-lg shrink-0 flex items-center justify-center p-1.5">
                                <img src="{{ $method->logo_url }}"
                                    alt="{{ $method->getLocalizedDisplayName() }}"
                                    class="w-full h-full object-contain">
                            </div>
                        @else
                            <div class="w-12 h-12 bg-base-200/50 rounded-lg shrink-0 flex items-center justify-center">
                                <svg class="w-6 h-6 text-base-content/25" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z">
                                    </path>
                                </svg>
                            </div>
                        @endif
                        {{-- Name + fee --}}
                        <div class="flex-1 min-w-0">
                            <div class="font-medium text-base-content">{{ $method->getLocalizedDisplayName() }}</div>
                            @if ($method->hasFee())
                                <div class="text-xs text-base-content/40 mt-0.5">{{ $method->getFeeDescription($paymentOrder->currency) }}</div>
                            @endif
                        </div>
                        {{-- Radio --}}
                        <div class="payment-radio w-5 h-5 rounded-full border-2 border-base-300 shrink-0 flex items-center justify-center transition-colors">
                            <div class="w-2.5 h-2.5 rounded-full bg-primary scale-0 transition-transform"></div>
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-center text-base-content/50">
                        <svg class="w-10 h-10 mx-auto mb-2 text-base-content/20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                        </svg>
                        <p class="font-medium">{{ __('no_payment_methods_available') }}</p>
                        <p class="text-sm mt-1">{{ __('contact_support_assistance') }}</p>
                    </div>
                @endforelse
            </div>
        </form>

    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const paymentCards = document.querySelectorAll('.payment-method-card');
            const selectedMethodInput = document.getElementById('selected-method');
            const paymentForm = document.getElementById('payment-form');

            paymentCards.forEach(card => {
                card.addEventListener('click', function() {
                    // Fill radio visual
                    const radio = this.querySelector('.payment-radio');
                    const dot = radio.querySelector('div');
                    radio.classList.add('border-primary');
                    radio.classList.remove('border-base-300');
                    dot.classList.remove('scale-0');
                    dot.classList.add('scale-100');

                    // Highlight selected row
                    this.classList.add('bg-primary/5');

                    // Dim other rows
                    paymentCards.forEach(c => {
                        if (c !== this) c.classList.add('opacity-30', 'pointer-events-none');
                    });

                    // Submit
                    selectedMethodInput.value = this.getAttribute('data-method-id');
                    paymentForm.submit();
                });
            });
        });
    </script>
@endpush
