<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Subscription;

enum Status
{
    case NEW;
    case BOOTING;
    case ACTIVE;
    case PAUSED;
    case FINISHED;
    case DETACHED;
    case ERROR;
}
