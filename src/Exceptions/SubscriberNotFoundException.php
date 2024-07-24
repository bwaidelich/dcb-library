<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Exceptions;

use RuntimeException;

use Wwwision\DCBLibrary\Subscription\SubscriptionId;
use function sprintf;

final class SubscriberNotFoundException extends RuntimeException
{
    public static function forSubscriptionId(SubscriptionId $subscriptionId): self
    {
        return new self(
            sprintf(
                'Subscriber with the subscription id "%s" not found.',
                $subscriptionId->value,
            )
        );
    }
}
