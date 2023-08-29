<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\EventHandling;

use Closure;
use Wwwision\DCBEventStore\EventStream;
use Wwwision\DCBEventStore\Types\SequenceNumber;
use Wwwision\DCBEventStore\Types\StreamQuery\StreamQuery;
use Wwwision\DCBLibrary\CatchUpOptions;

interface EventHandler
{
    /**
     * @param Closure(StreamQuery $query, ?SequenceNumber $from): EventStream $read
     */
    public function catchUp(Closure $read, CatchUpOptions $options): void;
}
