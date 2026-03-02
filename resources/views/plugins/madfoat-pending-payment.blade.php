@extends('payment-gateway::layouts.payment-gateway')

@section('title', __('efawateercom_payment'))

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4">
                    <x-heroicon-o-document-text class="w-8 h-8 text-primary" />
                </div>
                <h1 class="text-3xl font-bold text-base-content">{{ __('efawateercom_payment') }}</h1>
                <p class="text-base-content/70 mt-2">{{ __('pay_your_bill_through_bank_app') }}</p>
            </div>

            <!-- Bill Number Card -->
            <div class="card bg-base-100 shadow-xl mb-6">
                <div class="card-body text-center">
                    <h2 class="card-title text-xl justify-center mb-4">
                        <x-heroicon-o-hashtag class="w-6 h-6" />
                        {{ __('your_bill_number') }}
                    </h2>

                    <div class="bg-base-200 rounded-lg p-6 mb-4">
                        <p class="text-4xl font-mono font-bold text-primary tracking-widest select-all"
                           id="billing-number">{{ $billingNumber }}</p>
                    </div>

                    <button type="button"
                            onclick="navigator.clipboard.writeText('{{ $billingNumber }}').then(() => { this.querySelector('span').textContent = '{{ __('copied') }}'; setTimeout(() => { this.querySelector('span').textContent = '{{ __('copy_bill_number') }}'; }, 2000); })"
                            class="btn btn-outline btn-primary btn-sm gap-2">
                        <x-heroicon-o-clipboard-document class="w-4 h-4" />
                        <span>{{ __('copy_bill_number') }}</span>
                    </button>
                </div>
            </div>

            <!-- Order Details Card -->
            <div class="card bg-base-100 shadow-xl mb-6">
                <div class="card-body">
                    <h2 class="card-title text-xl mb-4">{{ __('order_details') }}</h2>

                    <div class="space-y-3">
                        <div class="flex justify-between items-center py-2 border-b border-base-300">
                            <span class="text-base-content/70">{{ __('order_code') }}</span>
                            <span class="font-semibold">#{{ $paymentOrder->order_code }}</span>
                        </div>

                        <div class="flex justify-between items-center py-2 border-b border-base-300">
                            <span class="text-base-content/70">{{ __('amount') }}</span>
                            <span class="font-bold text-lg text-primary">{{ $paymentOrder->formatted_amount }}</span>
                        </div>

                        @if ($paymentOrder->customer_name)
                            <div class="flex justify-between items-center py-2 border-b border-base-300">
                                <span class="text-base-content/70">{{ __('customer_name') }}</span>
                                <span class="font-medium">{{ $paymentOrder->customer_name }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Instructions Card -->
            <div class="card bg-base-100 shadow-xl mb-6">
                <div class="card-body">
                    <h2 class="card-title text-xl mb-4">
                        <x-heroicon-o-information-circle class="w-6 h-6 text-info" />
                        {{ __('payment_details') }}
                    </h2>

                    <div class="text-base-content/80 leading-relaxed">
                        <p>{{ $instructions }}</p>
                    </div>

                    <div class="divider"></div>

                    <ol class="list-decimal list-inside space-y-2 text-base-content/70 text-sm">
                        <li>{{ __('open_your_bank_app') }}</li>
                        <li>{{ __('go_to_efawateercom') }}</li>
                        <li>{{ __('search_for_business_name') }}</li>
                        <li>{{ __('enter_your_bill_number') }} <span class="font-mono font-bold text-primary">{{ $billingNumber }}</span></li>
                        <li>{{ __('confirm_and_pay') }}</li>
                    </ol>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <div class="flex flex-col gap-4">
                        <a href="{{ $successUrl }}" class="btn btn-primary btn-lg w-full gap-2">
                            <x-heroicon-o-check-circle class="w-6 h-6" />
                            {{ __('continue') }}
                        </a>

                        <a href="{{ payment_gateway_localized_url(route('payment-gateway.checkout', ['order' => $paymentOrder->order_code])) }}"
                           class="btn btn-outline btn-lg w-full gap-2">
                            <x-heroicon-o-arrow-left class="w-5 h-5" />
                            {{ __('back_to_payment_methods') }}
                        </a>
                    </div>
                </div>
            </div>

            <!-- Info Alert -->
            <div class="alert alert-info mt-6">
                <x-heroicon-o-clock class="w-6 h-6" />
                <div>
                    <h3 class="font-bold">{{ __('payment_processing') }}</h3>
                    <div class="text-sm">
                        {{ __('order_confirmed_after_efawateercom') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
