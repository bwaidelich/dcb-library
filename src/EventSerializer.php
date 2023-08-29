<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary;

use Wwwision\DCBEventStore\Types\Event;

interface EventSerializer
{
    public function convertEvent(Event $event): DomainEvent;
    public function convertDomainEvent(DomainEvent $domainEvent): Event;
}
