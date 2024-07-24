<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\EventHandling;

use Wwwision\DCBEventStore\Types\EventEnvelope;
use Wwwision\DCBLibrary\DomainEvent;

interface EventHandler
{
    public function handle(DomainEvent $domainEvent, EventEnvelope $eventEnvelope): void;
}
