<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Criteria;

/**
 * Binary operation that conjunctively combines two criteria:
 *   "prop1 = 'foo' AND prop2 = 'bar'"
 *
 * @see PropertyValueCriteriaParser
 * @api
 */
final readonly class AndCriteria implements PersistentProjectionFilterCriteria
{
    private function __construct(
        public PersistentProjectionFilterCriteria $criteria1,
        public PersistentProjectionFilterCriteria $criteria2,
    ) {
    }

    public static function create(PersistentProjectionFilterCriteria $criteria1, PersistentProjectionFilterCriteria $criteria2): self
    {
        return new self($criteria1, $criteria2);
    }
}
