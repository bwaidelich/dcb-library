<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Criteria;

/**
 * Criteria that matches if a property is greater than the specified value
 *     "stringProp > 'foo' OR intProp > 123 OR floatProp > 123.45"
 *
 */
final readonly class ValueGreaterThan implements PersistentProjectionFilterCriteria
{
    private function __construct(
        public string $propertyName,
        public string|int|float $value,
    ) {
    }

    public static function create(string $propertyName, string|int|float $value): self
    {
        return new self($propertyName, $value);
    }
}
