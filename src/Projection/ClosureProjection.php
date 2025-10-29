<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Projection;

use Wwwision\DCBEventStore\Types\EventEnvelope;
use Wwwision\DCBEventStore\Types\EventTypes;
use Wwwision\DCBEventStore\Types\StreamQuery\Criteria;
use Wwwision\DCBEventStore\Types\Tag;
use Wwwision\DCBEventStore\Types\Tags;
use Wwwision\DCBLibrary\DomainEvent;
use Wwwision\DCBLibrary\ProvidesTags;
use Wwwision\DCBLibrary\StreamCriteriaAware;

/**
 * @template S
 * @implements Projection<S>
 */
final class ClosureProjection implements Projection, StreamCriteriaAware
{
    /**
     * @param S $initialState
     * @param array<class-string, mixed> $handlers
     */
    private function __construct(
        private readonly mixed $initialState,
        public readonly array $handlers,
        public readonly bool $onlyLastEvent,
    ) {
    }

    /**
     * @template PS
     * @param PS $initialState
     * @return self<PS>
     */
    public static function create(mixed $initialState, bool $onlyLastEvent = false): self
    {
        return new self($initialState, [], $onlyLastEvent);
    }

    /**
     * @template E of DomainEvent
     * @template SN
     * @param class-string<E> $class
     * @param (callable(S|SN, E, EventEnvelope): SN)|(S|SN) $cb
     * @return self<S|SN>
     */
    public function when(string $class, mixed $cb): self
    {
        return new self($this->initialState, [...$this->handlers, $class => $cb], $this->onlyLastEvent); // @phpstan-ignore return.type
    }

    /**
     * @return TaggedProjection<S>
     */
    public function for(Tags|Tag|ProvidesTags $tags): TaggedProjection
    {
        return TaggedProjection::create($tags, $this);
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
        if (is_callable($this->handlers[$domainEvent::class])) {
            return $this->handlers[$domainEvent::class]($state, $domainEvent, $eventEnvelope);
        }
        return $this->handlers[$domainEvent::class];
    }

    public function getCriteria(): Criteria
    {
        $eventTypes = EventTypes::fromStrings(...array_map(static fn($domainEventClassName) => substr($domainEventClassName, strrpos($domainEventClassName, '\\') + 1), array_keys($this->handlers)));
        return Criteria::create(Criteria\EventTypesAndTagsCriterion::create(eventTypes: $eventTypes, onlyLastEvent: $this->onlyLastEvent));
    }
}
