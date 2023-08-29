<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\EventHandling;

use ArrayIterator;
use InvalidArgumentException;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<string, EventHandler>
 */
final class EventHandlers implements IteratorAggregate
{
    /**
     * @param array<string, EventHandler> $handlersByName
     */
    private function __construct(
        private readonly array $handlersByName,
    ) {
    }

    public static function create(): self
    {
        return new self([]);
    }

    public function with(string $eventHandlerName, EventHandler $handler): self
    {
        if (array_key_exists($eventHandlerName, $this->handlersByName)) {
            throw new InvalidArgumentException(sprintf('Failed to add event handler instance named "%s" because a corresponding handler is already registered', $eventHandlerName), 1693149787);
        }
        return new self([...$this->handlersByName, $eventHandlerName => $handler]);
    }

    public function get(string $eventHandlerName): EventHandler
    {
        if (!array_key_exists($eventHandlerName, $this->handlersByName)) {
            throw new InvalidArgumentException(sprintf('Failed to get event handler instance named "%s" because no corresponding handler is registered', $eventHandlerName), 1691420951);
        }
        return $this->handlersByName[$eventHandlerName];
    }

    /**
     * @return Traversable<string, EventHandler>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->handlersByName);
    }
}
