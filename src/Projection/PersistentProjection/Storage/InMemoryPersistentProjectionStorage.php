<?php
declare(strict_types=1);

namespace Wwwision\DCBLibrary\Projection\PersistentProjection\Storage;

use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Criteria\AndCriteria;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Criteria\NegateCriteria;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Criteria\OrCriteria;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Criteria\PersistentProjectionFilterCriteria;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Criteria\ValueContains;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Criteria\ValueEndsWith;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Criteria\ValueEquals;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Criteria\ValueGreaterThan;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Criteria\ValueGreaterThanOrEqual;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Criteria\ValueIsNull;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Criteria\ValueLessThan;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Criteria\ValueLessThanOrEqual;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Criteria\ValueStartsWith;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Ordering\OrderingDirection;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Ordering\OrderingField;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Ordering\TimestampField;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\PersistentProjectionFilter;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\SerializedPersistentProjectionFilterResult;

final class InMemoryPersistentProjectionStorage implements PersistentProjectionStorage
{

    /**
     * @var array<string, SerializedPersistentProjectionStateEnvelope>
     */
    private array $stateEnvelopesByPartitionKey = [];

    public function loadStateEnvelope(string $partitionKey): SerializedPersistentProjectionStateEnvelope|null
    {
        return $this->stateEnvelopesByPartitionKey[$partitionKey] ?? null;
    }

    public function saveStateEnvelope(SerializedPersistentProjectionStateEnvelope $stateEnvelope): void
    {
        $this->stateEnvelopesByPartitionKey[$stateEnvelope->partitionKey] = $stateEnvelope;
    }

    public function removeStateEnvelope(string $partitionKey): void
    {
        unset($this->stateEnvelopesByPartitionKey[$partitionKey]);
    }

    public function find(PersistentProjectionFilter $filter): SerializedPersistentProjectionFilterResult
    {
        $filteredResults = array_filter($this->stateEnvelopesByPartitionKey, static fn (SerializedPersistentProjectionStateEnvelope $stateEnvelope) => self::matchesFilter($stateEnvelope, $filter));
        if ($filteredResults === []) {
            return SerializedPersistentProjectionFilterResult::createEmpty();
        }
        $totalCount = count($filteredResults);
        if ($filter->ordering !== null) {
            usort($filteredResults, function (SerializedPersistentProjectionStateEnvelope $e1, SerializedPersistentProjectionStateEnvelope $e2) use ($filter) {
                foreach ($filter->ordering as $orderingField) {
                    $comparison = self::compareByField($e1, $e2, $orderingField);
                    if ($comparison !== 0) {
                        return $comparison;
                    }
                }
                return 0;
            });
        }
        if ($filter->pagination !== null) {
            $filteredResults = array_slice($filteredResults, $filter->pagination->offset, $filter->pagination->limit);
        }
        return SerializedPersistentProjectionFilterResult::create($totalCount, $filteredResults);
    }

    public function flush(): void
    {
        $this->stateEnvelopesByPartitionKey = [];
    }

    private static function matchesFilter(SerializedPersistentProjectionStateEnvelope $stateEnvelope, PersistentProjectionFilter $filter): bool
    {
        if ($filter->searchTerm !== null && !str_contains(strtolower($stateEnvelope->serializedState), strtolower($filter->searchTerm->term))) {
            return false;
        }
        if ($filter->criteria !== null && !self::matchesFilterCriteria($stateEnvelope->serializedState, $filter->criteria)) {
            return false;
        }
        return true;
    }

    private static function matchesFilterCriteria(string $serializedState, PersistentProjectionFilterCriteria $criteria): bool
    {
        if ($criteria instanceof AndCriteria) {
            return self::matchesFilterCriteria($serializedState, $criteria->criteria1) && self::matchesFilterCriteria($serializedState, $criteria->criteria2);
        }
        if ($criteria instanceof OrCriteria) {
            return self::matchesFilterCriteria($serializedState, $criteria->criteria1) || self::matchesFilterCriteria($serializedState, $criteria->criteria2);
        }
        if ($criteria instanceof NegateCriteria) {
            return !self::matchesFilterCriteria($serializedState, $criteria->criteria);
        }
        $state = self::deserializeState($serializedState);
        return match ($criteria::class) {
            ValueContains::class => self::valueContains($state, $criteria),
            ValueEndsWith::class => self::valueEndsWith($state, $criteria),
            ValueStartsWith::class => self::valueStartsWith($state, $criteria),
            ValueEquals::class => self::valueEquals($state, $criteria),
            ValueGreaterThan::class => isset($state[$criteria->propertyName]) && $state[$criteria->propertyName] > $criteria->value,
            ValueGreaterThanOrEqual::class => isset($state[$criteria->propertyName]) && $state[$criteria->propertyName] >= $criteria->value,
            ValueLessThan::class => isset($state[$criteria->propertyName]) && $state[$criteria->propertyName] < $criteria->value,
            ValueLessThanOrEqual::class => isset($state[$criteria->propertyName]) && $state[$criteria->propertyName] <= $criteria->value,
            ValueIsNull::class => !isset($state[$criteria->propertyName]),
            default => throw new \InvalidArgumentException(sprintf('Invalid/unsupported value criteria "%s"', get_debug_type($criteria)), 1759922446),
        };
    }

    /**
     * @param array<mixed> $state
     */
    private static function valueContains(array $state, ValueContains $criteria): bool
    {
        $value = $state[$criteria->propertyName] ?? null;
        if (!is_string($value)) {
            return false;
        }
        if ($criteria->caseSensitive) {
            return str_contains($value, $criteria->value);
        }
        return str_contains(mb_strtolower($value), mb_strtolower($criteria->value));
    }

    /**
     * @param array<mixed> $state
     */
    private static function valueStartsWith(array $state, ValueStartsWith $criteria): bool
    {
        $value = $state[$criteria->propertyName] ?? null;
        if (!is_string($value)) {
            return false;
        }
        if ($criteria->caseSensitive) {
            return str_starts_with($value, $criteria->value);
        }
        return str_starts_with(mb_strtolower($value), mb_strtolower($criteria->value));
    }

    /**
     * @param array<mixed> $state
     */
    private static function valueEndsWith(array $state, ValueEndsWith $criteria): bool
    {
        $value = $state[$criteria->propertyName] ?? null;
        if (!is_string($value)) {
            return false;
        }
        if ($criteria->caseSensitive) {
            return str_ends_with($value, $criteria->value);
        }
        return str_ends_with(mb_strtolower($value), mb_strtolower($criteria->value));
    }

    /**
     * @param array<mixed> $state
     */
    private static function valueEquals(array $state, ValueEquals $criteria): bool
    {
        $value = $state[$criteria->propertyName] ?? null;
        if ($criteria->caseSensitive) {
            return $value === $criteria->value;
        }
        return (is_string($value) ? mb_strtolower($value) : $value) === (is_string($criteria->value) ? mb_strtolower($criteria->value) : $criteria->value);
    }

    private static function compareByField(SerializedPersistentProjectionStateEnvelope $stateEnvelope1, SerializedPersistentProjectionStateEnvelope $stateEnvelope2, OrderingField $orderingField): int
    {
        $field = $orderingField->field;
        if (is_string($field)) {
            $state1 = self::deserializeState($stateEnvelope1->serializedState);
            $state2 = self::deserializeState($stateEnvelope2->serializedState);
            $value1 = $state1[$field] ?? null;
            $value2 = $state2[$field] ?? null;
        } elseif ($field === TimestampField::CREATED) {
            $value1 = $stateEnvelope1->createdAt;
            $value2 = $stateEnvelope2->createdAt;
        } elseif ($field === TimestampField::LAST_UPDATED_AT) {
            $value1 = $stateEnvelope1->lastUpdatedAt;
            $value2 = $stateEnvelope2->lastUpdatedAt;
        } else {
            throw new InvalidArgumentException(sprintf('Invalid field type %s', get_debug_type($field)), 1759929963);
        }
        $comparison = $value1 <=> $value2;
        return $orderingField->direction === OrderingDirection::DESCENDING ? -$comparison : $comparison;
    }

    /**
     * @return array<string, mixed>
     */
    private static function deserializeState(string $serializedState): array
    {
        try {
            $state = json_decode($serializedState, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException(sprintf('Failed to JSON decode state: %s', $e->getMessage()), 1759922655, $e);
        }
        if (!is_array($state)) {
            throw new RuntimeException(sprintf('Expected JSON-decoded state to be an array, got %s', get_debug_type($state)), 1759929640);
        }
        /** @var array<string, mixed> $state */
        return $state;
    }
}
