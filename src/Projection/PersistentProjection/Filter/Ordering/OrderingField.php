<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Ordering;

use InvalidArgumentException;
use JsonSerializable;
use ValueError;

final readonly class OrderingField implements JsonSerializable
{
    private function __construct(
        public string|TimestampField $field,
        public OrderingDirection $direction,
    ) {
        if ($field === '') {
            throw new InvalidArgumentException('Field must not be empty');
        }
    }

    public static function byProperty(string $propertyName, OrderingDirection $direction): self
    {
        return new self($propertyName, $direction);
    }

    public static function byTimestampField(TimestampField $timestampField, OrderingDirection $direction): self
    {
        return new self($timestampField, $direction);
    }

    /**
     * @param array<mixed> $array
     */
    public static function fromArray(array $array): self
    {
        if (!in_array($array['type'], ['propertyName', 'timestampField'], true)) {
            throw new InvalidArgumentException('Invalid/missing "type"', 1759920113);
        }
        if (!is_string($array['field'])) {
            throw new InvalidArgumentException('Invalid/missing "field"', 1759920169);
        }
        $type = $array['type'];
        unset($array['type']);
        if ($type === 'propertyName') {
            $field = $array['field'];
        } else {
            try {
                $field = TimestampField::from($array['field']);
            } catch (ValueError $e) {
                throw new InvalidArgumentException(sprintf('Invalid element "field" value: %s', $e->getMessage()), 1759674846, $e);
            }
        }
        unset($array['field']);
        if (!is_string($array['direction'])) {
            throw new InvalidArgumentException('Invalid/missing "direction"', 1759920213);
        }
        try {
            $direction = OrderingDirection::from($array['direction']);
        } catch (ValueError $e) {
            throw new InvalidArgumentException(sprintf('Invalid element "direction" value: %s', $e->getMessage()), 1759674849, $e);
        }
        unset($array['direction']);
        if ($array !== []) {
            throw new InvalidArgumentException(sprintf('Unsupported OrderingField array key%s: "%s"', count($array) === 1 ? '' : 's', implode('", "', array_keys($array))), 1759674852);
        }
        return new self($field, $direction);
    }

    /**
     * @return array{type: 'propertyName'|'timestampField', field: mixed, direction: 'ASCENDING'|'DESCENDING'}
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->field instanceof TimestampField ? 'timestampField' : 'propertyName',
            'field' => $this->field instanceof TimestampField ? $this->field->value : $this->field,
            'direction' => $this->direction->value,
        ];
    }
}
