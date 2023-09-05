<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\EventHandling;

use Closure;
use Wwwision\DCBEventStore\EventStream;
use Wwwision\DCBEventStore\Types\EventEnvelope;
use Wwwision\DCBEventStore\Types\SequenceNumber;
use Wwwision\DCBEventStore\Types\StreamQuery\StreamQuery;
use Wwwision\DCBLibrary\CatchUpOptions;
use Wwwision\DCBLibrary\CheckpointStorage;
use Wwwision\DCBLibrary\DomainEvent;
use Wwwision\DCBLibrary\EventSerializer;
use Wwwision\DCBLibrary\EventTypesAware;
use Wwwision\DCBLibrary\Projection\PartitionedProjection;
use Wwwision\DCBLibrary\ProvidesReset;
use Wwwision\DCBLibrary\ProvidesSetup;
use Wwwision\DCBLibrary\StreamQueryAware;
use Wwwision\DCBLibrary\TagsAware;

/**
 * @template S
 */
final class ProjectionEventHandler implements EventHandler, ProvidesSetup, ProvidesReset
{
    /**
     * @var array<string, mixed>
     */
    private array $statesByPartitionKey = [];

    /**
     * @param PartitionedProjection<S> $projection
     */
    public function __construct(
        private readonly PartitionedProjection $projection,
        private readonly CheckpointStorage $checkpointStorage,
        private readonly EventSerializer $eventSerializer,
    ) {
    }

    /**
     * @param Closure(StreamQuery, ?SequenceNumber): EventStream $read
     * @param CatchUpOptions $options
     * @return void
     */
    public function catchUp(Closure $read, CatchUpOptions $options): void
    {
        $query = StreamQuery::wildcard();
        if ($this->projection instanceof StreamQueryAware) {
            $query = $this->projection->adjustStreamQuery($query);
        }
        $highestAppliedSequenceNumber = $this->startCatchUp();
        $iteration = 0;
        try {
            foreach ($read($query, $highestAppliedSequenceNumber?->next()) as $eventEnvelope) {
                if ($highestAppliedSequenceNumber !== null && $eventEnvelope->sequenceNumber->value <= $highestAppliedSequenceNumber->value) {
                    continue;
                }
                $domainEvent = $this->eventSerializer->convertEvent($eventEnvelope->event);
                $this->handle($domainEvent, $eventEnvelope);
                if ($options->progressCallback !== null) {
                    ($options->progressCallback)($eventEnvelope);
                }
                $iteration++;
                $highestAppliedSequenceNumber = $eventEnvelope->sequenceNumber;
                if ($options->batchSize === 1 || $iteration % $options->batchSize === 0) {
                    $this->finishCatchUp($highestAppliedSequenceNumber);
                    $highestAppliedSequenceNumber = $this->startCatchUp();
                }
            }
        } finally {
            if ($highestAppliedSequenceNumber !== null) {
                $this->finishCatchUp($highestAppliedSequenceNumber);
            }
        }
    }

    private function startCatchUp(): ?SequenceNumber
    {
        $this->statesByPartitionKey = [];
        return $this->checkpointStorage->acquireLock();
    }

    private function handle(DomainEvent $domainEvent, EventEnvelope $eventEnvelope): void
    {
        if ($this->projection instanceof EventTypesAware && !$this->projection->eventTypes()->contain($eventEnvelope->event->type)) {
            return;
        }
        if ($this->projection instanceof TagsAware && !$domainEvent->tags()->containEvery($this->projection->tags())) {
            return;
        }
        $partitionKey = $this->projection->partitionKey($domainEvent);
        if (!array_key_exists($partitionKey, $this->statesByPartitionKey)) {
            $this->statesByPartitionKey[$partitionKey] = $this->projection->loadState($partitionKey);
        }
        $this->statesByPartitionKey[$partitionKey] = $this->projection->apply($this->statesByPartitionKey[$partitionKey], $domainEvent, $eventEnvelope);
    }

    private function finishCatchUp(SequenceNumber $sequenceNumber): void
    {
        $this->checkpointStorage->updateAndReleaseLock($sequenceNumber);
        foreach ($this->statesByPartitionKey as $state) {
            $this->projection->saveState($state);
        }
        $this->statesByPartitionKey = [];
    }

    public function setup(): void
    {
        if ($this->checkpointStorage instanceof ProvidesSetup) {
            $this->checkpointStorage->setup();
        }
        if ($this->projection instanceof ProvidesSetup) {
            $this->projection->setup();
        }
    }

    public function reset(): void
    {
        $this->checkpointStorage->reset();
        if ($this->projection instanceof ProvidesReset) {
            $this->projection->reset();
        }
    }
}
