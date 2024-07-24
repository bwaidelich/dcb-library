<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Subscription\Store;

use Closure;
use Wwwision\DCBLibrary\Subscription\Subscription;
use Wwwision\DCBLibrary\Subscription\SubscriptionId;
use Wwwision\DCBLibrary\Subscription\Subscriptions;

interface SubscriptionStore
{

    public function findOneById(SubscriptionId $subscriptionId): ?Subscription;

    public function findByCriteria(SubscriptionCriteria $criteria): Subscriptions;

    public function add(Subscription $subscription): void;

    /**
     * @param Closure(Subscription): Subscription $updater
     */
    public function update(SubscriptionId $subscriptionId, Closure $updater): void;
}
