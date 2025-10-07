<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Criteria;

/**
 * Criteria that matches if a property is equal to the specified value
 *     "stringProp = 'foo' OR intProp = 123 OR floatProp = 123.45 OR boolProp = true"
 *
 * Criteria that matches if a property is equal to "foo" ignoring the case (e.g. "Foo" and "FOO" would match as well)
 *     "stringProp =~ 'foo'"
 *
 * @see PropertyValueCriteriaParser
 * @api
 */
final readonly class ValueEquals implements PersistentProjectionFilterCriteria
{
    private function __construct(
        public string $propertyName,
        public string|bool|int|float $value,
        public bool $caseSensitive,
    ) {
    }

    public static function create(string $propertyName, string|bool|int|float $value, bool $caseSensitive): self
    {
        return new self($propertyName, $value, $caseSensitive);
    }
}
