@extends('payment-gateway::layouts.payment-gateway')

@section('title', __('offline_payment_gateway'))

@section('content')
    @php
        $hasMultiplePaymentMethods = \Trinavo\PaymentGateway\Facades\PaymentGateway::getAvailablePaymentMethodsForOrder($paymentOrder)->count() > 1;
        $backUrl = $hasMultiplePaymentMethods
            ? \Trinavo\PaymentGateway\Facades\PaymentGateway::getPaymentUrl($paymentOrder)
            : route('payment-gateway.cancel', ['order' => $paymentOrder->order_code]);
    @endphp

    <!-- Header Bar -->
    <div class="bg-base-100 shadow-sm sticky top-0 z-40">
        <div class="container mx-auto max-w-lg px-4">
            <div class="flex items-center h-14">
                <a href="{{ $backUrl }}"
                    class="btn btn-ghost btn-sm btn-square -ms-2 me-2">
                    <svg class="w-5 h-5 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                <h1 class="text-lg font-bold text-base-content">{{ $paymentMethod->getLocalizedDisplayName() }}</h1>
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

        <!-- Payment Instructions -->
        @if ($description)
            <div class="bg-warning/10 border border-warning/30 rounded-xl p-5 mb-5">
                <div class="flex items-center gap-2 mb-2 text-base-content font-bold text-sm">
                    <x-heroicon-o-exclamation-triangle class="w-5 h-5 shrink-0 text-warning" />
                    {{ __('note') }}:
                </div>
                <div class="text-base-content text-base leading-relaxed">{!! $description !!}</div>
            </div>
        @endif

        <!-- Action Buttons - Desktop -->
        <div class="hidden md:flex justify-between items-center mt-8">
            <form method="POST"
                action="{{ payment_gateway_localized_url(route('payment-gateway.offline-confirm', ['order' => $paymentOrder->order_code])) }}">
                @csrf
                <button type="submit" class="btn btn-primary">
                    <x-heroicon-o-check-circle class="w-5 h-5" />
                    {{ __('confirm_order') }}
                </button>
            </form>

            <a href="{{ $backUrl }}"
                class="btn btn-outline">
                <x-heroicon-o-arrow-right class="w-5 h-5" />
                {{ __('Go Back') }}
            </a>
        </div>

        <!-- Spacer for mobile sticky footer -->
        <div class="md:hidden h-20"></div>

    </div>

    <!-- Action Buttons - Mobile (sticky footer) -->
    <div class="md:hidden fixed bottom-0 start-0 end-0 bg-base-100 border-t border-base-300 px-4 py-3 flex justify-between items-center z-50">
        <form method="POST"
            action="{{ payment_gateway_localized_url(route('payment-gateway.offline-confirm', ['order' => $paymentOrder->order_code])) }}">
            @csrf
            <button type="submit" class="btn btn-primary">
                <x-heroicon-o-check-circle class="w-5 h-5" />
                {{ __('confirm_order') }}
            </button>
        </form>

        <a href="{{ $backUrl }}"
            class="btn btn-outline">
            <x-heroicon-o-arrow-right class="w-5 h-5" />
            {{ __('Go Back') }}
        </a>
    </div>
@endsection
