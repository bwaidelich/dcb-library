<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Ordering;

/**
 * Sort order of a given {@see OrderingField}
 */
enum OrderingDirection: string
{
    case ASCENDING = 'ASCENDING';
    case DESCENDING = 'DESCENDING';
}
