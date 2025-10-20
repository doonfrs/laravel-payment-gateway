<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Trinavo\PaymentGateway\Facades\PaymentGateway;

class ExampleController extends Controller
{
    /**
     * Example: Create a payment order and redirect to checkout
     */
    public function createPayment(Request $request)
    {
        // Create a payment order
        $paymentOrder = PaymentGateway::createPaymentOrder(
            amount: 99.99,
            currency: 'USD',
            customerName: 'John Doe',
            customerEmail: 'john@example.com',
            customerPhone: '+1234567890',
            description: 'Premium subscription - 1 month',
            // PHP code to execute when payment succeeds
            successCallback: '
                // Update user subscription
                $user = \App\Models\User::where("email", $order->customer_email)->first();
                if ($user) {
                    $user->subscription_expires_at = now()->addMonth();
                    $user->save();
                }

                // Send confirmation email
                \Mail::to($order->customer_email)->send(new \App\Mail\PaymentConfirmation($order));
            ',
            // PHP code to execute when payment fails
            failureCallback: '
                // Log the failure
                \Log::warning("Payment failed for order: " . $order->order_code);

                // Notify admin
                \Mail::to("admin@example.com")->send(new \App\Mail\PaymentFailed($order));
            ',
            // URLs to redirect after payment
            successUrl: route('subscription.success'),
            failureUrl: route('subscription.failed'),
        );

        // Get the payment URL and redirect user
        $paymentUrl = PaymentGateway::getPaymentUrl(paymentOrder: $paymentOrder);

        return redirect($paymentUrl);
    }

    /**
     * Example: Check payment status
     */
    public function checkPaymentStatus($orderCode)
    {
        $paymentOrder = PaymentGateway::getPaymentOrderByCode($orderCode);

        if (! $paymentOrder) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        return response()->json([
            'order_code' => $paymentOrder->order_code,
            'status' => $paymentOrder->status,
            'amount' => $paymentOrder->formatted_amount,
            'paid_at' => $paymentOrder->paid_at,
        ]);
    }

    /**
     * Example: Register a new payment method programmatically
     */
    public function registerPaymentMethod()
    {
        $paymentMethod = PaymentGateway::registerPaymentMethod([
            'name' => 'dummy',
            'plugin_class' => \Trinavo\PaymentGateway\Plugins\Dummy\DummyPaymentPlugin::class,
            'display_name' => 'Dummy Payment Method',
            'description' => 'Dummy payment method',
            'enabled' => true,
            'sort_order' => 1,
        ]);

        // Configure the payment method
        $paymentMethod->setSetting('secret_key', env('STRIPE_SECRET_KEY'), true); // encrypted
        $paymentMethod->setSetting('publishable_key', env('STRIPE_PUBLISHABLE_KEY'));
        $paymentMethod->setSetting('webhook_secret', env('STRIPE_WEBHOOK_SECRET'), true);

        return response()->json(['message' => 'Payment method registered successfully']);
    }
}
