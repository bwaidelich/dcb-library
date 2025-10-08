<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\SearchTerm;

use InvalidArgumentException;

final readonly class SearchTerm
{

    private function __construct(public string $term)
    {
        if (empty($term)) {
            throw new InvalidArgumentException('SearchTerm cannot be empty', 1759920399);
        }
    }

    /**
     * Create a new Fulltext search term (i.e. search across all properties)
     */
    public static function fulltext(string $term): self
    {
        return new self($term);
    }
}
