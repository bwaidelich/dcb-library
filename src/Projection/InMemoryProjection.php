<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Projection;

use Closure;
use Wwwision\DCBEventStore\Types\EventEnvelope;
use Wwwision\DCBEventStore\Types\EventTypes;
use Wwwision\DCBEventStore\Types\StreamQuery\Criteria\EventTypesAndTagsCriterion;
use Wwwision\DCBEventStore\Types\StreamQuery\StreamQuery;
use Wwwision\DCBEventStore\Types\Tag;
use Wwwision\DCBEventStore\Types\Tags;
use Wwwision\DCBLibrary\DomainEvent;
use Wwwision\DCBLibrary\StreamQueryAware;

/**
 * @template S
 * @implements Projection<S>
 */
final class InMemoryProjection implements Projection, StreamQueryAware
{
    /**
     * @param array<class-string<DomainEvent>, Closure(S, DomainEvent, EventEnvelope): S> $handlers
     * @param S $initialState
     */
    private function __construct(
        private readonly Tags $tags,
        private readonly array $handlers,
        private readonly mixed $initialState = null,
    ) {
    }

    /**
     * @template SS
     * @param Tags|Tag $tags
     * @param array<class-string<DomainEvent>, Closure(SS, DomainEvent, EventEnvelope): SS> $handlers
     * @param SS $initialState
     * @return self<SS>
     */
    public static function create(Tags|Tag $tags, array $handlers, mixed $initialState): self {
        return new self(
            $tags instanceof Tag ? Tags::create($tags) : $tags,
            $handlers,
            $initialState,
        );
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
        if (!$domainEvent->tags()->containEvery($this->tags)) {
            return $state;
        }
        return $this->handlers[$domainEvent::class]($state, $domainEvent, $eventEnvelope);
    }

    public function adjustStreamQuery(StreamQuery $query): StreamQuery
    {
        $eventTypes = EventTypes::fromStrings(...array_map(static fn($domainEventClassName) => substr($domainEventClassName, strrpos($domainEventClassName, '\\') + 1), array_keys($this->handlers)));
        return $query->withCriterion(new EventTypesAndTagsCriterion($eventTypes, $this->tags));
    }
}
