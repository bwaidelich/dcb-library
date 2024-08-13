<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Subscription;

use Countable;
use IteratorAggregate;
use JsonSerializable;
use Traversable;
use Wwwision\Types\Attributes\ListBased;
use function Wwwision\Types\instantiate;

/**
 * @implements IteratorAggregate<SubscriptionId>
 */
#[ListBased(itemClassName: SubscriptionId::class)]
final class SubscriptionIds implements IteratorAggregate, Countable, JsonSerializable
{

    /**
     * @param array<SubscriptionId> $items
     */
    private function __construct(
        private readonly array $items
    ) {
    }

    /**
     * @param array<string|SubscriptionId> $items
     */
    public static function fromArray(array $items): self
    {
        return instantiate(self::class, $items);
    }

    public static function none(): self
    {
        return self::fromArray([]);
    }

    public function getIterator(): Traversable
    {
        return yield from $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function contain(SubscriptionId $id): bool
    {
        foreach ($this->items as $item) {
            if ($item->equals($id)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array<string>
     */
    public function toStringArray(): array
    {
        return array_map(static fn (SubscriptionId $id) => $id->value, $this->items);
    }

    /**
     * @return iterable<mixed>
     */
    public function jsonSerialize(): iterable
    {
        return array_values($this->items);
    }
}
