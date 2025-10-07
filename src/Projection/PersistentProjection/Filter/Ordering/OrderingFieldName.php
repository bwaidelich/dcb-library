<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Ordering;

use JsonSerializable;

/**
 * The name of an {@see OrderingField} this is usually either a property name or one of the timestamp fields
 */
final readonly class OrderingFieldName implements JsonSerializable
{
    public string $value;

    private function __construct(string $value)
    {
        $this->value = trim($value);
        if ($this->value === '') {
            throw new \InvalidArgumentException('Ordering field value must not be empty', 1680269479);
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
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
