<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Projection\PersistentProjection\Storage\Serializer;

/**
 * @template S
 */
interface PersistentProjectionStateSerializer
{
    /**
     * @param S $state
     */
    public function serialize(mixed $state): string;

    /**
     * @return S
     */
    public function unserialize(string $value): mixed;
}
