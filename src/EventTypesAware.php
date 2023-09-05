<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary;

use Wwwision\DCBEventStore\Types\EventTypes;

interface EventTypesAware
{
    public function eventTypes(): EventTypes;
}
