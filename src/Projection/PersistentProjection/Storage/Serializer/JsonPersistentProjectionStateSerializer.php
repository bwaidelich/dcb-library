<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Projection\PersistentProjection\Storage\Serializer;

use JsonException;
use RuntimeException;

/**
 * @template S extends object
 * @implements PersistentProjectionStateSerializer<S>
 */
final readonly class JsonPersistentProjectionStateSerializer implements PersistentProjectionStateSerializer
{

    /**
     * @param class-string<S> $stateClassName
     */
    public function __construct(
        private string $stateClassName,
    ) {
    }

    public function serialize($state): string
    {
        try {
            return json_encode($state, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException(sprintf('Failed to JSON encode instance of %s: %s', $this->stateClassName, $e->getMessage()), 1759830312, $e);
        }
    }

    public function unserialize(string $value): mixed
    {
        try {
            $stateDecoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException(sprintf('Failed to decode JSON into instance of %s: %s', $this->stateClassName, $e->getMessage()), 1759830313, $e);
        }
        return new $this->stateClassName(...$stateDecoded);
    }
}
