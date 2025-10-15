<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Projection\PersistentProjection;

use Closure;
use RuntimeException;
use Wwwision\DCBEventStore\Types\EventEnvelope;
use Wwwision\DCBLibrary\DomainEvent;
use Wwwision\DCBLibrary\EventHandling\EventHandler;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\PersistentProjectionFilter;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\PersistentProjectionFilterResult;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Storage\PersistentProjectionStateEnvelope;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Storage\PersistentProjectionStorage;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Storage\SerializedPersistentProjectionStateEnvelope;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Storage\Serializer\PersistentProjectionStateSerializer;
use Wwwision\DCBLibrary\Projection\Projection;
use Wwwision\DCBLibrary\ProvidesReset;
use Wwwision\DCBLibrary\ProvidesSetup;
use Wwwision\DCBLibrary\StreamCriteriaAware;
use Wwwision\SubscriptionEngine\Subscription\SubscriptionId;

/**
 * @template S
 */
final readonly class PersistentProjection implements EventHandler, ProvidesSetup, ProvidesReset
{
    /**
     * @param Projection<S> $projection
     * @param Closure(DomainEvent, EventEnvelope): (string|null) $partitionKeyExtractor
     * @param PersistentProjectionStateSerializer<S> $serializer
     */
    public function __construct(
        private SubscriptionId $subscriptionId,
        private Projection $projection,
        public Closure $partitionKeyExtractor,
        private PersistentProjectionStateSerializer $serializer,
        private PersistentProjectionStorage $storage,
    ) {
    }

    public function subscriptionId(): SubscriptionId
    {
        return $this->subscriptionId;
    }

    /**
     * @return PersistentProjectionStateEnvelope<S>|null
     */
    public function findOne(string $partitionKey): PersistentProjectionStateEnvelope|null
    {
        $envelope = $this->storage->loadStateEnvelope($partitionKey);
        if ($envelope === null) {
            return null;
        }
        return new PersistentProjectionStateEnvelope($envelope->partitionKey, $this->serializer->unserialize($envelope->serializedState), $envelope->createdAt, $envelope->lastUpdatedAt);
    }

    /**
     * @return PersistentProjectionFilterResult<S>
     */
    public function find(PersistentProjectionFilter $filter): PersistentProjectionFilterResult
    {
        $result = $this->storage->find($filter);
        return PersistentProjectionFilterResult::create(
            $result->totalCount,
            $result->map(fn (SerializedPersistentProjectionStateEnvelope $envelope) => new PersistentProjectionStateEnvelope($envelope->partitionKey, $this->serializer->unserialize($envelope->serializedState), $envelope->createdAt, $envelope->lastUpdatedAt))
        );
    }

    public function handle(DomainEvent $domainEvent, EventEnvelope $eventEnvelope): void
    {
        if ($this->projection instanceof StreamCriteriaAware && $this->projection->getCriteria()->matchesEvent($eventEnvelope->event)) {
            return;
        }
        $partitionKey = ($this->partitionKeyExtractor)($domainEvent, $eventEnvelope);
        if ($partitionKey === null) {
            return;
        }
        $stateEnvelope = $this->storage->loadStateEnvelope($partitionKey);
        if ($stateEnvelope === null) {
            $state = $this->projection->initialState();
        } else {
            $state = $this->serializer->unserialize($stateEnvelope->serializedState);
        }
        $state = $this->projection->apply($state, $domainEvent, $eventEnvelope);
        if ($state === null) {
            $this->storage->removeStateEnvelope($partitionKey);
            return;
        }
        $serializedState = $this->serializer->serialize($state);
        if ($stateEnvelope === null) {
            $stateEnvelope = SerializedPersistentProjectionStateEnvelope::create(
                $partitionKey,
                $serializedState,
                $eventEnvelope->recordedAt,
            );
        } else {
            $stateEnvelope = $stateEnvelope->withUpdatedState($serializedState, $eventEnvelope->recordedAt);
        }
        $this->storage->saveStateEnvelope($stateEnvelope);
    }

    public function reset(): void
    {
        $this->storage->flush();
    }

    public function setup(): void
    {
        if ($this->storage instanceof ProvidesSetup) {
            $this->storage->setup();
        }
    }
}
