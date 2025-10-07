<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Projection\PersistentProjection\Filter;

use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Criteria\PersistentProjectionFilterCriteria;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Ordering\Ordering;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Pagination\Pagination;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\SearchTerm\SearchTerm;

final readonly class PersistentProjectionFilter
{
    /**
     * @internal the properties themselves are readonly; only the write-methods are API.
     */
    private function __construct(
        public ?PersistentProjectionFilterCriteria $criteria,
        public ?SearchTerm $searchTerm,
        public ?Ordering $ordering,
        public ?Pagination $pagination,
    ) {
    }

    /**
     * Creates an instance with the specified filter options
     *
     * Note: The signature of this method might be extended in the future, so it should always be used with named arguments
     * @see https://www.php.net/manual/en/functions.arguments.php#functions.named-arguments
     *
     * @param Ordering|array<mixed>|null $ordering
     * @param Pagination|array<mixed>|null $pagination
     */
    public static function create(
        PersistentProjectionFilterCriteria|null $criteria = null,
        SearchTerm|string|null $searchTerm = null,
        Ordering|array|null $ordering = null,
        Pagination|array|null $pagination = null,
    ): self {
        if (is_string($searchTerm)) {
            $searchTerm = $searchTerm === '' ? null : SearchTerm::fulltext($searchTerm);
        }
        if (is_array($ordering)) {
            $ordering = Ordering::fromArray($ordering);
        }
        if (is_array($pagination)) {
            $pagination = Pagination::fromArray($pagination);
        }
        return new self($criteria, $searchTerm, $ordering, $pagination);
    }
}
