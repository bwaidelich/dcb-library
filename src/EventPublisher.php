<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary;

use Closure;
use Wwwision\DCBEventStore\EventStore;
use Wwwision\DCBEventStore\Types\AppendCondition;
use Wwwision\DCBEventStore\Types\Events;
use Wwwision\DCBEventStore\Types\ExpectedHighestSequenceNumber;
use Wwwision\DCBEventStore\Types\StreamQuery\StreamQuery;
use Wwwision\DCBLibrary\Projection\Projection;

final class EventPublisher
{
    public function __construct(
        private readonly EventStore $eventStore,
        private readonly EventSerializer $eventSerializer,
        private readonly CatchUpQueue $catchUpQueue,
    ) {
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
        if ($projection instanceof StreamQueryAware) {
            $query = $projection->adjustStreamQuery($query);
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
        $this->catchUpQueue->run();
    }
}