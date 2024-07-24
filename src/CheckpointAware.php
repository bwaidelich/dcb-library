<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary;

use Wwwision\DCBEventStore\Types\SequenceNumber;

interface CheckpointAware
{
    public function getCheckpoint(): SequenceNumber;
}
