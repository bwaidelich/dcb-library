<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\EventHandling;

use Wwwision\DCBEventStore\Types\EventEnvelope;
use Wwwision\DCBLibrary\DomainEvent;
use Wwwision\SubscriptionEngine\Subscription\SubscriptionId;

interface EventHandler
{
    public static function subscriptionId(): SubscriptionId;

    public function handle(DomainEvent $domainEvent, EventEnvelope $eventEnvelope): void;
}
