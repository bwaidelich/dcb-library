<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Projection;

use Wwwision\DCBEventStore\Types\EventEnvelope;
use Wwwision\DCBLibrary\DomainEvent;

/**
 * A dummy projection that has no side effects, mostly for testing purposes
 * @implements Projection<null>
 */
final class NullProjection implements Projection
{

    public function initialState(): null
    {
        return null;
    }

    public function apply(mixed $state, DomainEvent $domainEvent, EventEnvelope $eventEnvelope): null
    {
        return null;
    }
}
