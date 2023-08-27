<?php
declare(strict_types=1);

namespace Wwwision\DCBLibrary;

use Closure;
use Wwwision\DCBEventStore\Types\EventEnvelope;

/**
 * Options for {@see CIRSEventStore::catchUp()}
 */
final class CatchUpOptions
{
    /**
     * @param int $batchSize Number of events to iterate before the state is persisted
     * @param Closure(EventEnvelope $eventEnvelope): void|null $progressCallback If specified the given closure will be invoked for every event with the current {@see EventEnvelope} passed as argument
     */
    private function __construct(
        public readonly int $batchSize,
        public readonly ?Closure $progressCallback,
    ) {
    }

    /**
     * Creates an instance for the specified options
     *
     * Note: The signature of this method might be extended in the future, so it should always be used with named arguments
     * @see https://www.php.net/manual/en/functions.arguments.php#functions.named-arguments
     */
    public static function create(
        int $batchSize = 1,
        Closure|null $progressCallback = null,
    ): self {
        return new self($batchSize, $progressCallback);
    }


    /**
     * Returns a new instance with the specified additional options
     *
     * Note: The signature of this method might be extended in the future, so it should always be used with named arguments
     * @see https://www.php.net/manual/en/functions.arguments.php#functions.named-arguments
     */
    public function with(
        int|null $batchSize = null,
        Closure|null $progressCallback = null,
    ): self {
        return self::create(
            $batchSize ?? $this->batchSize,
            $progressCallback ?? $this->progressCallback,
        );
    }
}
