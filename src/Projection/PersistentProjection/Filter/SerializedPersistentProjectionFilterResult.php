<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Projection\PersistentProjection\Filter;

use Closure;
use Countable;
use IteratorAggregate;
use Traversable;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Storage\SerializedPersistentProjectionStateEnvelope;

/**
 * @implements IteratorAggregate<SerializedPersistentProjectionStateEnvelope>
 */
final readonly class SerializedPersistentProjectionFilterResult implements IteratorAggregate, Countable
{

    /**
     * @param int $totalCount
     * @param list<SerializedPersistentProjectionStateEnvelope> $items
     */
    private function __construct(
        public int $totalCount,
        private array $items,
    ) {
    }

    /**
     * @param array<SerializedPersistentProjectionStateEnvelope> $items
     */
    public static function create(int $totalCount, array $items): self
    {
        return new self($totalCount, array_values($items));
    }

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

    /**
     * @template T
     * @param Closure(SerializedPersistentProjectionStateEnvelope): T $callback
     * @return array<T>
     */
    public function map(Closure $callback): array
    {
        return array_map($callback, $this->items);
    }
}
