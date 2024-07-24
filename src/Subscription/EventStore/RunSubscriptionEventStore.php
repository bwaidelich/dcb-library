<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Subscription\EventStore;

use Wwwision\DCBEventStore\EventStore;
use Wwwision\DCBEventStore\EventStream;
use Wwwision\DCBEventStore\Types\AppendCondition;
use Wwwision\DCBEventStore\Types\Events;
use Wwwision\DCBEventStore\Types\ReadOptions;
use Wwwision\DCBEventStore\Types\StreamQuery\StreamQuery;
use Wwwision\DCBLibrary\Subscription\Engine\SubscriptionEngine;
use Wwwision\DCBLibrary\Subscription\Engine\SubscriptionEngineCriteria;

final class RunSubscriptionEventStore implements EventStore
{

    public function __construct(
        private readonly EventStore $eventStore,
        private readonly SubscriptionEngine $subscriptionEngine,
        private readonly SubscriptionEngineCriteria|null $criteria = null,
    ) {
    }

    public function read(StreamQuery $query, ?ReadOptions $options = null): EventStream
    {
        return $this->eventStore->read($query, $options);
    }

    public function readAll(?ReadOptions $options = null): EventStream
    {
        return $this->eventStore->readAll($options);
    }

    public function append(Events $events, AppendCondition $condition): void
    {
        $this->eventStore->append($events, $condition);
        $this->subscriptionEngine->run($this->criteria ?? SubscriptionEngineCriteria::noConstraints());
    }
}