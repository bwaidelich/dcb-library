<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Subscription\Engine;

final class ProcessedResult
{
    /** @param list<Error> $errors */
    public function __construct(
        public readonly int $processedMessages,
        public readonly bool $finished = false,
        public readonly array $errors = [],
    ) {
    }
}
