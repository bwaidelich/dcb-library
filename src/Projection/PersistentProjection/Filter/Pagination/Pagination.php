<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Pagination;

use InvalidArgumentException;

final readonly class Pagination
{
    private function __construct(
        public int $limit,
        public int $offset,
    ) {
        if ($limit < 1) {
            throw new InvalidArgumentException(sprintf('Limit must not be less than 1, given: %d', $limit), 1759920247);
        }
        if ($offset < 1) {
            throw new InvalidArgumentException(sprintf('Offset must not be a negative number, given: %d', $this->offset), 1759920248);
        }
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
            if (!is_numeric($array['limit'])) {
                throw new InvalidArgumentException(sprintf('Limit must be an number or a numeric string, given: %s', get_debug_type($array['limit'])), 1759920304);
            }
            $limit = (int)$array['limit'];
            unset($array['limit']);
        }
        if (isset($array['offset'])) {
            if (!is_numeric($array['offset'])) {
                throw new InvalidArgumentException(sprintf('Offset must be an number or a numeric string, given: %s', get_debug_type($array['offset'])), 1759920306);
            }
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
