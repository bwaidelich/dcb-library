<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Subscription\Engine;

use Throwable;
use Wwwision\DCBLibrary\Subscription\SubscriptionId;

final class Error
{
    public function __construct(
        public readonly SubscriptionId $subscriptionId,
        public readonly string $message,
        public readonly Throwable $throwable,
    ) {
    }
}
