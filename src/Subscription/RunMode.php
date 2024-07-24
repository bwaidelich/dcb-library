<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Subscription;

enum RunMode
{
    case FROM_BEGINNING;
    case FROM_NOW;
    case ONCE;
}
