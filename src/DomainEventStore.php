<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary;

use Closure;
use RuntimeException;
use Wwwision\DCBEventStore\EventStore;
use Wwwision\DCBEventStore\Setupable;
use Wwwision\DCBEventStore\Types\AppendCondition;
use Wwwision\DCBEventStore\Types\EventEnvelope;
use Wwwision\DCBEventStore\Types\Events;
use Wwwision\DCBEventStore\Types\ExpectedHighestSequenceNumber;
use Wwwision\DCBEventStore\Types\StreamQuery\StreamQuery;
use Wwwision\DCBLibrary\EventHandling\EventHandler;
use Wwwision\DCBLibrary\Projection\Projection;
use Wwwision\SubscriptionEngine\Engine\SubscriptionEngineCriteria;
use Wwwision\SubscriptionEngine\Store\SubscriptionStore;
use Wwwision\SubscriptionEngine\Subscriber\Subscriber;
use Wwwision\SubscriptionEngine\Subscriber\Subscribers;
use Wwwision\SubscriptionEngine\Subscription\RunMode;
use Wwwision\SubscriptionEngine\Subscription\SubscriptionId;
use Wwwision\SubscriptionEngine\SubscriptionEngine;

final class DomainEventStore implements ProvidesSetup
{
    /**
     * @var array<Subscriber>
     */
    private array $subscribers = [];

    /**
     * @var SubscriptionEngine<EventEnvelope>|null
     */
    private SubscriptionEngine|null $subscriptionEngine = null;

    public function __construct(
        private readonly EventStore $eventStore,
        private readonly EventSerializer $eventSerializer,
        private readonly SubscriptionStore $subscriptionStore,
    ) {
    }

    public function registerSubscriber(EventHandler $eventHandler, RunMode $runMode = RunMode::FROM_BEGINNING): void
    {
        if ($this->subscriptionEngine !== null) {
            throw new RuntimeException('Subscribers must not be registered after subscription engine was initialized!', 1757497337);
        }
        $this->subscribers[] = Subscriber::create(
            id: $eventHandler->subscriptionId(),
            handler: fn (EventEnvelope $eventEnvelope) => $eventHandler->handle($this->eventSerializer->convertEvent($eventEnvelope->event), $eventEnvelope),
            runMode: $runMode,
            setup: $eventHandler instanceof ProvidesSetup ? $eventHandler->setup(...) : null,
            reset: $eventHandler instanceof ProvidesReset ? $eventHandler->reset(...) : null,
        );
    }

    public function setup(): void
    {
        if ($this->eventStore instanceof Setupable) {
            $this->eventStore->setup();
        }
        $subscriptionEngine = $this->subscriptionEngine();
        $subscriptionEngine->setup();
        $subscriptionEngine->boot();
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

    public function catchUpActiveSubscribers(): void
    {
        $this->subscriptionEngine()->catchUpActive();
    }

    public function catchUpSubscribers(SubscriptionId ...$subscriptionIds): void
    {
        $this->subscriptionEngine()->catchUpActive(SubscriptionEngineCriteria::create($subscriptionIds));
    }

    /**
     * @return SubscriptionEngine<EventEnvelope>
     */
    private function subscriptionEngine(): SubscriptionEngine
    {
        if ($this->subscriptionEngine === null) {
            $this->subscriptionEngine = new SubscriptionEngine(new DcbEventStoreAdapter($this->eventStore), $this->subscriptionStore, Subscribers::fromArray($this->subscribers));
        }
        return $this->subscriptionEngine;
    }
}
