<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Subscription\RetryStrategy;

use Wwwision\DCBLibrary\Subscription\Subscription;

final class NoRetryStrategy implements RetryStrategy
{
    public function shouldRetry(Subscription $subscription): bool
    {
        return false;
    }
}