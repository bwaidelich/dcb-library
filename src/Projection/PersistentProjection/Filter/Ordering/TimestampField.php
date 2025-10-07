<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Ordering;

/**
 * One of the node {@see Timestamps}
 *
 * @see OrderingField
 */
enum TimestampField: string
{
    case CREATED = 'CREATED';
    case LAST_UPDATED_AT = 'LAST_UPDATED_AT';
}
