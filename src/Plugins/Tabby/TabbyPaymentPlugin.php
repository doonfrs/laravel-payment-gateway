<?php

namespace Trinavo\PaymentGateway\Plugins\Tabby;

use Trinavo\PaymentGateway\Configuration\CheckboxField;
use Trinavo\PaymentGateway\Configuration\TextField;
use Trinavo\PaymentGateway\Configuration\SelectField;
use Trinavo\PaymentGateway\Contracts\PaymentPluginInterface;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Illuminate\Support\Facades\Log;

class TabbyPaymentPlugin extends PaymentPluginInterface
{
    public function getName(): string
    {
        return __('Tabby Payment Plugin');
    }

    public function getDescription(): string
    {
        return __('Integrate Tabby Buy Now Pay Later (BNPL) payment solution. Allows customers to pay in 4 interest-free installments.');
    }

    public function getConfigurationFields(): array
    {
        return [
            new TextField(
                name: 'public_key_sandbox',
                label: 'Public Key (Sandbox)',
                required: true,
                encrypted: true,
                description: 'Your Tabby public API key for sandbox environment.'
            ),
            new TextField(
                name: 'secret_key_sandbox',
                label: 'Secret Key (Sandbox)',
                required: true,
                encrypted: true,
                description: 'Your Tabby secret API key for sandbox environment.'
            ),

            new TextField(
                name: 'public_key_production',
                label: 'Public Key (Production)',
                required: false,
                encrypted: true,
                description: 'Your Tabby public API key for production environment.'
            ),
            new TextField(
                name: 'secret_key_production',
                label: 'Secret Key (Production)',
                required: false,
                encrypted: true,
                description: 'Your Tabby secret API key for production environment.'
            ),

            new CheckboxField(
                name: 'sandbox_mode',
                label: 'Sandbox Mode',
                default: true,
                description: 'Enable sandbox mode for Tabby payments.'
            ),

            new SelectField(
                name: 'payment_product',
                label: 'Payment Product',
                required: true,
                options: [
                    'installments' => 'Pay in 4 Installments',
                    'monthly' => 'Monthly Payments',
                    'credit' => 'Credit Card Payments'
                ],
                default: 'installments',
                description: 'Select the Tabby payment product to offer to customers.'
            ),

            new SelectField(
                name: 'supported_currency',
                label: 'Supported Currency',
                required: true,
                options: [
                    'AED' => 'UAE Dirham (AED)',
                    'SAR' => 'Saudi Riyal (SAR)',
                    'KWD' => 'Kuwaiti Dinar (KWD)',
                    'BHD' => 'Bahraini Dinar (BHD)',
                    'QAR' => 'Qatari Riyal (QAR)',
                    'EGP' => 'Egyptian Pound (EGP)'
                ],
                default: 'AED',
                description: 'Select the currency for Tabby payments.'
            ),
        ];
    }

    public function validateConfiguration(): bool
    {
        $sandboxMode = $this->paymentMethod->getSetting('sandbox_mode', true);
        
        if ($sandboxMode) {
            return !empty($this->paymentMethod->getSetting('public_key_sandbox')) && 
                   !empty($this->paymentMethod->getSetting('secret_key_sandbox'));
        }
        
        return !empty($this->paymentMethod->getSetting('public_key_production')) && 
               !empty($this->paymentMethod->getSetting('secret_key_production'));
    }

    public function processPayment(PaymentOrder $paymentOrder)
    {
        // Log payment processing start
        Log::info('Tabby Payment Processing Started', [
            'order_code' => $paymentOrder->order_code,
            'amount' => $paymentOrder->amount,
            'customer_email' => $paymentOrder->customer_email,
            'payment_method_id' => $this->paymentMethod->id ?? 'unknown'
        ]);

        // Ensure settings relationship is loaded
        if (!$this->paymentMethod->relationLoaded('settings')) {
            Log::info('Loading settings relationship for payment method', [
                'payment_method_id' => $this->paymentMethod->id ?? 'unknown'
            ]);
            $this->paymentMethod->load('settings');
        }

        // Log all plugin configuration fields and their values
        $allSettings = $this->paymentMethod->settings ?? [];
        Log::info('Tabby Plugin Configuration Details', [
            'payment_method_id' => $this->paymentMethod->id ?? 'unknown',
            'plugin_class' => $this->paymentMethod->plugin_class ?? 'unknown',
            'all_settings' => $allSettings,
            'configuration_fields' => array_map(function($field) {
                return [
                    'name' => $field->getName(),
                    'type' => get_class($field),
                    'required' => $field->isRequired(),
                    'default' => $field->getDefault()
                ];
            }, $this->getConfigurationFields())
        ]);

        // Log payment method object details
        Log::info('Tabby Payment Method Object Details', [
            'payment_method_id' => $this->paymentMethod->id ?? 'unknown',
            'payment_method_class' => get_class($this->paymentMethod),
            'payment_method_attributes' => $this->paymentMethod->getAttributes(),
            'payment_method_relations' => array_keys($this->paymentMethod->getRelations()),
            'has_settings_property' => property_exists($this->paymentMethod, 'settings'),
            'has_getSetting_method' => method_exists($this->paymentMethod, 'getSetting'),
            'settings_type' => gettype($this->paymentMethod->settings),
            'settings_count' => is_array($this->paymentMethod->settings) ? count($this->paymentMethod->settings) : 'not_array',
            'settings_loaded' => $this->paymentMethod->relationLoaded('settings'),
            'settings_collection' => $this->paymentMethod->settings instanceof \Illuminate\Database\Eloquent\Collection ? $this->paymentMethod->settings->count() : 'not_collection'
        ]);

        $sandboxMode = $this->paymentMethod->getSetting('sandbox_mode', true);
        
        if ($sandboxMode) {
            $publicKey = $this->paymentMethod->getSetting('public_key_sandbox');
            $secretKey = $this->paymentMethod->getSetting('secret_key_sandbox');
            $apiUrl = 'https://api-sandbox.tabby.ai';
        } else {
            $publicKey = $this->paymentMethod->getSetting('public_key_production');
            $secretKey = $this->paymentMethod->getSetting('secret_key_production');
            $apiUrl = 'https://api.tabby.ai';
        }

        // If settings are empty, try fallback method
        if (empty($publicKey) || empty($secretKey)) {
            Log::warning('Tabby Settings Empty, Trying Fallback Method', [
                'order_code' => $paymentOrder->order_code,
                'public_key_sandbox' => $publicKey,
                'secret_key_sandbox' => $secretKey ? '***' . substr($secretKey, -4) : null,
                'public_key_production' => $this->paymentMethod->getSetting('public_key_production'),
                'secret_key_production' => $this->paymentMethod->getSetting('secret_key_production') ? '***' . substr($this->paymentMethod->getSetting('secret_key_production'), -4) : null
            ]);

            if ($sandboxMode) {
                $publicKey = $this->getSettingFallback('public_key_sandbox');
                $secretKey = $this->getSettingFallback('secret_key_sandbox');
            } else {
                $publicKey = $this->getSettingFallback('public_key_production');
                $secretKey = $this->getSettingFallback('secret_key_production');
            }

            Log::info('Tabby Settings After Fallback', [
                'order_code' => $paymentOrder->order_code,
                'public_key_sandbox' => $publicKey,
                'secret_key_sandbox' => $secretKey ? '***' . substr($secretKey, -4) : null,
                'fallback_used' => true
            ]);
        }

        // Log individual setting values for debugging
        Log::info('Tabby Individual Settings Retrieved', [
            'order_code' => $paymentOrder->order_code,
            'sandbox_mode' => $sandboxMode,
            'public_key_sandbox' => $this->paymentMethod->getSetting('public_key_sandbox'),
            'secret_key_sandbox' => $this->paymentMethod->getSetting('secret_key_sandbox') ? '***' . substr($this->paymentMethod->getSetting('secret_key_sandbox'), -4) : null,
            'public_key_production' => $this->paymentMethod->getSetting('public_key_production'),
            'secret_key_production' => $this->paymentMethod->getSetting('secret_key_production') ? '***' . substr($this->paymentMethod->getSetting('secret_key_production'), -4) : null,
            'supported_currency' => $this->paymentMethod->getSetting('supported_currency'),
            'payment_product' => $this->paymentMethod->getSetting('payment_product')
        ]);

        // Log configuration details
        Log::info('Tabby Configuration Loaded', [
            'sandbox_mode' => $sandboxMode,
            'api_url' => $apiUrl,
            'public_key_length' => strlen($publicKey),
            'secret_key_length' => strlen($secretKey),
            'has_public_key' => !empty($publicKey),
            'has_secret_key' => !empty($secretKey)
        ]);

        $currency = $this->paymentMethod->getSetting('supported_currency', 'AED');
        $paymentProduct = $this->paymentMethod->getSetting('payment_product', 'installments');
        
        // If other settings are empty, try fallback method
        if (empty($currency) || empty($paymentProduct)) {
            Log::warning('Tabby Additional Settings Empty, Trying Fallback Method', [
                'order_code' => $paymentOrder->order_code,
                'supported_currency' => $currency,
                'payment_product' => $paymentProduct
            ]);

            if (empty($currency)) {
                $currency = $this->getSettingFallback('supported_currency', 'AED');
            }
            if (empty($paymentProduct)) {
                $paymentProduct = $this->getSettingFallback('payment_product', 'installments');
            }

            Log::info('Tabby Additional Settings After Fallback', [
                'order_code' => $paymentOrder->order_code,
                'supported_currency' => $currency,
                'payment_product' => $paymentProduct,
                'fallback_used' => true
            ]);
        }

        // Prepare the checkout session data
        $checkoutData = [
            'payment' => [
                'amount' => (string) round($paymentOrder->amount, 2),
                'currency' => $currency,
                'description' => $paymentOrder->description ?: 'Order #' . $paymentOrder->order_code,
                'buyer' => [
                    'phone' => $paymentOrder->customer_phone ?: '',
                    'email' => $paymentOrder->customer_email ?: '',
                    'name' => $paymentOrder->customer_name ?: '',
                ],
                'buyer_history' => [
                    'registered_since' => now()->subYear()->toISOString(),
                    'loyalty_level' => 0,
                ],
                'order' => [
                    'tax_amount' => '0.00',
                    'shipping_amount' => '0.00',
                    'discount_amount' => '0.00',
                    'updated_at' => now()->toISOString(),
                    'reference_id' => $paymentOrder->order_code,
                    'items' => [
                        [
                            'title' => $paymentOrder->description ?: 'Payment',
                            'description' => 'Payment for order #' . $paymentOrder->order_code,
                            'quantity' => 1,
                            'unit_price' => (string) round($paymentOrder->amount, 2),
                            'discount_amount' => '0.00',
                            'reference_id' => $paymentOrder->order_code,
                            'image_url' => '',
                            'product_url' => '',
                            'category' => 'general'
                        ]
                    ]
                ],
                'order_history' => [
                    [
                        'purchased_at' => now()->subMonth()->toISOString(),
                        'amount' => (string) round($paymentOrder->amount, 2),
                        'payment_method' => 'card',
                        'status' => 'new'
                    ]
                ],
                'meta' => [
                    'order_id' => $paymentOrder->order_code,
                    'customer' => $paymentOrder->customer_data ?? []
                ]
            ],
            'lang' => app()->getLocale(),
            'merchant_code' => $publicKey,
            'merchant_urls' => [
                'success' => $this->getSuccessUrl($paymentOrder),
                'cancel' => $this->getFailureUrl($paymentOrder),
                'failure' => $this->getFailureUrl($paymentOrder)
            ]
        ];

        // Log checkout data being sent
        Log::info('Tabby Checkout Data Prepared', [
            'order_code' => $paymentOrder->order_code,
            'amount' => $checkoutData['payment']['amount'],
            'currency' => $currency,
            'payment_product' => $paymentProduct,
            'merchant_urls' => $checkoutData['merchant_urls'],
            'locale' => app()->getLocale()
        ]);

        // Log URLs being generated
        Log::info('Tabby URLs Generated', [
            'order_code' => $paymentOrder->order_code,
            'success_url' => $this->getSuccessUrl($paymentOrder),
            'failure_url' => $this->getFailureUrl($paymentOrder),
            'callback_url' => $this->getCallbackUrl()
        ]);

        // Log view data being passed
        $viewData = [
            'paymentOrder' => $paymentOrder,
            'paymentMethod' => $this->paymentMethod,
            'publicKey' => $publicKey,
            'secretKey' => $secretKey,
            'apiUrl' => $apiUrl,
            'checkoutData' => $checkoutData,
            'callbackUrl' => $this->getCallbackUrl(),
            'successUrl' => $this->getSuccessUrl($paymentOrder),
            'failureUrl' => $this->getFailureUrl($paymentOrder),
            'sandboxMode' => $sandboxMode,
            'currency' => $currency,
            'paymentProduct' => $paymentProduct,
        ];

        Log::info('Tabby View Data Prepared', [
            'order_code' => $paymentOrder->order_code,
            'view_name' => 'payment-gateway::plugins.tabby-payment',
            'view_data_keys' => array_keys($viewData),
            'has_payment_order' => isset($viewData['paymentOrder']),
            'has_payment_method' => isset($viewData['paymentMethod']),
            'has_public_key' => !empty($viewData['publicKey']),
            'has_secret_key' => !empty($viewData['secretKey'])
        ]);

        try {
            Log::info('Tabby View Rendering Started', [
                'order_code' => $paymentOrder->order_code,
                'view_name' => 'payment-gateway::plugins.tabby-payment'
            ]);
            
            $view = view('payment-gateway::plugins.tabby-payment', $viewData);
            
            Log::info('Tabby View Rendered Successfully', [
                'order_code' => $paymentOrder->order_code,
                'view_type' => get_class($view)
            ]);
            
            return $view;
        } catch (\Exception $e) {
            Log::error('Tabby View Rendering Failed', [
                'order_code' => $paymentOrder->order_code,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'view_name' => 'payment-gateway::plugins.tabby-payment'
            ]);
            
            // Re-throw the exception so the payment gateway can handle it
            throw $e;
        }
    }

    public function handleCallback(array $callbackData): \Trinavo\PaymentGateway\Models\CallbackResponse
    {
        // Log incoming callback data
        Log::info('Tabby Callback Received', [
            'raw_callback_data' => $callbackData,
            'callback_keys' => array_keys($callbackData)
        ]);

        $status = $callbackData['status'] ?? 'failed';
        $orderCode = $callbackData['order_code'] ?? null;
        $paymentId = $callbackData['payment_id'] ?? null;
        $tabbyId = $callbackData['tabby_id'] ?? null;
        $message = $callbackData['message'] ?? null;

        // Log parsed callback data
        Log::info('Tabby Callback Data Parsed', [
            'status' => $status,
            'order_code' => $orderCode,
            'payment_id' => $paymentId,
            'tabby_id' => $tabbyId,
            'message' => $message
        ]);

        if (!$orderCode) {
            Log::error('Tabby Callback Missing Order Code', [
                'callback_data' => $callbackData,
                'parsed_order_code' => $orderCode
            ]);
            
            return \Trinavo\PaymentGateway\Models\CallbackResponse::failure(
                orderCode: 'unknown',
                message: 'Order code is required'
            );
        }

        // Tabby payment statuses: AUTHORIZED, CLOSED, EXPIRED, REJECTED, CREATED
        if (in_array($status, ['AUTHORIZED', 'CLOSED'])) {
            Log::info('Tabby Payment Success', [
                'order_code' => $orderCode,
                'status' => $status,
                'tabby_id' => $tabbyId,
                'payment_id' => $paymentId,
                'message' => $message
            ]);
            
            return \Trinavo\PaymentGateway\Models\CallbackResponse::success(
                orderCode: $orderCode,
                transactionId: $tabbyId ?: $paymentId,
                message: $message ?: 'Payment completed successfully via Tabby'
            );
        }

        // Log payment failure
        Log::warning('Tabby Payment Failed', [
            'order_code' => $orderCode,
            'status' => $status,
            'tabby_id' => $tabbyId,
            'payment_id' => $paymentId,
            'message' => $message,
            'failure_reason' => 'Payment status not in success list'
        ]);

        return \Trinavo\PaymentGateway\Models\CallbackResponse::failure(
            orderCode: $orderCode,
            message: $message ?: 'Payment failed or was rejected',
            status: $status,
            additionalData: [
                'tabby_payment_id' => $paymentId,
                'tabby_id' => $tabbyId,
                'tabby_status' => $status
            ]
        );
    }

    /**
     * Get the API base URL based on sandbox mode
     */
    private function getApiUrl(): string
    {
        $sandboxMode = $this->paymentMethod->getSetting('sandbox_mode', true);
        $apiUrl = $sandboxMode ? 'https://api-sandbox.tabby.ai' : 'https://api.tabby.ai';
        
        Log::debug('Tabby API URL Generated', [
            'sandbox_mode' => $sandboxMode,
            'api_url' => $apiUrl
        ]);
        
        return $apiUrl;
    }

    /**
     * Get API headers for Tabby requests
     */
    private function getApiHeaders(): array
    {
        $sandboxMode = $this->paymentMethod->getSetting('sandbox_mode', true);
        $secretKey = $sandboxMode 
            ? $this->paymentMethod->getSetting('secret_key_sandbox')
            : $this->paymentMethod->getSetting('secret_key_production');

        $headers = [
            'Authorization' => 'Bearer ' . $secretKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        // Log API headers (without exposing the actual secret key)
        Log::debug('Tabby API Headers Generated', [
            'sandbox_mode' => $sandboxMode,
            'has_authorization' => !empty($headers['Authorization']),
            'authorization_length' => strlen($headers['Authorization']),
            'content_type' => $headers['Content-Type'],
            'accept' => $headers['Accept']
        ]);

        return $headers;
    }

    /**
     * Log API request details
     */
    private function logApiRequest(string $method, string $endpoint, array $data = [], array $headers = []): void
    {
        Log::info('Tabby API Request', [
            'method' => $method,
            'endpoint' => $endpoint,
            'data' => $data,
            'headers' => array_map(function($value) {
                // Mask sensitive header values
                if (str_contains(strtolower($value), 'bearer')) {
                    return 'Bearer ***' . substr($value, -4);
                }
                return $value;
            }, $headers),
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Log API response details
     */
    private function logApiResponse(string $method, string $endpoint, $response, int $statusCode = null, array $headers = []): void
    {
        Log::info('Tabby API Response', [
            'method' => $method,
            'endpoint' => $endpoint,
            'status_code' => $statusCode,
            'response' => $response,
            'response_headers' => $headers,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Log API error details
     */
    private function logApiError(string $method, string $endpoint, \Exception $exception, array $requestData = []): void
    {
        Log::error('Tabby API Error', [
            'method' => $method,
            'endpoint' => $endpoint,
            'error_message' => $exception->getMessage(),
            'error_code' => $exception->getCode(),
            'error_file' => $exception->getFile(),
            'error_line' => $exception->getLine(),
            'request_data' => $requestData,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Fallback method to get settings directly from database
     */
    private function getSettingFallback(string $key, $default = null)
    {
        try {
            $setting = \Trinavo\PaymentGateway\Models\PaymentMethodSetting::where('payment_method_id', $this->paymentMethod->id)
                ->where('key', $key)
                ->first();

            if (!$setting) {
                return $default;
            }

            $value = $setting->value;

            if ($setting->encrypted) {
                $value = \Illuminate\Support\Facades\Crypt::decryptString($value);
            }

            return $value;
        } catch (\Exception $e) {
            Log::error('Tabby Setting Fallback Failed', [
                'key' => $key,
                'payment_method_id' => $this->paymentMethod->id ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return $default;
        }
    }
}
