<?php

namespace Trinavo\PaymentGateway\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Trinavo\PaymentGateway\Models\PaymentMethod;
use Trinavo\PaymentGateway\Services\PaymentGatewayService;

class PaymentController extends Controller
{
    protected PaymentGatewayService $paymentGateway;

    public function __construct(PaymentGatewayService $paymentGateway)
    {
        $this->paymentGateway = $paymentGateway;
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

        $paymentMethods = $this->paymentGateway->getAvailablePaymentMethods();

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

        if (! $paymentOrder->isPending()) {
            return redirect()->route('payment-gateway.status', ['order' => $orderCode]);
        }

        $paymentMethod = PaymentMethod::findOrFail($request->payment_method_id);

        if (! $paymentMethod->enabled) {
            return back()->withErrors(['payment_method_id' => 'Selected payment method is not available']);
        }

        try {
            $response = $this->paymentGateway->processPayment($paymentOrder, $paymentMethod);

            return $response;
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Payment processing failed: '.$e->getMessage()]);
        }
    }

    /**
     * Handle payment callback from plugins
     */
    public function callback(Request $request, string $plugin)
    {
        try {
            $pluginClass = $this->getPluginClass($plugin);
            $callbackData = $request->all();

            $result = $this->paymentGateway->handlePluginCallback($pluginClass, $callbackData);

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
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Callback processing failed: '.$e->getMessage(),
            ], 500);
        }
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
     * Get plugin class name from plugin identifier
     */
    protected function getPluginClass(string $plugin): string
    {
        $pluginMap = [
            'dummy' => \Trinavo\PaymentGateway\Plugins\Dummy\DummyPaymentPlugin::class,
        ];

        if (! isset($pluginMap[$plugin])) {
            throw new \Exception("Unknown plugin: {$plugin}");
        }

        return $pluginMap[$plugin];
    }
}
