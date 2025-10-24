<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Criteria;

/**
 * Criteria that matches if a property starts with the specified string (case-sensitive)
 *     "prop1 ^= 'foo'"
 *
 * Criteria that matches if a property starts with the specified string (case-insensitive)
 *      "prop1 ^=~ 'foo'"
 *
 */
final readonly class ValueStartsWith implements PersistentProjectionFilterCriteria
{
    private function __construct(
        public string $propertyName,
        public string $value,
        public bool $caseSensitive,
    ) {
    }

    public static function create(string $propertyName, string $value, bool $caseSensitive): self
    {
        return new self($propertyName, $value, $caseSensitive);
    }
}
