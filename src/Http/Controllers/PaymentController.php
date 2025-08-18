<?php

namespace Trinavo\PaymentGateway\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Trinavo\PaymentGateway\Models\PaymentMethod;
use Trinavo\PaymentGateway\Services\PaymentGatewayService;

class PaymentController extends Controller
{
    protected PaymentGatewayService $paymentGateway;

    protected \Trinavo\PaymentGateway\Services\PluginRegistryService $pluginRegistry;

    public function __construct(PaymentGatewayService $paymentGateway, \Trinavo\PaymentGateway\Services\PluginRegistryService $pluginRegistry)
    {
        $this->paymentGateway = $paymentGateway;
        $this->pluginRegistry = $pluginRegistry;
    }

    /**
     * Show checkout page with payment methods
     */
    public function checkout(Request $request, string $orderCode)
    {
        $paymentOrder = $this->paymentGateway->getPaymentOrderByCode($orderCode);

        if (! $paymentOrder) {
            abort(404, 'Payment order not found');
        }

        if (! $paymentOrder->isPending()) {
            return redirect()->route('payment-gateway.status', ['order' => $orderCode]);
        }

        $paymentMethods = $this->paymentGateway->getAvailablePaymentMethodsForOrder($paymentOrder);

        return view('payment-gateway::checkout', [
            'paymentOrder' => $paymentOrder,
            'paymentMethods' => $paymentMethods,
        ]);
    }

    /**
     * Process payment with selected method
     */
    public function processPayment(Request $request, string $orderCode)
    {
        $request->validate([
            'payment_method_id' => 'required|exists:payment_methods,id',
        ]);

        $paymentOrder = $this->paymentGateway->getPaymentOrderByCode($orderCode);

        if (! $paymentOrder) {
            abort(404, 'Payment order not found');
        }

        $paymentMethod = PaymentMethod::findOrFail($request->payment_method_id);

        if (! $paymentMethod->enabled) {
            return back()->withErrors(['payment_method_id' => 'Selected payment method is not available']);
        }

        // Check if the payment method is ignored for this order
        if ($paymentOrder->isPluginIgnored($paymentMethod->name) ||
            $paymentOrder->isPluginIgnored($paymentMethod->plugin_class)) {
            return back()->withErrors(['payment_method_id' => 'Selected payment method is not available for this order']);
        }

        $response = $this->paymentGateway->processPayment($paymentOrder, $paymentMethod);

        return $response;

    }

    /**
     * Handle payment callback from plugins
     */
    public function callback(Request $request, string $plugin)
    {
        $pluginClass = $this->getPluginClass($plugin);
        
        // Get data from both request and session (for redirect-based flows)
        $callbackData = array_merge($request->all(), session()->all());

        Log::info('Payment Gateway Callback Received', [
            'plugin' => $plugin,
            'plugin_class' => $pluginClass,
            'request_data' => $request->all(),
            'session_data' => session()->all(),
            'merged_data' => $callbackData
        ]);

        $result = $this->paymentGateway->handlePluginCallback($pluginClass, $callbackData);

        Log::info('Payment Gateway Callback Result', [
            'plugin' => $plugin,
            'result' => $result
        ]);

        if ($result['success']) {
            $paymentOrder = $this->paymentGateway->getPaymentOrderByCode($result['order_code']);
            if ($paymentOrder) {
                $this->paymentGateway->handlePaymentSuccess($paymentOrder, $result);

                return redirect()->route('payment-gateway.success', ['order' => $result['order_code']]);
            }
        } else {
            $paymentOrder = $this->paymentGateway->getPaymentOrderByCode($result['order_code']);
            if ($paymentOrder) {
                $this->paymentGateway->handlePaymentFailure($paymentOrder, $result);

                return redirect()->route('payment-gateway.failure', ['order' => $result['order_code']]);
            }
        }

        return response()->json($result);

    }

    /**
     * Show payment success page
     */
    public function success(Request $request, string $orderCode)
    {
        $paymentOrder = $this->paymentGateway->getPaymentOrderByCode($orderCode);

        if (! $paymentOrder) {
            abort(404, 'Payment order not found');
        }

        return view('payment-gateway::success', [
            'paymentOrder' => $paymentOrder,
        ]);
    }

    /**
     * Show payment failure page
     */
    public function failure(Request $request, string $orderCode)
    {
        $paymentOrder = $this->paymentGateway->getPaymentOrderByCode($orderCode);

        if (! $paymentOrder) {
            abort(404, 'Payment order not found');
        }

        return view('payment-gateway::failure', [
            'paymentOrder' => $paymentOrder,
        ]);
    }

    /**
     * Show payment status page
     */
    public function status(Request $request, string $orderCode)
    {
        $paymentOrder = $this->paymentGateway->getPaymentOrderByCode($orderCode);

        if (! $paymentOrder) {
            abort(404, 'Payment order not found');
        }

        return view('payment-gateway::status', [
            'paymentOrder' => $paymentOrder,
        ]);
    }

    /**
     * Handle dummy payment actions (for testing)
     */
    public function dummyAction(Request $request, string $orderCode, string $action)
    {
        $paymentOrder = $this->paymentGateway->getPaymentOrderByCode($orderCode);

        if (! $paymentOrder) {
            abort(404, 'Payment order not found');
        }

        switch ($action) {
            case 'success':
                $this->paymentGateway->handlePaymentSuccess($paymentOrder, [
                    'transaction_id' => 'dummy_success_'.uniqid(),
                    'method' => 'dummy_direct',
                ]);

                return redirect()->route('payment-gateway.success', ['order' => $orderCode]);

            case 'failure':
                $this->paymentGateway->handlePaymentFailure($paymentOrder, [
                    'error' => 'Dummy payment failed',
                    'method' => 'dummy_direct',
                ]);

                return redirect()->route('payment-gateway.failure', ['order' => $orderCode]);

            case 'callback':
                // Simulate external callback
                return redirect()->route('payment-gateway.callback', ['plugin' => 'dummy'])
                    ->with([
                        'status' => 'success',
                        'order_code' => $orderCode,
                    ]);

            default:
                abort(404, 'Invalid action');
        }
    }

    /**
     * Handle offline payment confirmation
     */
    public function offlineConfirm(Request $request, string $orderCode)
    {
        $paymentOrder = $this->paymentGateway->getPaymentOrderByCode($orderCode);

        if (! $paymentOrder) {
            abort(404, 'Payment order not found');
        }

        // Handle offline payment success
        $this->paymentGateway->handlePaymentSuccess($paymentOrder, [
            'transaction_id' => 'offline_'.uniqid(),
            'method' => 'offline_cod',
        ]);

        return redirect()->route('payment-gateway.success', ['order' => $orderCode]);
    }

    /**
     * Get plugin class name from plugin identifier
     */
    protected function getPluginClass(string $plugin): string
    {
        $pluginClass = $this->pluginRegistry->getPluginClass($plugin);

        if (! $pluginClass) {
            throw new \Exception("Unknown plugin: {$plugin}");
        }

        return $pluginClass;
    }
}
