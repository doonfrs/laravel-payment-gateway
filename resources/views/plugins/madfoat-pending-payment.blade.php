@extends('payment-gateway::layouts.payment-gateway')

@section('title', __('efawateercom_payment'))

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-lg mx-auto">
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body">

                    {{-- Header --}}
                    <div class="text-center mb-6">
                        <div class="w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-3">
                            <x-heroicon-o-document-text class="w-6 h-6 text-primary" />
                        </div>
                        <h1 class="text-xl font-bold text-base-content">{{ __('efawateercom_payment') }}</h1>
                        <p class="text-base-content/60 text-sm mt-1">{{ __('pay_your_bill_through_bank_app') }}</p>
                    </div>

                    {{-- Bill Number Hero --}}
                    <div class="bg-base-200 rounded-sm p-6 text-center mb-2">
                        <p class="text-base-content/50 text-xs uppercase tracking-wider mb-2">{{ __('your_bill_number') }}</p>
                        <p class="text-3xl font-mono font-bold text-base-content tracking-widest select-all"
                           id="billing-number">{{ $billingNumber }}</p>
                    </div>

                    <div class="text-center mb-4">
                        <button type="button"
                                onclick="navigator.clipboard.writeText('{{ $billingNumber }}').then(() => { this.querySelector('span').textContent = '{{ __('copied') }}'; setTimeout(() => { this.querySelector('span').textContent = '{{ __('copy_bill_number') }}'; }, 2000); })"
                                class="btn btn-ghost btn-sm gap-2">
                            <x-heroicon-o-clipboard-document class="w-4 h-4" />
                            <span>{{ __('copy_bill_number') }}</span>
                        </button>
                    </div>

                    {{-- Order Details --}}
                    <div class="divider text-xs text-base-content/40">{{ __('order_details') }}</div>

                    <div class="space-y-2 text-sm mb-4">
                        <div class="flex justify-between items-center">
                            <span class="text-base-content/60">{{ __('order_code') }}</span>
                            <span class="font-medium">#{{ $paymentOrder->order_code }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-base-content/60">{{ __('amount') }}</span>
                            <span class="font-bold">{{ $paymentOrder->formatted_amount }}</span>
                        </div>
                        @if ($paymentOrder->customer_name)
                            <div class="flex justify-between items-center">
                                <span class="text-base-content/60">{{ __('customer_name') }}</span>
                                <span class="font-medium">{{ $paymentOrder->customer_name }}</span>
                            </div>
                        @endif
                    </div>

                    {{-- Instructions --}}
                    <div class="divider text-xs text-base-content/40">{{ __('how_to_pay') }}</div>

                    <div class="text-sm text-base-content/70 mb-3">
                        <p>{{ $instructions }}</p>
                    </div>

                    <ol class="list-decimal ltr:list-inside rtl:list-inside space-y-1.5 text-sm text-base-content/60 mb-4">
                        <li>{{ __('open_your_bank_app') }}</li>
                        <li>{{ __('go_to_efawateercom') }}</li>
                        <li>{{ __('search_for_business_name') }}</li>
                        <li>{{ __('enter_your_bill_number') }} <span class="font-mono font-semibold text-base-content">{{ $billingNumber }}</span></li>
                        <li>{{ __('confirm_and_pay') }}</li>
                    </ol>

                    {{-- Processing Note --}}
                    <div class="flex items-center gap-2 text-xs text-base-content/50 mb-6">
                        <x-heroicon-o-clock class="w-4 h-4 shrink-0" />
                        <span>{{ __('order_confirmed_after_efawateercom') }}</span>
                    </div>

                    {{-- Action Buttons --}}
                    <div class="divider"></div>

                    <div class="flex flex-col gap-3">
                        <a href="{{ $successUrl }}" class="btn btn-primary w-full gap-2">
                            <x-heroicon-o-check-circle class="w-5 h-5" />
                            {{ __('i_have_already_paid') }}
                        </a>

                        <a href="{{ payment_gateway_localized_url(route('payment-gateway.checkout', ['order' => $paymentOrder->order_code])) }}"
                           class="btn btn-ghost btn-sm w-full gap-2">
                            <x-heroicon-o-arrow-left class="w-4 h-4" />
                            {{ __('back_to_payment_methods') }}
                        </a>
                    </div>

                </div>
            </div>
        </div>
    </div>
@endsection
