<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Projection;

use Wwwision\DCBEventStore\Types\EventEnvelope;
use Wwwision\DCBEventStore\Types\EventTypes;
use Wwwision\DCBLibrary\DomainEvent;
use Wwwision\DCBLibrary\EventTypesAware;

/**
 * @template S
 * @implements Projection<S>
 */
final class ClosureProjection implements Projection, EventTypesAware
{
    /**
     * @param S $initialState
     * @param array<class-string, callable> $handlers
     */
    private function __construct(
        private readonly mixed $initialState,
        public readonly array $handlers,
    ) {
    }

    /**
     * @template PS
     * @param PS $initialState
     * @return self<PS>
     */
    public static function create(mixed $initialState): self
    {
        return new self($initialState, []);
    }

    /**
     * @template E of DomainEvent
     * @param class-string<E> $class
     * @param callable(S, E): S $cb
     * @return self<S>
     */
    public function when(string $class, callable $cb): self
    {
        return new self($this->initialState, [...$this->handlers, $class => $cb]);
    }

    public function eventTypes(): EventTypes
    {
        return EventTypes::fromStrings(...array_map(static fn($domainEventClassName) => substr($domainEventClassName, strrpos($domainEventClassName, '\\') + 1), array_keys($this->handlers)));
    }

    /**
     * @return S
     */
    public function initialState(): mixed
    {
        return $this->initialState;
    }

    /**
     * @param S $state
     * @return S
     */
    public function apply(mixed $state, DomainEvent $domainEvent, EventEnvelope $eventEnvelope): mixed
    {
        if (!array_key_exists($domainEvent::class, $this->handlers)) {
            return $state;
        }
        return $this->handlers[$domainEvent::class]($state, $domainEvent, $eventEnvelope);
    }
}
