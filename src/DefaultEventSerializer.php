<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary;

use JsonException;
use RuntimeException;
use Wwwision\DCBEventStore\Types\Event;
use Wwwision\Types\Parser;

final class DefaultEventSerializer implements EventSerializer
{
    public function __construct(
        private readonly string $domainEventNamespace,
    ) {
    }

    public function convertEvent(Event $event): DomainEvent
    {
        /** @var class-string<DomainEvent> $className */
        $className = rtrim($this->domainEventNamespace, '\\') . '\\' . $event->type->value;
        try {
            $payload = json_decode($event->data->value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException(sprintf('Failed to JSON-decode event data: %s', $e->getMessage()), 1715009639, $e);
        }
        $domainEvent = Parser::instantiate($className, $payload);
        if (!$domainEvent instanceof DomainEvent) {
            throw new RuntimeException(sprintf('Expected denormalized event to implement %s, got: %s', DomainEvent::class, get_debug_type($domainEvent)), 1715009568);
        }
        return $domainEvent;
    }

    public function convertDomainEvent(DomainEvent $domainEvent): Event
    {
        // TODO support decorating events with id & metadata
        try {
            $payloadJson = json_encode($domainEvent, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException(sprintf('Failed to JSON-encode event data: %s', $e->getMessage()), 1715009664, $e);
        }
        return Event::create(
            type: substr($domainEvent::class, strrpos($domainEvent::class, '\\') + 1),
            data: $payloadJson,
            tags: $domainEvent->tags(),
        );
    }
}
