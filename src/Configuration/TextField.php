<?php

namespace Trinavo\PaymentGateway\Configuration;

use Trinavo\PaymentGateway\Contracts\ConfigurationField;

class TextField extends ConfigurationField
{
    protected ?string $placeholder;

    protected ?int $maxLength;

    public function __construct(
        string $name,
        string $label,
        bool $required = false,
        ?string $default = null,
        ?string $description = null,
        bool $encrypted = false,
        ?string $placeholder = null,
        ?int $maxLength = null
    ) {
        parent::__construct($name, $label, $required, $default, $description, $encrypted);
        $this->placeholder = $placeholder;
        $this->maxLength = $maxLength;
    }

    public function getType(): string
    {
        return 'text';
    }

    public function getPlaceholder(): ?string
    {
        return $this->placeholder;
    }

    public function getMaxLength(): ?int
    {
        return $this->maxLength;
    }

    public function toArray(): array
    {
        $array = parent::toArray();

        if ($this->placeholder !== null) {
            $array['placeholder'] = $this->placeholder;
        }

        if ($this->maxLength !== null) {
            $array['max_length'] = $this->maxLength;
        }

        return $array;
    }
}
