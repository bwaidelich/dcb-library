<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Criteria;

/**
 * Criteria that matches if a property is NULL or not existent
 */
final readonly class ValueIsNull implements PersistentProjectionFilterCriteria
{
    private function __construct(
        public string $propertyName,
    ) {
    }

    public static function create(string $propertyName): self
    {
        return new self($propertyName);
    }
}
