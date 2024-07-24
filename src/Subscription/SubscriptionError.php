<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Subscription;

use Throwable;

/**
 * @phpstan-type Context = array{class: class-string, message: string, code: int|string, file: string, line: int, trace: list<Trace>}
 */
final class SubscriptionError
{
    /** @param list<Context>|null $errorContext */
    public function __construct(
        public readonly string $errorMessage,
        public readonly Status $previousStatus,
        //public readonly array|null $errorContext = null,
    ) {
    }

    public static function fromThrowable(Status $status, Throwable $error): self
    {
        return new self($error->getMessage(), $status);//, ThrowableToErrorContextTransformer::transform($error));
    }
}
