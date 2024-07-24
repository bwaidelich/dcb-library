<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Subscription\Subscriber;

use Wwwision\DCBLibrary\EventHandling\EventHandler;
use Wwwision\DCBLibrary\Subscription\RunMode;
use Wwwision\DCBLibrary\Subscription\SubscriptionGroup;
use Wwwision\DCBLibrary\Subscription\SubscriptionId;

final class Subscriber
{

    public function __construct(
        public readonly SubscriptionId $id,
        public readonly SubscriptionGroup $group,
        public readonly RunMode $runMode,
        public readonly EventHandler $handler,
    ) {
    }
}
