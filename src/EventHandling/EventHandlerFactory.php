<?php
declare(strict_types=1);

namespace Wwwision\DCBLibrary\EventHandling;

interface EventHandlerFactory
{
    public function build(): EventHandler;
}
