<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary;

use Wwwision\DCBEventStore\Types\StreamQuery\Criteria;

interface StreamCriteriaAware
{
    public function getCriteria(): Criteria;
}
