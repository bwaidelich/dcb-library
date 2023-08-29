<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary;

interface CatchUpQueue
{
    public function run(): void;
}
