<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Projection\PersistentProjection\Storage;

use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\PersistentProjectionFilter;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\SerializedPersistentProjectionFilterResult;

interface PersistentProjectionStorage
{

    public function loadStateEnvelope(string $partitionKey): SerializedPersistentProjectionStateEnvelope|null;

    public function saveStateEnvelope(SerializedPersistentProjectionStateEnvelope $state): void;

    public function find(PersistentProjectionFilter $filter): SerializedPersistentProjectionFilterResult;

    public function flush(): void;
}
