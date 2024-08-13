<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Subscription;

use Closure;
use Countable;
use InvalidArgumentException;
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

    public function get(SubscriptionId $subscriptionId): Subscription
    {
        foreach ($this->items as $item) {
            if ($item->id->equals($subscriptionId)) {
                return $item;
            }
        }
        throw new InvalidArgumentException(sprintf('Subscription with id "%s" not part of this set', $subscriptionId->value), 1723567808);
    }

    public function without(SubscriptionId $subscriptionId): self
    {
        return $this->filter(static fn (Subscription $subscription) => !$subscription->id->equals($subscriptionId));
    }

    /**
     * @param Closure(Subscription): bool $callback
     */
    public function filter(Closure $callback): self
    {
        return self::fromArray(array_filter($this->items, $callback));
    }

    /**
     * @template T
     * @param Closure(Subscription): T $callback
     * @return array<T>
     */
    public function map(Closure $callback): array
    {
        return array_map($callback, $this->items);
    }

    public function withAdded(Subscription $subscription): self
    {
        if ($this->contain($subscription->id)) {
            throw new InvalidArgumentException(sprintf('Subscription with id "%s" is already part of this set', $subscription->id->value), 1723568258);
        }
        return new self([...$this->items, $subscription]);
    }

    public function withReplaced(SubscriptionId $subscriptionId, Subscription $subscription): self
    {
        if (!$this->contain($subscription->id)) {
            throw new InvalidArgumentException(sprintf('Subscription with id "%s" is not part of this set', $subscription->id->value), 1723568412);
        }
        $newItems = [];
        foreach ($this->items as $item) {
            if ($item->id->equals($subscriptionId)) {
                $newItems[] = $subscription;
            } else {
                $newItems[] = $item;
            }
        }
        return new self($newItems);
    }

    /**
     * @return iterable<Subscription>
     */
    public function jsonSerialize(): iterable
    {
        return array_values($this->items);
    }
}
