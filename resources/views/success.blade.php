@extends('payment-gateway::layouts.payment-gateway')

@section('title', __('payment_successful'))

@section('content')
    @php
        $successRedirect = config('payment-gateway.success_redirect', 'home');
        $redirectUrl = $successRedirect === 'home' ? '/' : ($paymentOrder->success_url ?? '/');
    @endphp

    <div class="container mx-auto max-w-lg px-4 py-6 md:py-10">

        <!-- Success Icon + Message -->
        <div class="text-center mb-5">
            <div class="w-14 h-14 bg-success/15 rounded-full flex items-center justify-center mx-auto mb-3">
                <svg class="w-7 h-7 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <h1 class="text-xl font-bold text-base-content">{{ __('payment_successful') }}</h1>
            <p class="text-sm text-base-content/50 mt-1">{{ __('transaction_completed_successfully') }}</p>
        </div>

        <!-- Order Details -->
        <div class="bg-base-100 rounded-xl shadow-sm p-4 mb-4">
            <div class="flex justify-between items-start mb-3">
                <div>
                    <div class="text-xs text-base-content/50 uppercase tracking-wide">{{ __('order_code') }}</div>
                    <div class="font-semibold text-base-content mt-0.5">{{ $paymentOrder->order_code }}</div>
                </div>
                <div class="text-end">
                    <div class="text-xs text-base-content/50 uppercase tracking-wide">{{ __('amount') }}</div>
                    <div class="text-xl font-bold text-success mt-0.5">{{ $paymentOrder->formatted_amount }}</div>
                </div>
            </div>

            <div class="border-t border-base-200 pt-3 space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-base-content/50">{{ __('status') }}</span>
                    <span class="badge badge-success badge-sm gap-1">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        {{ __('' . $paymentOrder->status) }}
                    </span>
                </div>
                @if ($paymentOrder->paid_at)
                    <div class="flex justify-between">
                        <span class="text-base-content/50">{{ __('date') }}</span>
                        <span class="text-base-content font-medium">{{ $paymentOrder->paid_at->format('M d, Y H:i') }}</span>
                    </div>
                @endif
                @if ($paymentOrder->paymentMethod)
                    <div class="flex justify-between">
                        <span class="text-base-content/50">{{ __('payment_method') }}</span>
                        <span class="text-base-content font-medium">{{ $paymentOrder->paymentMethod->getLocalizedDisplayName() }}</span>
                    </div>
                @endif
                @if ($paymentOrder->customer_name)
                    <div class="flex justify-between">
                        <span class="text-base-content/50">{{ __('customer_name') }}</span>
                        <span class="text-base-content">{{ $paymentOrder->customer_name }}</span>
                    </div>
                @endif
                @if ($paymentOrder->customer_email)
                    <div class="flex justify-between">
                        <span class="text-base-content/50">{{ __('customer_email') }}</span>
                        <span class="text-base-content">{{ $paymentOrder->customer_email }}</span>
                    </div>
                @endif
            </div>
        </div>

        @if ($paymentOrder->getLocalizedDescription())
            <div class="text-sm text-base-content/50 text-center mb-4">
                {{ $paymentOrder->getLocalizedDescription() }}
            </div>
        @endif

        <!-- Continue Button - Desktop -->
        <div class="hidden md:block">
            <a href="{{ $redirectUrl }}" class="btn btn-primary w-full">
                {{ __('continue') }}
                <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                </svg>
            </a>
        </div>

        <!-- Spacer for mobile sticky footer -->
        <div class="md:hidden h-16"></div>
    </div>

    <!-- Continue Button - Mobile (sticky footer) -->
    <div class="md:hidden fixed bottom-0 start-0 end-0 bg-base-100 border-t border-base-300 px-4 py-3 z-50">
        <a href="{{ $redirectUrl }}" class="btn btn-primary w-full">
            {{ __('continue') }}
            <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
            </svg>
        </a>
    </div>
@endsection
