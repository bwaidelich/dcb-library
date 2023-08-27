<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary;

use ArrayIterator;
use Closure;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<DomainEvent>
 */
final class DomainEvents implements IteratorAggregate
{

    /**
     * @param array<DomainEvent> $domainEvents
     */
    private function __construct(private readonly array $domainEvents)
    {
    }

    public static function create(DomainEvent ...$domainEvents): self
    {
        return new self($domainEvents);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->domainEvents);
    }

    /**
     * @return array<mixed>
     */
    public function map(Closure $callback): array
    {
        return array_map($callback, $this->domainEvents);
    }
}
