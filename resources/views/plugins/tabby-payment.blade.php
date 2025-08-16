@extends('payment-gateway::layouts.payment-gateway')

@section('title', __('tabby_payment_gateway'))

@section('content')
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <div class="bg-white dark:bg-gray-900 rounded-lg shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-purple-600 to-pink-600 text-white px-6 py-4">
                    <h1 class="text-2xl font-bold">{{ $paymentMethod->getLocalizedDisplayName() }}</h1>
                    <p class="text-purple-100 mt-1">{{ __('pay_now_or_split_into_installments') }}</p>
                </div>
                <div class="p-6">
                    <div class="mb-6">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">{{ __('order_summary') }}
                        </h2>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-300">{{ __('order_code') }}:</span>
                                <span
                                    class="font-medium text-gray-900 dark:text-gray-100">{{ $paymentOrder->order_code }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-300">{{ __('amount') }}:</span>
                                <span
                                    class="font-bold text-xl text-green-600 dark:text-green-400">{{ $paymentOrder->formatted_amount }}</span>
                            </div>
                            @if ($paymentOrder->getLocalizedDescription())
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-300">{{ __('description') }}:</span>
                                    <span
                                        class="font-medium text-gray-900 dark:text-gray-100">{{ $paymentOrder->getLocalizedDescription() }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="mb-6">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">{{ __('customer_details') }}
                        </h2>
                        <div class="space-y-2">
                            @if ($paymentOrder->customer_name)
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-300">{{ __('customer_name') }}:</span>
                                    <span
                                        class="font-medium text-gray-900 dark:text-gray-100">{{ $paymentOrder->customer_name }}</span>
                                </div>
                            @endif
                            @if ($paymentOrder->customer_email)
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-300">{{ __('customer_email') }}:</span>
                                    <span
                                        class="font-medium text-gray-900 dark:text-gray-100">{{ $paymentOrder->customer_email }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    @if ($paymentProduct === 'installments')
                        <div
                            class="mb-6 bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-700 rounded-lg p-4">
                            <div class="flex items-center mb-2">
                                <svg class="w-5 h-5 text-purple-600 dark:text-purple-400 mr-2" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                </svg>
                                <h3 class="text-sm font-semibold text-purple-900 dark:text-purple-100">
                                    {{ __('pay_in_4_installments') }}</h3>
                            </div>
                            <p class="text-sm text-purple-700 dark:text-purple-300">
                                {{ __('split_your_payment_into_4_interest_free_installments', ['amount' => $paymentOrder->currency . ' ' . number_format($paymentOrder->amount / 4, 2)]) }}
                            </p>
                        </div>
                    @endif

                    <hr class="border-gray-200 dark:border-gray-700 mb-8">

                    <div class="space-y-4">
                        <!-- Loading State -->
                        <div id="tabby-loading" class="text-center py-8">
                            <div
                                class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-500 dark:text-gray-400">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-purple-600"
                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                </svg>
                                {{ __('initializing_payment') }}...
                            </div>
                        </div>

                        <!-- Error State -->
                        <div id="tabby-error"
                            class="hidden bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-lg p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
                                        {{ __('payment_initialization_failed') }}
                                    </h3>
                                    <div class="mt-2 text-sm text-red-700 dark:text-red-300">
                                        <p id="tabby-error-message">{{ __('unable_to_load_payment_form') }}</p>
                                    </div>
                                    <div class="mt-4">
                                        <button onclick="window.location.href='{{ $failureUrl }}'"
                                            class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-md text-sm">
                                            {{ __('go_back') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tabby Payment Container -->
                        <div id="tabby-checkout" class="hidden">
                            <!-- Tabby will inject its payment form here -->
                        </div>

                        <!-- Debug Info (temporary) -->
                        <div id="tabby-debug" class="mt-4 p-4 bg-gray-100 dark:bg-gray-800 rounded-lg text-sm">
                            <h4 class="font-semibold mb-2">Debug Information:</h4>
                            <div id="debug-content">
                                <p>Widget Status: <span id="widget-status">Initializing...</span></p>
                                <p>Element Count: <span id="element-count">0</span></p>
                                <p>Iframe Count: <span id="iframe-count">0</span></p>
                                <p>Button Count: <span id="button-count">0</span></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <!-- Tabby Web SDK -->
    <script>
        // Track script loading
        let tabbyScriptLoaded = false;
        let tabbyScriptError = null;

        // Create script element with error handling
        const tabbyScript = document.createElement('script');
        tabbyScript.src = 'https://checkout.tabby.ai/tabby-card.js';
        tabbyScript.async = true;

        tabbyScript.onload = function() {
            console.log('Tabby SDK script loaded successfully');
            tabbyScriptLoaded = true;
        };

        tabbyScript.onerror = function() {
            console.error('Tabby SDK script failed to load');
            tabbyScriptError = 'Script loading failed';
            tabbyScriptLoaded = false;

            // Try to diagnose the issue
            console.error('Script loading failed. Possible causes:');
            console.error('1. Network connectivity issues');
            console.error('2. Content Security Policy (CSP) blocking');
            console.error('3. Firewall or proxy blocking');
            console.error('4. Tabby service down');

            // Check if we can reach the domain
            fetch('https://checkout.tabby.ai/', {
                    mode: 'no-cors'
                })
                .then(() => console.log('Tabby domain is reachable'))
                .catch(() => console.error('Tabby domain is not reachable'));
        };

        // Add script to head
        document.head.appendChild(tabbyScript);

        // Set a timeout for script loading
        setTimeout(function() {
            if (!tabbyScriptLoaded) {
                console.error('Tabby SDK script loading timeout');
                if (document.getElementById('tabby-loading')) {
                    showError('Tabby SDK failed to load within timeout period');
                }
            }
        }, 10000); // 10 second timeout

        // Check for CSP issues
        try {
            const metaCSP = document.querySelector('meta[http-equiv="Content-Security-Policy"]');
            if (metaCSP) {
                console.log('Content Security Policy found:', metaCSP.content);
                if (!metaCSP.content.includes('checkout.tabby.ai')) {
                    console.warn('CSP might be blocking Tabby SDK. Add checkout.tabby.ai to script-src');
                }
            }
        } catch (e) {
            console.log('No CSP meta tag found');
        }
    </script>

    <script>
        // Debug logging
        console.log('Tabby Payment View Loaded');
        console.log('Payment Order:', @json($paymentOrder));
        console.log('Payment Method:', @json($paymentMethod));
        console.log('Public Key:', '{{ $publicKey }}');
        console.log('API URL:', '{{ $apiUrl }}');
        console.log('Sandbox Mode:', {{ $sandboxMode ? 'true' : 'false' }});
        console.log('Currency:', '{{ $currency }}');
        console.log('Payment Product:', '{{ $paymentProduct }}');

        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM Content Loaded');
            console.log('Tabby script loaded status:', tabbyScriptLoaded);
            console.log('Tabby script error:', tabbyScriptError);

            const loadingEl = document.getElementById('tabby-loading');
            const errorEl = document.getElementById('tabby-error');
            const checkoutEl = document.getElementById('tabby-checkout');
            const errorMessageEl = document.getElementById('tabby-error-message');

            if (!loadingEl || !errorEl || !checkoutEl || !errorMessageEl) {
                console.error('Required DOM elements not found:', {
                    loadingEl: !!loadingEl,
                    errorEl: !!errorEl,
                    checkoutEl: !!checkoutEl,
                    errorMessageEl: !!errorMessageEl
                });
                return;
            }

            function showError(message) {
                console.error('Showing error:', message);
                loadingEl.classList.add('hidden');
                errorMessageEl.textContent = message;
                errorEl.classList.remove('hidden');
            }

            function showCheckout() {
                console.log('Showing checkout form');
                loadingEl.classList.add('hidden');
                checkoutEl.classList.remove('hidden');
            }

            // Wait for Tabby script to load
            function waitForTabbyScript() {
                return new Promise((resolve, reject) => {
                    if (tabbyScriptLoaded) {
                        resolve();
                    } else if (tabbyScriptError) {
                        reject(new Error('Tabby script failed to load: ' + tabbyScriptError));
                    } else {
                        // Check every 100ms
                        const checkInterval = setInterval(() => {
                            if (tabbyScriptLoaded) {
                                clearInterval(checkInterval);
                                resolve();
                            } else if (tabbyScriptError) {
                                clearInterval(checkInterval);
                                reject(new Error('Tabby script failed to load: ' +
                                    tabbyScriptError));
                            }
                        }, 100);

                        // Timeout after 10 seconds
                        setTimeout(() => {
                            clearInterval(checkInterval);
                            reject(new Error('Tabby script loading timeout'));
                        }, 10000);
                    }
                });
            }

            // Tabby configuration
            const config = {
                selector: '#tabby-checkout',
                currency: '{{ $currency }}',
                lang: '{{ app()->getLocale() }}',
                payment: {
                    amount: '{{ round($paymentOrder->amount, 2) }}',
                    currency: '{{ $currency }}',
                    description: '{{ $paymentOrder->description ?: 'Order #' . $paymentOrder->order_code }}',
                    buyer: {
                        phone: '{{ $paymentOrder->customer_phone ?: '' }}',
                        email: '{{ $paymentOrder->customer_email ?: '' }}',
                        name: '{{ $paymentOrder->customer_name ?: '' }}',
                    },
                    order: {
                        tax_amount: '0.00',
                        shipping_amount: '0.00',
                        discount_amount: '0.00',
                        reference_id: '{{ $paymentOrder->order_code }}',
                        items: [{
                            title: '{{ $paymentOrder->description ?: 'Payment' }}',
                            description: 'Payment for order #{{ $paymentOrder->order_code }}',
                            quantity: 1,
                            unit_price: '{{ round($paymentOrder->amount, 2) }}',
                            discount_amount: '0.00',
                            reference_id: '{{ $paymentOrder->order_code }}',
                            image_url: '',
                            product_url: '',
                            category: 'general'
                        }]
                    },
                    buyer_history: {
                        registered_since: '{{ now()->subYear()->toISOString() }}',
                        loyalty_level: 0,
                    },
                    order_history: [{
                        purchased_at: '{{ now()->subMonth()->toISOString() }}',
                        amount: '{{ round($paymentOrder->amount, 2) }}',
                        payment_method: 'card',
                        status: 'new'
                    }],
                    meta: {
                        order_id: '{{ $paymentOrder->order_code }}',
                        customer: @json($paymentOrder->customer_data ?? [])
                    }
                },
                merchant_code: '{{ $publicKey }}',
                lang: '{{ app()->getLocale() }}',
                merchant_urls: {
                    success: '{{ $successUrl }}',
                    cancel: '{{ $failureUrl }}',
                    failure: '{{ $failureUrl }}'
                },
                @if ($sandboxMode)
                    sandbox: true,
                @endif
                onResult: function(result) {
                    console.log('Tabby payment result:', result);

                    if (result.status === 'created' || result.status === 'authorized') {
                        // Payment created or authorized successfully
                        const callbackData = {
                            status: result.status,
                            order_code: '{{ $paymentOrder->order_code }}',
                            payment_id: result.payment?.id,
                            tabby_id: result.id,
                            message: 'Payment processed successfully'
                        };

                        console.log('Sending callback data:', callbackData);

                        // Send callback data to our server
                        fetch('{{ $callbackUrl }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                    ?.getAttribute('content'),
                            },
                            body: JSON.stringify(callbackData)
                        }).then(response => {
                            console.log('Callback response:', response);
                            if (response.ok) {
                                window.location.href = '{{ $successUrl }}';
                            } else {
                                window.location.href = '{{ $failureUrl }}';
                            }
                        }).catch(error => {
                            console.error('Callback error:', error);
                            window.location.href = '{{ $failureUrl }}';
                        });
                    } else {
                        // Payment failed or cancelled
                        console.log('Payment failed or cancelled, redirecting to failure URL');
                        window.location.href = '{{ $failureUrl }}';
                    }
                },
                onClose: function() {
                    console.log('Tabby checkout closed');
                    // Optionally redirect to failure URL when user closes
                    // window.location.href = '{{ $failureUrl }}';
                }
            };

            console.log('Tabby config prepared:', config);

            // Initialize Tabby after script is loaded
            async function initializeTabby() {
                try {
                    console.log('Waiting for Tabby script to load...');
                    await waitForTabbyScript();

                    console.log('Tabby script loaded, checking if TabbyCard is available...');
                    console.log('window.TabbyCard:', window.TabbyCard);

                    // Initialize Tabby
                    if (window.TabbyCard) {
                        console.log('TabbyCard found, initializing...');
                        console.log('TabbyCard object:', window.TabbyCard);
                        console.log('TabbyCard prototype:', Object.getPrototypeOf(window.TabbyCard));
                        console.log('TabbyCard methods:', Object.getOwnPropertyNames(window.TabbyCard));
                        console.log('TabbyCard descriptor:', Object.getOwnPropertyDescriptor(window,
                            'TabbyCard'));

                        // Validate configuration before initialization
                        console.log('Validating Tabby configuration...');
                        if (!config.merchant_code) {
                            throw new Error('Merchant code is missing');
                        }
                        if (!config.payment.amount) {
                            throw new Error('Payment amount is missing');
                        }
                        if (!config.payment.currency) {
                            throw new Error('Payment currency is missing');
                        }
                        console.log('Configuration validation passed');

                        // Try to initialize with error handling
                        try {
                            console.log('Calling window.TabbyCard with config...');
                            console.log('TabbyCard type:', typeof window.TabbyCard);
                            console.log('TabbyCard constructor:', window.TabbyCard.constructor.name);

                            // Try different initialization methods
                            if (typeof window.TabbyCard === 'function') {
                                // If TabbyCard is a function, call it directly
                                window.TabbyCard(config);
                                console.log('TabbyCard called successfully');

                                // Wait a bit for the widget to render, then check if it's visible
                                setTimeout(() => {
                                    console.log('Checking Tabby widget after initialization...');
                                    const tabbyCheckout = document.getElementById('tabby-checkout');
                                    console.log('Tabby checkout element:', tabbyCheckout);
                                    console.log('Tabby checkout innerHTML length:', tabbyCheckout
                                        ?.innerHTML?.length || 0);
                                    console.log('Tabby checkout children count:', tabbyCheckout
                                        ?.children?.length || 0);

                                    // Check if there are any iframes or forms
                                    const iframes = tabbyCheckout?.querySelectorAll('iframe');
                                    const forms = tabbyCheckout?.querySelectorAll('form');
                                    const buttons = tabbyCheckout?.querySelectorAll('button');

                                    console.log('Found iframes:', iframes?.length || 0);
                                    console.log('Found forms:', forms?.length || 0);
                                    console.log('Found buttons:', buttons?.length || 0);

                                    if (iframes?.length > 0) {
                                        console.log('Tabby iframe found:', iframes[0]);
                                    }
                                    if (forms?.length > 0) {
                                        console.log('Tabby form found:', forms[0]);
                                    }
                                    if (buttons?.length > 0) {
                                        console.log('Tabby buttons found:', Array.from(buttons).map(b =>
                                            ({
                                                text: b.textContent,
                                                type: b.type,
                                                class: b.className
                                            })));
                                    }

                                    // Check if the widget is actually visible
                                    const computedStyle = window.getComputedStyle(tabbyCheckout);
                                    console.log('Tabby checkout computed style:', {
                                        display: computedStyle.display,
                                        visibility: computedStyle.visibility,
                                        opacity: computedStyle.opacity,
                                        height: computedStyle.height,
                                        width: computedStyle.width
                                    });

                                    // Update debug info
                                    updateDebugInfo(tabbyCheckout, iframes, forms, buttons);

                                    // If no content found, try to re-initialize
                                    if (tabbyCheckout?.children?.length === 0) {
                                        console.warn(
                                            'Tabby widget appears empty, attempting re-initialization...'
                                        );
                                        setTimeout(() => {
                                            window.TabbyCard(config);
                                            console.log('Re-initialization attempted');
                                        }, 1000);
                                    }

                                }, 2000); // Wait 2 seconds for widget to render

                            } else if (window.TabbyCard && typeof window.TabbyCard.init === 'function') {
                                // If it has an init method, use that
                                window.TabbyCard.init(config);
                                console.log('TabbyCard.init called successfully');
                            } else if (window.TabbyCard && typeof window.TabbyCard.create === 'function') {
                                // If it has a create method, use that
                                window.TabbyCard.create(config);
                                console.log('TabbyCard.create called successfully');
                            } else {
                                throw new Error('Unknown TabbyCard initialization method');
                            }

                            console.log('TabbyCard initialized successfully');
                            showCheckout();
                        } catch (initError) {
                            console.error('TabbyCard initialization failed:', initError);
                            throw new Error('TabbyCard initialization failed: ' + initError.message);
                        }
                    } else {
                        console.error('TabbyCard not found in window object');
                        console.error('Available window properties:', Object.keys(window).filter(key => key
                            .toLowerCase().includes('tabby')));
                        showError('{{ __('tabby_sdk_not_loaded') }}');
                    }
                } catch (error) {
                    console.error('Tabby initialization error:', error);
                    console.error('Error details:', {
                        message: error.message,
                        stack: error.stack,
                        name: error.name,
                        config: config
                    });

                    // Show more specific error messages
                    let errorMessage = '{{ __('failed_to_initialize_tabby_payment') }}';
                    if (error.message.includes('Merchant code is missing')) {
                        errorMessage = 'Merchant code is missing. Please check configuration.';
                    } else if (error.message.includes('Payment amount is missing')) {
                        errorMessage = 'Payment amount is missing. Please check configuration.';
                    } else if (error.message.includes('Payment currency is missing')) {
                        errorMessage = 'Payment currency is missing. Please check configuration.';
                    } else if (error.message.includes('TabbyCard initialization failed')) {
                        errorMessage = 'Tabby SDK initialization failed. Please try again.';
                    } else if (error.message.includes('script failed to load')) {
                        errorMessage =
                            'Tabby SDK failed to load. Please check your internet connection and try again.';
                    } else if (error.message.includes('timeout')) {
                        errorMessage = 'Tabby SDK loading timeout. Please try again.';
                    }

                    showError(errorMessage);
                }
            }

            // Start initialization
            initializeTabby();

            // Function to update debug information
            function updateDebugInfo(tabbyCheckout, iframes, forms, buttons) {
                const widgetStatus = document.getElementById('widget-status');
                const elementCount = document.getElementById('element-count');
                const iframeCount = document.getElementById('iframe-count');
                const buttonCount = document.getElementById('button-count');

                if (widgetStatus) widgetStatus.textContent = 'Widget Rendered';
                if (elementCount) elementCount.textContent = tabbyCheckout?.children?.length || 0;
                if (iframeCount) iframeCount.textContent = iframes?.length || 0;
                if (buttonCount) buttonCount.textContent = buttons?.length || 0;
            }

            // Continuous monitoring of widget state
            function startWidgetMonitoring() {
                const checkInterval = setInterval(() => {
                    const tabbyCheckout = document.getElementById('tabby-checkout');
                    if (tabbyCheckout && tabbyCheckout.children.length > 0) {
                        console.log('Tabby widget content detected!');
                        clearInterval(checkInterval);

                        // Final check after content is found
                        setTimeout(() => {
                            const finalCheck = document.getElementById('tabby-checkout');
                            console.log('Final widget check:', {
                                children: finalCheck?.children?.length || 0,
                                innerHTML: finalCheck?.innerHTML?.substring(0, 200) + '...',
                                computedStyle: window.getComputedStyle(finalCheck)
                            });
                        }, 500);
                    }
                }, 500); // Check every 500ms

                // Stop monitoring after 10 seconds
                setTimeout(() => {
                    clearInterval(checkInterval);
                    console.log('Widget monitoring stopped');
                }, 10000);
            }

            // Start monitoring
            startWidgetMonitoring();
        });

        // Additional error handling for script loading
        window.addEventListener('error', function(e) {
            console.error('Global error caught:', e);
            console.error('Error details:', {
                message: e.message,
                filename: e.filename,
                lineno: e.lineno,
                colno: e.colno,
                error: e.error
            });
        });

        // Check if Tabby SDK loaded
        window.addEventListener('load', function() {
            console.log('Window loaded, checking Tabby SDK...');
            if (window.TabbyCard) {
                console.log('Tabby SDK loaded successfully');
            } else {
                console.error('Tabby SDK failed to load');
            }
        });
    </script>
@endpush
