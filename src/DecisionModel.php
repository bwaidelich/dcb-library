<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary;

use Wwwision\DCBEventStore\Types\ExpectedHighestSequenceNumber;
use Wwwision\DCBEventStore\Types\StreamQuery\StreamQuery;

/**
 * @template S
 */
final readonly class DecisionModel
{

    /**
     * @param S $state
     */
    public function __construct(
        public StreamQuery $query,
        public ExpectedHighestSequenceNumber $expectedHighestSequenceNumber,
        public mixed $state,
    ) {
    }
}
