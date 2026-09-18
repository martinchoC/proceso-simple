<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Validación mínima y explícita. No reemplaza las restricciones de BD:
 * las reglas de integridad reales viven en el esquema.
 */
final class Validator
{
    /** @var array<string,string> */
    private array $errors = [];

    /** @param array<string,mixed> $data */
    public function __construct(private readonly array $data)
    {
    }

    public function required(string $field, string $label): self
    {
        $value = $this->data[$field] ?? null;
        if (!is_scalar($value) || trim((string) $value) === '') {
            $this->errors[$field] = "$label es obligatorio.";
        }
        return $this;
    }

    public function maxLen(string $field, int $max, string $label): self
    {
        $value = (string) ($this->data[$field] ?? '');
        if (mb_strlen($value) > $max) {
            $this->errors[$field] = "$label supera los $max caracteres.";
        }
        return $this;
    }

    public function positiveInt(string $field, string $label): self
    {
        $value = filter_var($this->data[$field] ?? null, FILTER_VALIDATE_INT);
        if ($value === false || $value <= 0) {
            $this->errors[$field] = "$label es inválido.";
        }
        return $this;
    }

    public function numericBetween(string $field, float $min, float $max, string $label): self
    {
        $value = filter_var($this->data[$field] ?? null, FILTER_VALIDATE_FLOAT);
        if ($value === false || $value < $min || $value > $max) {
            $this->errors[$field] = "$label debe estar entre $min y $max.";
        }
        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        return $this->errors ? reset($this->errors) : null;
    }
}
