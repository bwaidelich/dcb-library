<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Pagination;

use Webmozart\Assert\Assert;

final readonly class Pagination
{
    /**
     * @param positive-int $limit
     */
    private function __construct(
        public int $limit,
        public int $offset,
    ) {
        Assert::positiveInteger($this->limit, 'Limit must not be less than 1, given: %d');
        Assert::natural($this->offset, 'Offset must not be a negative number, given: %d');
    }

    /**
     * @param positive-int $limit
     */
    public static function fromLimitAndOffset(int $limit, int $offset): self
    {
        return new self($limit, $offset);
    }

    /**
     * @param array<mixed> $array
     */
    public static function fromArray(array $array): self
    {
        $limit = null;
        $offset = null;
        if (isset($array['limit'])) {
            Assert::numeric($array['limit'], 'Limit must be an number or a numeric string, given: %s');
            $limit = (int)$array['limit'];
            Assert::positiveInteger($limit, 'Limit must not be less than 1, given: %d');
            unset($array['limit']);
        }
        if (isset($array['offset'])) {
            Assert::numeric($array['offset'], 'Offset must be an number or a numeric string, given: %s');
            $offset = (int)$array['offset'];
            unset($array['offset']);
        }
        if ($array !== []) {
            throw new \InvalidArgumentException(sprintf('Unsupported pagination array key%s: "%s"', count($array) === 1 ? '' : 's', implode('", "', array_keys($array))), 1680259558);
        }
        $limit = $limit ?? PHP_INT_MAX;
        $offset = $offset ?? 0;
        return new self($limit, $offset);
    }
}
