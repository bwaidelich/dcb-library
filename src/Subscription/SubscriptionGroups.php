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
 * @implements IteratorAggregate<SubscriptionGroup>
 */
#[ListBased(itemClassName: SubscriptionGroup::class)]
final class SubscriptionGroups implements IteratorAggregate, Countable, JsonSerializable
{

    /**
     * @param array<SubscriptionGroup> $items
     */
    private function __construct(
        private readonly array $items
    ) {
    }

    /**
     * @param list<SubscriptionGroup|string> $items
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

    /**
     * @return array<string>
     */
    public function toStringArray(): array
    {
        return array_map(fn (SubscriptionGroup $group) => $group->value, $this->items);
    }

    public function jsonSerialize(): mixed
    {
        return array_values($this->items);
    }
}
