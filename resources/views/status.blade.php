@extends('payment-gateway::layouts.payment-gateway')

@section('title', __('payment_status'))

@section('content')
    <!-- Header Bar -->
    <div class="bg-base-100 shadow-sm sticky top-0 z-40">
        <div class="container mx-auto max-w-lg px-4">
            <div class="flex items-center h-14">
                <h1 class="text-lg font-bold text-base-content">{{ __('payment_status') }}</h1>
            </div>
        </div>
    </div>

    <div class="container mx-auto max-w-lg px-4 py-4 md:py-6">

        <!-- Status Indicator -->
        <div class="text-center mb-5">
            @if ($paymentOrder->status === 'pending')
                <div class="w-14 h-14 bg-warning/15 rounded-full flex items-center justify-center mx-auto mb-3 animate-pulse">
                    <svg class="w-7 h-7 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-base-content">{{ __('pending') }}</h2>
                <p class="text-sm text-base-content/50 mt-1">{{ __('status_pending_message') }}</p>
            @elseif ($paymentOrder->status === 'completed')
                <div class="w-14 h-14 bg-success/15 rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg class="w-7 h-7 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-base-content">{{ __('completed') }}</h2>
                <p class="text-sm text-base-content/50 mt-1">{{ __('status_completed_message') }}</p>
            @elseif ($paymentOrder->status === 'failed')
                <div class="w-14 h-14 bg-error/15 rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg class="w-7 h-7 text-error" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-base-content">{{ __('failed') }}</h2>
                <p class="text-sm text-base-content/50 mt-1">{{ __('status_failed_message') }}</p>
            @elseif ($paymentOrder->status === 'cancelled')
                <div class="w-14 h-14 bg-base-content/10 rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg class="w-7 h-7 text-base-content/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728L18.364 5.636M5.636 18.364l12.728-12.728"></path>
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-base-content">{{ __('cancelled') }}</h2>
                <p class="text-sm text-base-content/50 mt-1">{{ __('status_cancelled_message') }}</p>
            @else
                <div class="w-14 h-14 bg-base-content/10 rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg class="w-7 h-7 text-base-content/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-base-content">{{ __('status') }}</h2>
                <p class="text-sm text-base-content/50 mt-1">{{ __('status_unknown_message') }}</p>
            @endif
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
                    <div class="text-xl font-bold mt-0.5
                        @if ($paymentOrder->status === 'completed') text-success
                        @elseif ($paymentOrder->status === 'failed') text-error
                        @elseif ($paymentOrder->status === 'pending') text-warning
                        @else text-base-content @endif">
                        {{ $paymentOrder->formatted_amount }}
                    </div>
                </div>
            </div>

            <div class="border-t border-base-200 pt-3 space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-base-content/50">{{ __('status') }}</span>
                    <span class="badge badge-sm gap-1
                        @if ($paymentOrder->status === 'completed') badge-success
                        @elseif ($paymentOrder->status === 'failed') badge-error
                        @elseif ($paymentOrder->status === 'pending') badge-warning
                        @elseif ($paymentOrder->status === 'cancelled') badge-ghost
                        @else badge-ghost @endif">
                        {{ __('' . $paymentOrder->status) }}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-base-content/50">{{ __('date') }}</span>
                    <span class="text-base-content font-medium">{{ $paymentOrder->created_at->format('M d, Y H:i') }}</span>
                </div>
                @if ($paymentOrder->paid_at)
                    <div class="flex justify-between">
                        <span class="text-base-content/50">{{ __('paid_at') }}</span>
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

        @if ($paymentOrder->payment_data && isset($paymentOrder->payment_data['error']))
            <div class="alert alert-error mb-4">
                <svg class="h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd"
                        d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                        clip-rule="evenodd" />
                </svg>
                <span>{{ $paymentOrder->payment_data['error'] }}</span>
            </div>
        @endif

        <!-- Action Buttons - Desktop -->
        <div class="hidden md:flex flex-col gap-2">
            @if ($paymentOrder->status === 'pending')
                <button onclick="location.reload()" class="btn btn-primary w-full">
                    {{ __('refresh_status') }}
                </button>
                <a href="{{ payment_gateway_localized_url(route('payment-gateway.checkout', ['order' => $paymentOrder->order_code])) }}"
                    class="btn btn-outline w-full">
                    {{ __('continue_payment') }}
                </a>
            @endif

            @if ($paymentOrder->status === 'completed' && $paymentOrder->success_url)
                <a href="{{ $paymentOrder->success_url }}" class="btn btn-primary w-full">
                    {{ __('continue') }}
                    <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                    </svg>
                </a>
            @endif

            @if ($paymentOrder->status === 'failed' && $paymentOrder->failure_url)
                <a href="{{ $paymentOrder->failure_url }}" class="btn btn-error btn-outline w-full">
                    {{ __('go_back') }}
                </a>
            @endif
        </div>

        <!-- Spacer for mobile sticky footer -->
        <div class="md:hidden h-20"></div>
    </div>

    <!-- Action Buttons - Mobile (sticky footer) -->
    @if ($paymentOrder->status === 'pending' || ($paymentOrder->status === 'completed' && $paymentOrder->success_url) || ($paymentOrder->status === 'failed' && $paymentOrder->failure_url))
        <div class="md:hidden fixed bottom-0 start-0 end-0 bg-base-100 border-t border-base-300 px-4 py-3 z-50">
            <div class="flex flex-col gap-2">
                @if ($paymentOrder->status === 'pending')
                    <button onclick="location.reload()" class="btn btn-primary w-full">
                        {{ __('refresh_status') }}
                    </button>
                    <a href="{{ payment_gateway_localized_url(route('payment-gateway.checkout', ['order' => $paymentOrder->order_code])) }}"
                        class="btn btn-outline w-full">
                        {{ __('continue_payment') }}
                    </a>
                @endif

                @if ($paymentOrder->status === 'completed' && $paymentOrder->success_url)
                    <a href="{{ $paymentOrder->success_url }}" class="btn btn-primary w-full">
                        {{ __('continue') }}
                        <svg class="w-4 h-4 rtl:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                        </svg>
                    </a>
                @endif

                @if ($paymentOrder->status === 'failed' && $paymentOrder->failure_url)
                    <a href="{{ $paymentOrder->failure_url }}" class="btn btn-error btn-outline w-full">
                        {{ __('go_back') }}
                    </a>
                @endif
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        // Auto-refresh for pending orders
        @if ($paymentOrder->status === 'pending')
            setTimeout(function() {
                location.reload();
            }, 10000);
        @endif
    </script>
@endpush
