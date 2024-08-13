<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Subscription;

use Throwable;

final class SubscriptionError
{
    public function __construct(
        public readonly string $errorMessage,
        public readonly Status $previousStatus,
        public readonly string|null $errorTrace = null,
    ) {
    }

    public static function fromThrowable(Status $status, Throwable $error): self
    {
        return new self($error->getMessage(), $status, $error->getTraceAsString());
    }
}
