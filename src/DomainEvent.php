<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary;

use Wwwision\DCBEventStore\Types\Tags;

interface DomainEvent
{
    public function tags(): Tags;
}
