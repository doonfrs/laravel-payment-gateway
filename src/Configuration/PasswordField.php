<?php

namespace Trinavo\PaymentGateway\Configuration;

use Trinavo\PaymentGateway\Contracts\ConfigurationField;

class PasswordField extends ConfigurationField
{
    protected ?string $placeholder;

    public function __construct(
        string $name,
        string $label,
        bool $required = false,
        ?string $default = null,
        ?string $description = null,
        ?string $placeholder = null
    ) {
        // Password fields are always encrypted
        parent::__construct($name, $label, $required, $default, $description, true);
        $this->placeholder = $placeholder;
    }

    public function getType(): string
    {
        return 'password';
    }

    public function getPlaceholder(): ?string
    {
        return $this->placeholder;
    }

    public function toArray(): array
    {
        $array = parent::toArray();

        if ($this->placeholder !== null) {
            $array['placeholder'] = $this->placeholder;
        }

        return $array;
    }
}
