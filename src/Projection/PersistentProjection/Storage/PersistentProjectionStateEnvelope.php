<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Projection\PersistentProjection\Storage;

use DateTimeImmutable;

/**
 * @template S
 */
final readonly class PersistentProjectionStateEnvelope
{

    /**
     * @param S $state
     */
    public function __construct(
        public string $partitionKey,
        public mixed $state,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $lastUpdatedAt,
    ) {
    }

    /**
     * @param S $state
     * @return self<S>
     */
    public static function create(
        string $partitionKey,
        mixed $state,
        DateTimeImmutable $now,
    ): self {
        return new self($partitionKey, $state, $now, $now);
    }

    /**
     * @param S $state
     * @return self<S>
     */
    public function withUpdatedState(mixed $state, DateTimeImmutable $now): self
    {
        return new self(
            $this->partitionKey,
            $state,
            $this->createdAt,
            $now,
        );
    }
}
