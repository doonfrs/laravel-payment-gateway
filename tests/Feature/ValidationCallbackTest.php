<?php

namespace Trinavo\PaymentGateway\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use Trinavo\PaymentGateway\Models\PaymentMethod;
use Trinavo\PaymentGateway\Models\PaymentOrder;
use Trinavo\PaymentGateway\Providers\PaymentGatewayServiceProvider;
use Trinavo\PaymentGateway\Services\PaymentGatewayService;

/**
 * Static helper class used as a validation_callback target. Eval'd code does
 * `\Trinavo\...\ValidationCallbackTestHelpers::passing($paymentOrder)` and we
 * read the recorded calls to assert the callback fired with the right context.
 */
class ValidationCallbackTestHelpers
{
    public static int $calls = 0;

    public static array $lastContext = [];

    public static int $lastPaymentOrderId = 0;

    public static $nextResult = true;

    public static function reset(): void
    {
        self::$calls = 0;
        self::$lastContext = [];
        self::$lastPaymentOrderId = 0;
        self::$nextResult = true;
    }

    public static function callback($paymentOrder)
    {
        // The eval'd scope contains $context (['trigger' => '...']) — capture it
        // by looking it up in the caller's local symbols via debug_backtrace.
        // Simpler: read from $GLOBALS that the test sets before invoking.
        self::$calls++;
        self::$lastPaymentOrderId = $paymentOrder->id;
        self::$lastContext = $GLOBALS['__validation_test_context'] ?? [];

        return self::$nextResult;
    }
}

class ValidationCallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app)
    {
        return [PaymentGatewayServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->withoutVite();
        ValidationCallbackTestHelpers::reset();
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('payment-gateway.routes.prefix', 'payment-gateway');
        $app['config']->set('payment-gateway.routes.middleware', ['web']);
    }

    private function callbackString(): string
    {
        return '\\'.ValidationCallbackTestHelpers::class.'::callback($paymentOrder)';
    }

    public function test_run_validation_returns_true_when_no_callback_is_set(): void
    {
        $paymentOrder = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => PaymentOrder::STATUS_PENDING,
        ]);

        $result = app(PaymentGatewayService::class)->runValidation($paymentOrder, 'method_selected');

        $this->assertTrue($result);
    }

    public function test_run_validation_returns_true_when_callback_returns_true(): void
    {
        ValidationCallbackTestHelpers::$nextResult = true;

        $paymentOrder = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => PaymentOrder::STATUS_PENDING,
            'validation_callback' => $this->callbackString(),
        ]);

        $GLOBALS['__validation_test_context'] = [];
        $result = app(PaymentGatewayService::class)->runValidation($paymentOrder, 'method_selected');

        $this->assertTrue($result);
        $this->assertEquals(1, ValidationCallbackTestHelpers::$calls);
        $this->assertEquals($paymentOrder->id, ValidationCallbackTestHelpers::$lastPaymentOrderId);
    }

    public function test_run_validation_returns_string_message_when_callback_returns_string(): void
    {
        ValidationCallbackTestHelpers::$nextResult = 'Cart changed since checkout. Please review and try again.';

        $paymentOrder = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => PaymentOrder::STATUS_PENDING,
            'validation_callback' => $this->callbackString(),
        ]);

        $result = app(PaymentGatewayService::class)->runValidation($paymentOrder, 'before_submit');

        $this->assertSame('Cart changed since checkout. Please review and try again.', $result);
    }

    public function test_run_validation_returns_fallback_when_callback_returns_false(): void
    {
        ValidationCallbackTestHelpers::$nextResult = false;

        $paymentOrder = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => PaymentOrder::STATUS_PENDING,
            'validation_callback' => $this->callbackString(),
        ]);

        $result = app(PaymentGatewayService::class)->runValidation($paymentOrder, 'method_selected');

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
        $this->assertNotSame('false', $result);
    }

    public function test_process_route_blocks_when_validation_fails(): void
    {
        ValidationCallbackTestHelpers::$nextResult = 'Cart changed. Please review.';

        $paymentMethod = PaymentMethod::create([
            'name' => json_encode(['en' => 'Dummy Payment']),
            'plugin_class' => \Trinavo\PaymentGateway\Plugins\Dummy\DummyPaymentPlugin::class,
            'enabled' => true,
            'sort_order' => 1,
        ]);
        // Add a second so checkout doesn't auto-process; we test the explicit POST below.
        PaymentMethod::create([
            'name' => json_encode(['en' => 'Offline Payment']),
            'plugin_class' => \Trinavo\PaymentGateway\Plugins\Dummy\DummyPaymentPlugin::class,
            'enabled' => true,
            'sort_order' => 2,
        ]);

        $paymentOrder = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => PaymentOrder::STATUS_PENDING,
            'validation_callback' => $this->callbackString(),
            'failure_url' => '/cart',
        ]);

        $response = $this->post("/payment-gateway/checkout/{$paymentOrder->order_code}/process", [
            'payment_method_id' => $paymentMethod->id,
        ]);

        $response->assertRedirect('/cart');
        $response->assertSessionHas('payment_validation_message', 'Cart changed. Please review.');

        $paymentOrder->refresh();
        $this->assertEquals(PaymentOrder::STATUS_CANCELLED, $paymentOrder->status);
        $this->assertEquals('validation_callback', $paymentOrder->payment_data['cancelled_by']);
        $this->assertEquals('method_selected', $paymentOrder->payment_data['validation_trigger']);
    }

    public function test_process_route_proceeds_when_validation_passes(): void
    {
        ValidationCallbackTestHelpers::$nextResult = true;

        $paymentMethod = PaymentMethod::create([
            'name' => json_encode(['en' => 'Dummy Payment']),
            'plugin_class' => \Trinavo\PaymentGateway\Plugins\Dummy\DummyPaymentPlugin::class,
            'enabled' => true,
            'sort_order' => 1,
        ]);
        PaymentMethod::create([
            'name' => json_encode(['en' => 'Offline Payment']),
            'plugin_class' => \Trinavo\PaymentGateway\Plugins\Dummy\DummyPaymentPlugin::class,
            'enabled' => true,
            'sort_order' => 2,
        ]);

        $paymentOrder = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => PaymentOrder::STATUS_PENDING,
            'validation_callback' => $this->callbackString(),
            'failure_url' => '/cart',
        ]);

        $response = $this->post("/payment-gateway/checkout/{$paymentOrder->order_code}/process", [
            'payment_method_id' => $paymentMethod->id,
        ]);

        $response->assertStatus(200);
        $paymentOrder->refresh();
        $this->assertEquals(PaymentOrder::STATUS_PENDING, $paymentOrder->status);
        $this->assertEquals(1, ValidationCallbackTestHelpers::$calls);
    }

    public function test_checkout_auto_select_blocks_when_validation_fails(): void
    {
        ValidationCallbackTestHelpers::$nextResult = 'Cart changed before auto-select.';

        // Single payment method triggers auto-process inside checkout()
        $paymentMethod = PaymentMethod::create([
            'name' => json_encode(['en' => 'Dummy Payment']),
            'plugin_class' => \Trinavo\PaymentGateway\Plugins\Dummy\DummyPaymentPlugin::class,
            'enabled' => true,
            'sort_order' => 1,
        ]);

        $paymentOrder = PaymentOrder::create([
            'amount' => 100,
            'currency' => 'USD',
            'status' => PaymentOrder::STATUS_PENDING,
            'validation_callback' => $this->callbackString(),
            'failure_url' => '/cart',
        ]);

        $response = $this->get("/payment-gateway/checkout/{$paymentOrder->order_code}");

        $response->assertRedirect('/cart');
        $response->assertSessionHas('payment_validation_message', 'Cart changed before auto-select.');

        $paymentOrder->refresh();
        $this->assertEquals(PaymentOrder::STATUS_CANCELLED, $paymentOrder->status);
    }
}
