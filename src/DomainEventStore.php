<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary;

use Closure;
use Wwwision\DCBEventStore\EventStore;
use Wwwision\DCBEventStore\Setupable;
use Wwwision\DCBEventStore\Types\AppendCondition;
use Wwwision\DCBEventStore\Types\Events;
use Wwwision\DCBEventStore\Types\ExpectedHighestSequenceNumber;
use Wwwision\DCBEventStore\Types\StreamQuery\StreamQuery;
use Wwwision\DCBLibrary\Projection\Projection;

final class DomainEventStore implements ProvidesSetup
{
    public function __construct(
        private readonly EventStore $eventStore,
        private readonly EventSerializer $eventSerializer,
    ) {
    }

    public function setup(): void
    {
        if ($this->eventStore instanceof Setupable) {
            $this->eventStore->setup();
        }
    }

    /**
     * @template S
     * @param Projection<S> $projection
     * @param Closure(S): (DomainEvent|DomainEvents) $eventProducer
     * @return void
     */
    public function conditionalAppend(Projection $projection, Closure $eventProducer): void
    {
        $query = StreamQuery::wildcard();
        if ($projection instanceof StreamCriteriaAware) {
            $query = $query->withCriteria($projection->getCriteria());
        }
        $expectedHighestSequenceNumber = ExpectedHighestSequenceNumber::none();
        $state = $projection->initialState();
        foreach ($this->eventStore->read($query) as $eventEnvelope) {
            $domainEvent = $this->eventSerializer->convertEvent($eventEnvelope->event);
            $state = $projection->apply($state, $domainEvent, $eventEnvelope);
            $expectedHighestSequenceNumber = ExpectedHighestSequenceNumber::fromSequenceNumber($eventEnvelope->sequenceNumber);
        }
        $domainEvents = $eventProducer($state);
        if ($domainEvents instanceof DomainEvent) {
            $domainEvents = DomainEvents::create($domainEvents);
        }
        $events = Events::fromArray($domainEvents->map($this->eventSerializer->convertDomainEvent(...)));
        $this->eventStore->append($events, new AppendCondition($query, $expectedHighestSequenceNumber));
    }
}
