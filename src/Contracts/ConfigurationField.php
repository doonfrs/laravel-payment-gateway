<?php

namespace Trinavo\PaymentGateway\Contracts;

abstract class ConfigurationField
{
    protected string $name;

    protected string $label;

    protected bool $required;

    protected mixed $default;

    protected ?string $description;

    protected bool $encrypted;

    public function __construct(
        string $name,
        string $label,
        bool $required = false,
        mixed $default = null,
        ?string $description = null,
        bool $encrypted = false
    ) {
        $this->name = $name;
        $this->label = $label;
        $this->required = $required;
        $this->default = $default;
        $this->description = $description;
        $this->encrypted = $encrypted;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function isEncrypted(): bool
    {
        return $this->encrypted;
    }

    abstract public function getType(): string;

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'type' => $this->getType(),
            'required' => $this->required,
            'default' => $this->default,
            'description' => $this->description,
            'encrypted' => $this->encrypted,
        ];
    }
}
