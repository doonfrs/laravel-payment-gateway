<?php

namespace Trinavo\PaymentGateway\Configuration;

use Trinavo\PaymentGateway\Contracts\ConfigurationField;

class NumberField extends ConfigurationField
{
    protected ?int $min;

    protected ?int $max;

    protected ?int $step;

    protected ?string $placeholder;

    public function __construct(
        string $name,
        string $label,
        bool $required = false,
        ?int $default = null,
        ?string $description = null,
        ?int $min = null,
        ?int $max = null,
        ?int $step = null,
        ?string $placeholder = null
    ) {
        parent::__construct($name, $label, $required, $default, $description, false);
        $this->min = $min;
        $this->max = $max;
        $this->step = $step;
        $this->placeholder = $placeholder;
    }

    public function getType(): string
    {
        return 'number';
    }

    public function getMin(): ?int
    {
        return $this->min;
    }

    public function getMax(): ?int
    {
        return $this->max;
    }

    public function getStep(): ?int
    {
        return $this->step;
    }

    public function getPlaceholder(): ?string
    {
        return $this->placeholder;
    }

    public function toArray(): array
    {
        $array = parent::toArray();

        if ($this->min !== null) {
            $array['min'] = $this->min;
        }

        if ($this->max !== null) {
            $array['max'] = $this->max;
        }

        if ($this->step !== null) {
            $array['step'] = $this->step;
        }

        if ($this->placeholder !== null) {
            $array['placeholder'] = $this->placeholder;
        }

        return $array;
    }
}
