<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\EventHandling;

use Closure;
use RuntimeException;
use Wwwision\DCBEventStore\EventStream;
use Wwwision\DCBEventStore\Types\EventEnvelope;
use Wwwision\DCBEventStore\Types\SequenceNumber;
use Wwwision\DCBEventStore\Types\StreamQuery\StreamQuery;
use Wwwision\DCBLibrary\CatchUpOptions;
use Wwwision\DCBLibrary\CheckpointAware;
use Wwwision\DCBLibrary\DomainEvent;
use Wwwision\DCBLibrary\Projection\Projection;
use Wwwision\DCBLibrary\ProvidesReset;
use Wwwision\DCBLibrary\ProvidesSetup;
use Wwwision\DCBLibrary\StreamCriteriaAware;

/**
 * @template S
 */
final class ProjectionEventHandler implements EventHandler, ProvidesSetup, ProvidesReset
{

    /**
     * @param Projection<S>&CheckpointAware $projection
     */
    public function __construct(
        private readonly Projection&CheckpointAware $projection,
    ) {
    }

    public function setup(): void
    {
        if ($this->projection instanceof ProvidesSetup) {
            $this->projection->setup();
        }
    }

    public function reset(): void
    {
        if ($this->projection instanceof ProvidesReset) {
            $this->projection->reset();
        }
    }

    public function handle(DomainEvent $domainEvent, EventEnvelope $eventEnvelope): void
    {
        $sequenceNumber = $this->projection->getCheckpoint();
        if (!$sequenceNumber->next()->equals($eventEnvelope->sequenceNumber)) {
            throw new RuntimeException(sprintf('Expected next sequence number of %s, got: %d', $sequenceNumber->next()->value, $eventEnvelope->sequenceNumber->value), 1720006460);
        }
        $this->projection->apply($this->projection->initialState(), $domainEvent, $eventEnvelope);
    }

    /**
     * @param Closure(StreamQuery, ?SequenceNumber): EventStream $read
     * @param CatchUpOptions $options
     * @return void
     */
    public function catchUp(Closure $read, CatchUpOptions $options): void
    {
        $query = StreamQuery::wildcard();
        if ($this->projection instanceof StreamCriteriaAware) {
            $query = $query->withCriteria($this->projection->getCriteria());
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

    private function finishCatchUp(SequenceNumber $sequenceNumber): void
    {
        if ($this->projection instanceof PartitionedProjection) {
            foreach ($this->statesByPartitionKey as $state) {
                $this->projection->saveState($state);
            }
        }
        $this->checkpointStorage->updateAndReleaseLock($sequenceNumber);
        $this->statesByPartitionKey = [];
    }
}
