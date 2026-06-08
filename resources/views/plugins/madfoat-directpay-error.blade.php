@extends('payment-gateway::layouts.payment-gateway')

@section('title', __('Madfoat DirectPay: Cannot Initialize'))

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-lg mx-auto">
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body">

                    <div class="text-center mb-6">
                        <div class="w-12 h-12 bg-error/10 rounded-full flex items-center justify-center mx-auto mb-3">
                            <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-error" />
                        </div>
                        <h1 class="text-xl font-bold text-base-content">{{ __('Madfoat DirectPay: Cannot Initialize') }}</h1>
                        <p class="text-base-content/60 text-sm mt-1">
                            {{ __('We could not start a DirectPay payment for this order.') }}
                        </p>
                    </div>

                    <div class="bg-base-200 rounded-sm p-4 space-y-2 mb-4 text-sm">
                        <div class="flex justify-between items-center">
                            <span class="text-base-content/60">{{ __('Order Code') }}</span>
                            <span class="font-medium">#{{ $paymentOrder->order_code }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-base-content/60">{{ __('Amount') }}</span>
                            <span class="font-bold">{{ $paymentOrder->formatted_amount }}</span>
                        </div>
                    </div>

                    <div role="alert" class="alert alert-error mb-4">
                        <x-heroicon-o-x-circle class="w-5 h-5" />
                        <span>{{ $errorMessage }}</span>
                    </div>

                    <div class="flex flex-col gap-3">
                        <a href="{{ $failureUrl }}" class="btn btn-primary w-full gap-2">
                            <x-heroicon-o-arrow-left class="w-4 h-4" />
                            {{ __('Back') }}
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </div>
@endsection
