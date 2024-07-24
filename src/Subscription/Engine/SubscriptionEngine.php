<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Subscription\Engine;

interface SubscriptionEngine
{
    public function setup(
        SubscriptionEngineCriteria $criteria = null,
        int $limit = null,
    ): void;

    public function run(
        SubscriptionEngineCriteria $criteria = null,
        int $limit = null,
    ): void;
}
