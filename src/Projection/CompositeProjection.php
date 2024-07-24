<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Projection;

use stdClass;
use Wwwision\DCBEventStore\Types\EventEnvelope;
use Wwwision\DCBEventStore\Types\StreamQuery\Criteria;
use Wwwision\DCBEventStore\Types\StreamQuery\StreamQuery;
use Wwwision\DCBLibrary\DomainEvent;
use Wwwision\DCBLibrary\StreamCriteriaAware;

/**
 * @template S of stdClass
 * @implements Projection<S>
 */
final class CompositeProjection implements Projection, StreamCriteriaAware
{
    /**
     * @template PS
     * @param array<string, Projection<PS>> $projections
     */
    private function __construct(
        private readonly array $projections,
    ) {
    }

    /**
     * @template PS
     * @param array<string, Projection<PS>> $projections
     */
    public static function create(array $projections): self // @phpstan-ignore-line TODO fix
    {
        return new self($projections);
    }


    /**
     * @return S
     */
    public function initialState(): stdClass
    {
        $state = new stdClass();
        foreach ($this->projections as $projectionKey => $projection) {
            $state->{$projectionKey} = $projection->initialState();
        }
        return $state;  // @phpstan-ignore-line TODO fix
    }

    /**
     * @param S $state
     * @return S
     */
    public function apply(mixed $state, DomainEvent $domainEvent, EventEnvelope $eventEnvelope): stdClass
    {
        foreach ($this->projections as $projectionKey => $projection) {
            if ($projection instanceof StreamCriteriaAware && !$projection->getCriteria()->hashes()->intersect($eventEnvelope->criterionHashes)) {
                continue;
            }
            #var_dump($eventEnvelope->event->type->value . ' for ' . $projectionKey . ' tags: ' . implode(', ', $eventEnvelope->event->tags->toSimpleArray()));
            $state->{$projectionKey} = $projection->apply($state->{$projectionKey}, $domainEvent, $eventEnvelope);
        }
        return $state;
    }

    public function getCriteria(): Criteria
    {
        $criteria = Criteria::create();
        foreach ($this->projections as $projection) {
            if ($projection instanceof StreamCriteriaAware) {
                $criteria = $criteria->merge($projection->getCriteria());
            }
        }
        return $criteria;
    }
}
