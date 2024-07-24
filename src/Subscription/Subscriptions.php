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
 * @implements IteratorAggregate<Subscription>
 */
#[ListBased(itemClassName: Subscription::class)]
final class Subscriptions implements IteratorAggregate, Countable, JsonSerializable
{

    /**
     * @param array<Subscription> $items
     */
    private function __construct(
        private readonly array $items
    ) {
    }

    /**
     * @param array<Subscription> $items
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

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function contain(SubscriptionId $subscriptionId): bool
    {
        foreach ($this->items as $item) {
            if ($item->id->equals($subscriptionId)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return iterable<Subscription>
     */
    public function jsonSerialize(): iterable
    {
        return array_values($this->items);
    }
}