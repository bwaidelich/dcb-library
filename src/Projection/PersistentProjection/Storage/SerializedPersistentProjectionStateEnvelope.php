<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Projection\PersistentProjection\Storage;

use DateTimeImmutable;

final readonly class SerializedPersistentProjectionStateEnvelope
{

    public function __construct(
        public string $partitionKey,
        public string $serializedState,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $lastUpdatedAt,
    ) {
    }

    public static function create(
        string $partitionKey,
        string $serializedState,
        DateTimeImmutable $now,
    ): self {
        return new self($partitionKey, $serializedState, $now, $now);
    }

    public function withUpdatedState(string $serializedState, DateTimeImmutable $now): self
    {
        return new self(
            $this->partitionKey,
            $serializedState,
            $this->createdAt,
            $now,
        );
    }
}
