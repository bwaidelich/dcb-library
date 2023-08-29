<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Projection;

use Wwwision\DCBLibrary\DomainEvent;

/**
 * @template S
 * @extends Projection<S>
 */
interface PartitionedProjection extends Projection
{
    public function partitionKey(DomainEvent $domainEvent): string;

    /**
     * @return S
     */
    public function loadState(string $partitionKey): mixed;

    /**
     * @param S $state
     */
    public function saveState($state): void;
}
