<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Adapters;

use Wwwision\DCBEventStore\EventStore;
use Wwwision\DCBLibrary\CatchUpOptions;
use Wwwision\DCBLibrary\CatchUpQueue;
use Wwwision\DCBLibrary\EventHandling\EventHandlers;

/**
 * @internal This adapter should not be used in production – embrace eventual consistency!
 */
final class SynchronousCatchUpQueue implements CatchUpQueue
{
    public function __construct(
        private readonly EventStore $eventStore,
        private readonly EventHandlers $eventHandlers,
    ) {
    }

    public function run(): void
    {
        foreach ($this->eventHandlers as $eventHandler) {
            $eventHandler->catchUp($this->eventStore->read(...), CatchUpOptions::create());
        }
    }
}
