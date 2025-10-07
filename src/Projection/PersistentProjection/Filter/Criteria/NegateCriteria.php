<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Criteria;

/**
 * Unary operation that negates a criteria:
 *   "NOT (prop1 = 'foo' OR prop1 = 'bar')"
 * Or:
 *   "prop1 != 'foo'"
 *
 * @see PropertyValueCriteriaParser
 * @api
 */
final readonly class NegateCriteria implements PersistentProjectionFilterCriteria
{
    private function __construct(
        public PersistentProjectionFilterCriteria $criteria,
    ) {
    }

    public static function create(PersistentProjectionFilterCriteria $criteria): self
    {
        return new self($criteria);
    }
}
