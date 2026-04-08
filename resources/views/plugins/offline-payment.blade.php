@extends('payment-gateway::layouts.payment-gateway')

@section('title', __('offline_payment_gateway'))

@section('content')
    <div class="container mx-auto px-4 py-4">
        <div class="max-w-2xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-4">
                <div class="w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-2">
                    <x-heroicon-o-banknotes class="w-6 h-6 text-primary" />
                </div>
                <h1 class="text-2xl font-bold text-base-content">{{ $paymentMethod->getLocalizedDisplayName() }}</h1>
                <p class="text-base-content/70 text-sm mt-1">{{ __('offline_payment_gateway') }}</p>
                @if (\Trinavo\PaymentGateway\Facades\PaymentGateway::getAvailablePaymentMethodsForOrder($paymentOrder)->count() > 1)
                    <a href="{{ \Trinavo\PaymentGateway\Facades\PaymentGateway::getPaymentUrl($paymentOrder) }}" class="link link-primary text-sm mt-2 inline-flex items-center gap-1">
                        <x-heroicon-o-arrow-uturn-left class="w-4 h-4" />
                        {{ __('Change Payment Method') }}
                    </a>
                @endif
            </div>

            <!-- Payment Details Card -->
            <div class="card bg-base-100 shadow-xl mb-4">
                <div class="card-body p-4">
                    <h2 class="card-title text-lg mb-2">{{ __('order_summary') }}</h2>

                    <div class="space-y-1">
                        <div class="flex justify-between items-center py-1.5 border-b border-base-300">
                            <span class="text-base-content/70 text-sm">{{ __('amount') }}</span>
                            <span class="font-bold text-lg text-primary">{{ $paymentOrder->formatted_amount }}</span>
                        </div>

                        @if ($paymentOrder->customer_name)
                            <div class="flex justify-between items-center py-1.5 border-b border-base-300">
                                <span class="text-base-content/70 text-sm">{{ __('customer_name') }}</span>
                                <span class="font-semibold text-sm text-base-content">{{ $paymentOrder->customer_name }}</span>
                            </div>
                        @endif

                        @if ($paymentOrder->customer_email)
                            <div class="flex justify-between items-center py-1.5">
                                <span class="text-base-content/70 text-sm">{{ __('customer_email') }}</span>
                                <span class="font-semibold text-sm text-base-content">{{ $paymentOrder->customer_email }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Payment Instructions Card -->
            @if ($description)
                <div class="card bg-base-100 shadow-xl mb-4">
                    <div class="card-body p-4">
                        <h2 class="card-title text-lg mb-2">
                            {{ $paymentMethod->getLocalizedDisplayName() }}
                        </h2>
                        <div class="text-base-content/80 text-sm leading-relaxed">{!! $description !!}</div>
                    </div>
                </div>
            @endif

            <!-- Security Notice -->
            <div class="alert alert-info mb-4">
                <x-heroicon-o-information-circle class="w-5 h-5" />
                <div>
                    <h3 class="font-bold">{{ __('Secure Payment') }}</h3>
                    <div class="text-sm">
                        {{ __('Your payment is processed securely. Transaction details will be recorded for your reference.') }}
                    </div>
                </div>
            </div>

            <!-- Action Buttons - Desktop -->
            <div class="hidden md:flex justify-between">
                <form method="POST"
                    action="{{ payment_gateway_localized_url(route('payment-gateway.offline-confirm', ['order' => $paymentOrder->order_code])) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary">
                        <x-heroicon-o-check-circle class="w-5 h-5" />
                        {{ __('confirm_order') }}
                    </button>
                </form>

                <a href="{{ route('payment-gateway.cancel', ['order' => $paymentOrder->order_code]) }}"
                    class="btn btn-outline">
                    <x-heroicon-o-x-mark class="w-5 h-5" />
                    {{ __('Cancel') }}
                </a>
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

                <a href="{{ route('payment-gateway.cancel', ['order' => $paymentOrder->order_code]) }}"
                    class="btn btn-outline">
                    <x-heroicon-o-x-mark class="w-5 h-5" />
                    {{ __('Cancel') }}
                </a>
            </div>
            <!-- Spacer for mobile sticky footer -->
            <div class="md:hidden h-16"></div>
        </div>
    </div>
@endsection
