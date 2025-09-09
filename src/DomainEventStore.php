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
     * @return DecisionModel<S>
     */
    public function buildDecisionModel(Projection $projection): DecisionModel
    {
        $query = StreamQuery::wildcard();
        if ($projection instanceof StreamCriteriaAware) {
            foreach ($projection->getCriteria() as $criterion) {
                $query = $query->withCriterion($criterion);
            }
        }
        $expectedHighestSequenceNumber = ExpectedHighestSequenceNumber::none();
        $state = $projection->initialState();
        foreach ($this->eventStore->read($query) as $eventEnvelope) {
            $domainEvent = $this->eventSerializer->convertEvent($eventEnvelope->event);
            $state = $projection->apply($state, $domainEvent, $eventEnvelope);
            $expectedHighestSequenceNumber = ExpectedHighestSequenceNumber::fromSequenceNumber($eventEnvelope->sequenceNumber);
        }
        return new DecisionModel($query, $expectedHighestSequenceNumber, $state);
    }

    public function append(DomainEvent|DomainEvents $domainEvents): void
    {
        if ($domainEvents instanceof DomainEvent) {
            $domainEvents = DomainEvents::create($domainEvents);
        }
        $events = Events::fromArray($domainEvents->map($this->eventSerializer->convertDomainEvent(...)));
        $this->eventStore->append($events, AppendCondition::noConstraints());
    }

    /**
     * @template S
     * @param Projection<S> $projection
     * @param Closure(S): (DomainEvent|DomainEvents) $eventProducer
     * @return void
     */
    public function conditionalAppend(Projection $projection, Closure $eventProducer): void
    {
        $decisionModel = $this->buildDecisionModel($projection);
        $domainEvents = $eventProducer($decisionModel->state);
        if ($domainEvents instanceof DomainEvent) {
            $domainEvents = DomainEvents::create($domainEvents);
        }
        $events = Events::fromArray($domainEvents->map($this->eventSerializer->convertDomainEvent(...)));
        $this->eventStore->append($events, new AppendCondition($decisionModel->query, $decisionModel->expectedHighestSequenceNumber));
    }
}
