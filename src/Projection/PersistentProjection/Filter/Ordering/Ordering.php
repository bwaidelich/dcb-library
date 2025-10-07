<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Ordering;

use InvalidArgumentException;

/**
 * @implements \IteratorAggregate<OrderingField>
 */
final class Ordering implements \IteratorAggregate, \JsonSerializable
{
    /**
     * @var OrderingField[]
     */
    private array $fields;

    private function __construct(OrderingField ...$fields)
    {
        if ($fields === []) {
            throw new \InvalidArgumentException('Ordering must contain at least one ordering field', 1759674836);
        }
        $this->fields = $fields;
    }

    /**
     * @param array<OrderingField|array<mixed>|mixed> $array
     */
    public static function fromArray(array $array): self
    {
        $fields = [];
        foreach ($array as $field) {
            if ($field instanceof OrderingField) {
                $fields[] = $field;
            } elseif (is_array($field)) {
                $fields[] = OrderingField::fromArray($field);
            } else {
                throw new InvalidArgumentException(sprintf('Invalid ordering field: %s', get_debug_type($field)), 1759756910);
            }
        }
        return new self(...$fields);
    }

    public static function byProperty(string $propertyName, OrderingDirection $direction): self
    {
        return new self(OrderingField::byProperty($propertyName, $direction));
    }

    public static function byTimestampField(TimestampField $timestampField, OrderingDirection $direction): self
    {
        return new self(OrderingField::byTimestampField($timestampField, $direction));
    }

    public function andByProperty(string $propertyName, OrderingDirection $direction): self
    {
        return new self(...[...$this->fields, OrderingField::byProperty($propertyName, $direction)]);
    }

    public function andByTimestampField(TimestampField $timestampField, OrderingDirection $direction): self
    {
        return new self(...[...$this->fields, OrderingField::byTimestampField($timestampField, $direction)]);
    }

    public function getIterator(): \Traversable
    {
        yield from $this->fields;
    }

    /**
     * @return OrderingField[]
     */
    public function jsonSerialize(): array
    {
        return $this->fields;
    }
}
