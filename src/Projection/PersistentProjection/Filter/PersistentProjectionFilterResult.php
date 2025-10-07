<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Projection\PersistentProjection\Filter;

use Countable;
use IteratorAggregate;
use Traversable;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Storage\PersistentProjectionStateEnvelope;

/**
 * @template S
 * @implements IteratorAggregate<PersistentProjectionStateEnvelope<S>>
 */
final readonly class PersistentProjectionFilterResult implements IteratorAggregate, Countable
{

    /**
     * @param int $totalCount
     * @param list<PersistentProjectionStateEnvelope<S>> $items
     */
    private function __construct(
        public int $totalCount,
        private array $items,
    ) {
    }

    /**
     * @template SA
     * @param array<PersistentProjectionStateEnvelope<SA>> $items
     * @return self<SA>
     */
    public static function create(int $totalCount, array $items): self
    {
        return new self($totalCount, array_values($items));
    }

    /**
     * @return self<mixed>
     */
    public static function createEmpty(): self
    {
        return new self(0, []);
    }

    public function getIterator(): Traversable
    {
        yield from $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }
}
