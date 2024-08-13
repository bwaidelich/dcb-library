<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Subscription\Store;

use Wwwision\DCBLibrary\Subscription\Status;
use Wwwision\DCBLibrary\Subscription\SubscriptionGroups;
use Wwwision\DCBLibrary\Subscription\SubscriptionId;
use Wwwision\DCBLibrary\Subscription\SubscriptionIds;

final class SubscriptionCriteria
{
    /**
     * @param list<Status>|null $status
     */
    private function __construct(
        public readonly SubscriptionIds|null $ids,
        public readonly SubscriptionGroups|null $groups,
        public readonly array|null $status,
    ) {
    }

    /**
     * @param SubscriptionIds|array<string|SubscriptionId>|null $ids
     * @param SubscriptionGroups|list<string>|null $groups
     * @param list<Status>|null $status
     */
    public static function create(
        SubscriptionIds|array $ids = null,
        SubscriptionGroups|array $groups = null,
        array $status = null,
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
            $status,
        );
    }

    public static function noConstraints(): self
    {
        return new self(
            ids: null,
            groups: null,
            status: null,
        );
    }

    public static function withStatus(Status $status): self
    {
        return new self(
            ids: null,
            groups: null,
            status: [$status],
        );
    }
}
