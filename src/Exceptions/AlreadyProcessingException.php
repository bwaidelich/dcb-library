<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Exceptions;

use RuntimeException;

final class AlreadyProcessingException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Subscription engine is already processing');
    }
}
