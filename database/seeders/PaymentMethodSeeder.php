<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Trinavo\PaymentGateway\Models\PaymentMethod;

class PaymentMethodSeeder extends Seeder
{
    public function run()
    {
        PaymentMethod::updateOrCreate(
            ['plugin_class' => \Trinavo\PaymentGateway\Plugins\DummyPaymentPlugin::class],
            [
                'name' => 'dummy_payment',
                'display_name' => 'Dummy Payment Gateway',
                'description' => 'A dummy payment gateway for testing purposes',
                'enabled' => true,
                'sort_order' => 1,
            ]
        );
    }
}
