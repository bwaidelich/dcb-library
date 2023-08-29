<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Projection;

use stdClass;
use Wwwision\DCBEventStore\Types\EventEnvelope;
use Wwwision\DCBEventStore\Types\StreamQuery\StreamQuery;
use Wwwision\DCBLibrary\DomainEvent;
use Wwwision\DCBLibrary\StreamQueryAware;

/**
 * @template S of stdClass
 * @implements Projection<S>
 */
final class CompositeProjection implements Projection, StreamQueryAware
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
            $state->{$projectionKey} = $projection->apply($state->{$projectionKey}, $domainEvent, $eventEnvelope);
        }
        return $state;
    }

    public function adjustStreamQuery(StreamQuery $query): StreamQuery
    {
        foreach ($this->projections as $projection) {
            if ($projection instanceof StreamQueryAware) {
                $query = $projection->adjustStreamQuery($query);
            }
        }
        return $query;
    }
}
