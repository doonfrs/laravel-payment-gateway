<?php

namespace Trinavo\PaymentGateway\Configuration;

use Trinavo\PaymentGateway\Contracts\ConfigurationField;

class CheckboxField extends ConfigurationField
{
    public function __construct(
        string $name,
        string $label,
        bool $default = false,
        ?string $description = null
    ) {
        parent::__construct($name, $label, false, $default, $description, false);
    }

    public function getType(): string
    {
        return 'checkbox';
    }
}
