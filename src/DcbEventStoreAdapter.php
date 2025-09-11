<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary;

use Wwwision\DCBEventStore\EventStore;
use Wwwision\DCBEventStore\Types\EventEnvelope;
use Wwwision\DCBEventStore\Types\ReadOptions;
use Wwwision\DCBEventStore\Types\StreamQuery\StreamQuery;
use Wwwision\SubscriptionEngine\EventStore\EventStoreAdapter;
use Wwwision\SubscriptionEngine\Subscription\Position;

/**
 * @implements EventStoreAdapter<EventEnvelope>
 */
final class DcbEventStoreAdapter implements EventStoreAdapter
{
    public function __construct(
        private readonly EventStore $eventStore,
    ) {
    }

    public function read(Position $startPosition): iterable
    {
        return $this->eventStore->read(StreamQuery::wildcard(), ReadOptions::create(from: $startPosition->value));
    }

    public function lastPosition(): Position
    {
        $lastEventEnvelope = $this->eventStore->read(StreamQuery::wildcard(), ReadOptions::create(backwards: true))->first();
        return Position::fromInteger($lastEventEnvelope->sequenceNumber->value ?? 0);
    }

    public function eventPosition(object $event): Position
    {
        return Position::fromInteger($event->sequenceNumber->value);
    }
}
