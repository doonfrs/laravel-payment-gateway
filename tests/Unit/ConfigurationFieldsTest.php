<?php

namespace Trinavo\PaymentGateway\Tests\Unit;

use Orchestra\Testbench\TestCase;
use Trinavo\PaymentGateway\Configuration\CheckboxField;
use Trinavo\PaymentGateway\Configuration\NumberField;
use Trinavo\PaymentGateway\Configuration\PasswordField;
use Trinavo\PaymentGateway\Configuration\SelectField;
use Trinavo\PaymentGateway\Configuration\TextField;
use Trinavo\PaymentGateway\Providers\PaymentGatewayServiceProvider;

class ConfigurationFieldsTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [PaymentGatewayServiceProvider::class];
    }

    public function test_text_field_creation()
    {
        $field = new TextField(
            name: 'api_key',
            label: 'API Key',
            required: true,
            description: 'Your API key',
            placeholder: 'Enter key...',
            maxLength: 255
        );

        $this->assertEquals('api_key', $field->getName());
        $this->assertEquals('API Key', $field->getLabel());
        $this->assertTrue($field->isRequired());
        $this->assertEquals('text', $field->getType());
        $this->assertEquals('Enter key...', $field->getPlaceholder());
        $this->assertEquals(255, $field->getMaxLength());
    }

    public function test_password_field_is_always_encrypted()
    {
        $field = new PasswordField(
            name: 'secret',
            label: 'Secret Key'
        );

        $this->assertTrue($field->isEncrypted());
        $this->assertEquals('password', $field->getType());
    }

    public function test_select_field_normalizes_options()
    {
        // Test key-value options
        $field1 = new SelectField(
            name: 'env',
            label: 'Environment',
            options: ['sandbox' => 'Sandbox', 'live' => 'Live']
        );

        $this->assertEquals([
            'sandbox' => 'Sandbox',
            'live' => 'Live',
        ], $field1->getOptions());

        // Test index-based options
        $field2 = new SelectField(
            name: 'currency',
            label: 'Currency',
            options: ['USD', 'EUR', 'GBP']
        );

        $this->assertEquals([
            'USD' => 'USD',
            'EUR' => 'EUR',
            'GBP' => 'GBP',
        ], $field2->getOptions());
    }

    public function test_checkbox_field_defaults()
    {
        $field = new CheckboxField(
            name: 'enabled',
            label: 'Enabled',
            default: true
        );

        $this->assertEquals('checkbox', $field->getType());
        $this->assertTrue($field->getDefault());
        $this->assertFalse($field->isRequired()); // Checkboxes are never required
    }

    public function test_number_field_constraints()
    {
        $field = new NumberField(
            name: 'timeout',
            label: 'Timeout',
            min: 1,
            max: 300,
            step: 5,
            default: 30
        );

        $this->assertEquals('number', $field->getType());
        $this->assertEquals(1, $field->getMin());
        $this->assertEquals(300, $field->getMax());
        $this->assertEquals(5, $field->getStep());
        $this->assertEquals(30, $field->getDefault());
    }

    public function test_field_to_array_conversion()
    {
        $field = new TextField(
            name: 'test_field',
            label: 'Test Field',
            required: true,
            default: 'default_value',
            description: 'Test description'
        );

        $array = $field->toArray();

        $this->assertEquals([
            'name' => 'test_field',
            'label' => 'Test Field',
            'type' => 'text',
            'required' => true,
            'default' => 'default_value',
            'description' => 'Test description',
            'encrypted' => false,
        ], $array);
    }

    public function test_select_field_multiple_option()
    {
        $field = new SelectField(
            name: 'cards',
            label: 'Supported Cards',
            options: ['visa' => 'Visa', 'mastercard' => 'Mastercard'],
            multiple: true
        );

        $this->assertTrue($field->isMultiple());

        $array = $field->toArray();
        $this->assertTrue($array['multiple']);
    }
}
