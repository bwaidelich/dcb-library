<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Subscription\Engine;

use Wwwision\DCBLibrary\Subscription\SubscriptionGroups;
use Wwwision\DCBLibrary\Subscription\SubscriptionId;
use Wwwision\DCBLibrary\Subscription\SubscriptionIds;

final class SubscriptionEngineCriteria
{
    private function __construct(
        public readonly SubscriptionIds|null $ids,
        public readonly SubscriptionGroups|null $groups,
    ) {
    }

    /**
     * @param SubscriptionIds|array<string|SubscriptionId>|null $ids
     * @param SubscriptionGroups|list<string>|null $groups
     */
    public static function create(
        SubscriptionIds|array|null $ids = null,
        SubscriptionGroups|array|null $groups = null,
    ): self {
        if (is_array($ids)) {
            $ids = SubscriptionIds::fromArray($ids);
        }
        if (is_array($groups)) {
            $groups = SubscriptionGroups::fromArray($groups);
        }
        return new self(
            $ids,
            $groups,
        );
    }

    public static function noConstraints(): self
    {
        return new self(
            ids: null,
            groups: null,
        );
    }
}
