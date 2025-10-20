<?php

namespace Trinavo\PaymentGateway\Facades;

use Illuminate\Support\Facades\Facade;
use Trinavo\PaymentGateway\Models\PaymentMethod;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Services\PaymentGatewayService;

/**
 * @method static PaymentGatewayService setDefaultLocale(string $locale)
 * @method static PaymentGatewayService setAvailableLocales(array $locales)
 * @method static PaymentOrder createPaymentOrder(float $amount, ?string $currency = null, ?string $customerName = null, ?string $customerEmail = null, ?string $customerPhone = null, ?array $customerData = null, ?string $description = null, ?string $successCallback = null, ?string $failureCallback = null, ?string $successUrl = null, ?string $failureUrl = null, ?array $ignoredPlugins = null)
 * @method static string getPaymentUrl(PaymentOrder $paymentOrder)
 * @method static \Illuminate\Database\Eloquent\Collection getAvailablePaymentMethods()
 * @method static \Illuminate\Database\Eloquent\Collection getAvailablePaymentMethodsForOrder(PaymentOrder $paymentOrder)
 * @method static mixed processPayment(PaymentOrder $paymentOrder, PaymentMethod $paymentMethod)
 * @method static void handlePaymentSuccess(PaymentOrder $paymentOrder, array $paymentData = [])
 * @method static void handlePaymentFailure(PaymentOrder $paymentOrder, array $paymentData = [])
 * @method static array handlePluginCallback(string $pluginClass, array $callbackData)
 * @method static ?PaymentOrder getPaymentOrderByCode(string $orderCode)
 * @method static PaymentMethod registerPaymentMethod(array $data)
 *
 * @see \Trinavo\PaymentGateway\Services\PaymentGatewayService
 */
class PaymentGateway extends Facade
{
    protected static function getFacadeAccessor()
    {
        return PaymentGatewayService::class;
    }
}
