<?php

namespace App\Casts;

/**
 * Objeto de valor liviano con la misma interfaz pública que los BackedEnum del dominio
 * (`$item->value` y `$item->label()`), utilizado para Ámbitos o Turnos creados dinámicamente en BD.
 */
final readonly class DynamicValue implements \JsonSerializable, \Stringable
{
    public function __construct(
        public string $value,
        private string $label,
    ) {
    }

    public function label(): string
    {
        return $this->label;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
