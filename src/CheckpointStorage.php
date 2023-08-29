<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary;

use Wwwision\DCBEventStore\Types\SequenceNumber;

interface CheckpointStorage
{
    public function acquireLock(): ?SequenceNumber;
    public function updateAndReleaseLock(SequenceNumber $sequenceNumber): void;

    public function reset(): void;
}
