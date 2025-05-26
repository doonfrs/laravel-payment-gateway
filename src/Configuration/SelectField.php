<?php

namespace Trinavo\PaymentGateway\Configuration;

use Trinavo\PaymentGateway\Contracts\ConfigurationField;

class SelectField extends ConfigurationField
{
    protected array $options;

    protected bool $multiple;

    public function __construct(
        string $name,
        string $label,
        array $options,
        bool $required = false,
        mixed $default = null,
        ?string $description = null,
        bool $multiple = false
    ) {
        parent::__construct($name, $label, $required, $default, $description, false);
        $this->options = $this->normalizeOptions($options);
        $this->multiple = $multiple;
    }

    public function getType(): string
    {
        return 'select';
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    /**
     * Normalize options to always have key => label format
     * Supports both ['option1', 'option2'] and ['key1' => 'Label 1', 'key2' => 'Label 2']
     */
    protected function normalizeOptions(array $options): array
    {
        $normalized = [];

        foreach ($options as $key => $value) {
            if (is_numeric($key)) {
                // Index-based array: ['option1', 'option2']
                $normalized[$value] = $value;
            } else {
                // Key-value array: ['key1' => 'Label 1']
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    public function toArray(): array
    {
        $array = parent::toArray();
        $array['options'] = $this->options;
        $array['multiple'] = $this->multiple;

        return $array;
    }
}
