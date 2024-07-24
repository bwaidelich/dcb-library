<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Subscription;

use Wwwision\Types\Attributes\StringBased;
use function Wwwision\Types\instantiate;

#[StringBased(minLength: 1, maxLength: 150)]
final class SubscriptionId
{

    private function __construct(public readonly string $value)
    {
    }

    public static function fromString(string $value): self
    {
        return instantiate(self::class, $value);
    }

    public function equals(self $other): bool
    {
        return $other->value === $this->value;
    }

}