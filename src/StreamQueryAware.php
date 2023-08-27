<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary;

use Wwwision\DCBEventStore\Types\StreamQuery\StreamQuery;

interface StreamQueryAware
{
    public function adjustStreamQuery(StreamQuery $query): StreamQuery;
}
