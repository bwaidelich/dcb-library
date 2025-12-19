<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Criteria;

/**
 * Criteria that matches if a property is TRUE
 */
final readonly class ValueIsTrue implements PersistentProjectionFilterCriteria
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
