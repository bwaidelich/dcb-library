<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\SearchTerm;

use Webmozart\Assert\Assert;

final readonly class SearchTerm
{

    private function __construct(public string $term)
    {
        Assert::notEmpty($term, 'SearchTerm cannot be empty');
    }

    /**
     * Create a new Fulltext search term (i.e. search across all properties)
     */
    public static function fulltext(string $term): self
    {
        return new self($term);
    }
}
