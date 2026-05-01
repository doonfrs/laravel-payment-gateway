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

        @php
            $shopOrderId = is_array($paymentOrder->customer_data ?? null)
                ? ($paymentOrder->customer_data['order_id'] ?? null)
                : null;
            $shopOrder = null;
            if ($shopOrderId && class_exists(\App\Models\Order::class)) {
                $shopOrder = \App\Models\Order::with(['orderItems', 'orderPayments' => fn ($q) => $q->whereNotNull('attachment')])
                    ->find($shopOrderId);
            }
            $linkedItems = $shopOrder
                ? $shopOrder->orderItems->filter(fn ($oi) => filled($oi->snapshotGet('item.payment_link')))
                : collect();
            $existingReceipt = $shopOrder
                ? optional($shopOrder->orderPayments->firstWhere(fn ($p) => filled($p->attachment)))->attachment
                : null;
            $existingReceiptIsPdf = $existingReceipt
                ? \Illuminate\Support\Str::endsWith(strtolower($existingReceipt), '.pdf')
                : false;
            $existingReceiptUrl = $existingReceipt
                ? \Illuminate\Support\Facades\Storage::disk('public')->url($existingReceipt)
                : null;
        @endphp

        @if ($linkedItems->isNotEmpty())
            <div class="bg-base-100 rounded-xl shadow-sm p-5 mb-5">
                <div class="font-bold text-sm mb-3">{{ __('Pay per product') }}</div>
                <ul class="space-y-2">
                    @foreach ($linkedItems as $oi)
                        <li class="flex items-center justify-between gap-3">
                            <span class="truncate text-sm">{{ $oi->display_name }} × {{ format_number($oi->count) }}</span>
                            <a href="{{ $oi->snapshotGet('item.payment_link') }}"
                                target="_blank" rel="noopener"
                                class="btn btn-primary btn-sm">
                                <x-heroicon-o-arrow-top-right-on-square class="w-4 h-4" />
                                {{ __('Pay') }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($shopOrder && ($paymentMethod?->require_receipt ?? false))
            <div class="bg-base-100 rounded-xl shadow-sm p-5 mb-5">
                <div class="font-bold text-sm mb-3">{{ __('Upload payment proof') }}</div>

                @if ($existingReceiptUrl)
                    <div class="flex items-center gap-3 mb-3">
                        @if ($existingReceiptIsPdf)
                            <a href="{{ $existingReceiptUrl }}" target="_blank" rel="noopener"
                                class="inline-flex items-center gap-2 text-primary">
                                <x-heroicon-o-document-arrow-down class="w-6 h-6" />
                                <span class="text-sm">{{ __('Open receipt') }}</span>
                            </a>
                        @else
                            <img src="{{ $existingReceiptUrl }}" alt="{{ __('Payment receipt') }}"
                                class="h-16 w-16 rounded-sm object-cover" />
                        @endif
                        <span class="text-sm text-base-content/60">{{ __('Receipt uploaded') }}</span>
                    </div>
                @endif

                @if (session('success'))
                    <div class="alert alert-success mb-3 text-sm">{{ session('success') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-error mb-3 text-sm">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <form method="POST"
                    action="{{ url('/payment/offline/' . $paymentOrder->order_code . '/receipt') }}"
                    enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <input type="file" name="payment_receipt"
                        accept="image/*,application/pdf"
                        class="file-input file-input-bordered w-full" required />
                    <p class="text-xs text-base-content/60">
                        {{ __('Image or PDF, up to 5 MB.') }}
                    </p>
                    <button type="submit" class="btn btn-secondary btn-sm">
                        <x-heroicon-o-arrow-up-tray class="w-4 h-4" />
                        {{ $existingReceiptUrl ? __('Replace receipt') : __('Upload payment proof') }}
                    </button>
                </form>
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
