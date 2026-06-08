@extends('payment-gateway::layouts.payment-gateway')

@section('title', __('Redirecting to Madfoat DirectPay'))

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-lg mx-auto">
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body">

                    {{-- Header --}}
                    <div class="text-center mb-6">
                        <div class="w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-3">
                            <x-heroicon-o-arrow-right-circle class="w-6 h-6 text-primary" />
                        </div>
                        <h1 class="text-xl font-bold text-base-content">{{ __('Redirecting to Madfoat DirectPay') }}</h1>
                        <p class="text-base-content/60 text-sm mt-1">
                            {{ __('You will be sent to eFAWATEERcom to complete the payment with your bank.') }}
                        </p>
                    </div>

                    {{-- Order summary --}}
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

                    {{-- Loading state --}}
                    <div class="flex items-center justify-center gap-3 py-4 text-base-content/60 text-sm">
                        <span class="loading loading-spinner loading-sm"></span>
                        <span>{{ __('Connecting to Madfoat DirectPay...') }}</span>
                    </div>

                    {{-- Auto-submit form --}}
                    <form id="madfoat-directpay-form" action="{{ $gatewayUrl }}" method="POST" accept-charset="UTF-8" class="hidden">
                        <input type="hidden" name="RequestParams" value="{{ $requestParams }}">
                        <noscript>
                            <button type="submit" class="btn btn-primary w-full">
                                {{ __('Continue to Madfoat DirectPay') }}
                            </button>
                        </noscript>
                    </form>

                    {{-- Fallback button (shown if JS is enabled but auto-submit failed for any reason) --}}
                    <div class="mt-4">
                        <button type="button"
                                onclick="document.getElementById('madfoat-directpay-form').submit()"
                                class="btn btn-ghost btn-sm w-full">
                            {{ __('Click here if you are not redirected automatically') }}
                        </button>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-submit on next tick so the spinner has time to render.
        window.addEventListener('DOMContentLoaded', function () {
            setTimeout(function () {
                document.getElementById('madfoat-directpay-form').submit();
            }, 250);
        });
    </script>
@endsection
