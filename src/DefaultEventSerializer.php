<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary;

use Wwwision\DCBEventStore\Types\Event;
use Wwwision\DCBEventStore\Types\EventData;
use Wwwision\DCBEventStore\Types\EventId;
use Wwwision\DCBEventStore\Types\EventMetadata;
use Wwwision\DCBEventStore\Types\EventType;
use Wwwision\Types\Parser;
use function Wwwision\Types\instantiate;

final class DefaultEventSerializer implements EventSerializer
{
    public function __construct(
        private readonly string $domainEventNamespace,
    ) {
    }

    public function convertEvent(Event $event): DomainEvent
    {
        $className = rtrim($this->domainEventNamespace, '\\') . '\\' . $event->type->value;
        $payload = json_decode($event->data->value, true, 512, JSON_THROW_ON_ERROR);
        return Parser::instantiate($className, $payload);
    }

    public function convertDomainEvent(DomainEvent $domainEvent): Event
    {
        // TODO support decorating events with id & metadata
        $payloadJson = json_encode($domainEvent, JSON_THROW_ON_ERROR);
        return new Event(
            EventId::create(),
            EventType::fromString(substr($domainEvent::class, strrpos($domainEvent::class, '\\') + 1)),
            EventData::fromString($payloadJson),
            $domainEvent->tags(),
            EventMetadata::none(),
        );
    }
}
