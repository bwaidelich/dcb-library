<?php
declare(strict_types=1);

namespace Wwwision\DCBLibrary\Projection\PersistentProjection\Storage;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
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
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Criteria\ValueLessThan;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Criteria\ValueLessThanOrEqual;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Criteria\ValueStartsWith;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Ordering\Ordering;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Ordering\OrderingDirection;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Ordering\TimestampField;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Pagination\Pagination;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\PersistentProjectionFilter;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\SearchTerm\SearchTerm;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\SerializedPersistentProjectionFilterResult;
use Wwwision\DCBLibrary\ProvidesSetup;

final class DoctrinePersistentProjectionStorage implements PersistentProjectionStorage, ProvidesSetup
{

    private int $uniqueParamCount = 0;

    public function __construct(
        private Connection $connection,
        private string $tableName,
    ) {
    }

    public function loadStateEnvelope(string $partitionKey): SerializedPersistentProjectionStateEnvelope|null
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM ' . $this->connection->quoteIdentifier($this->tableName) . ' WHERE partition_key = :partitionKey', [
            'partitionKey' => $partitionKey,
        ]);
        if ($row === false) {
            return null;
        }
        return $this->convertRow($row);
    }

    /**
     * @param array<mixed> $row
     */
    private function convertRow(array $row): SerializedPersistentProjectionStateEnvelope
    {
        if (!is_string($row['partition_key'])) {
            throw new RuntimeException('Missing/invalid value for column "partition_key"', 1759920465);
        }
        if (!is_string($row['state'])) {
            throw new RuntimeException('Missing/invalid value for column "state"', 1759920466);
        }
        if (!is_string($row['created_at'])) {
            throw new RuntimeException('Missing/invalid value for column "created_at"', 1759920467);
        }
        $createdAt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $row['created_at']);
        if ($createdAt === false) {
            throw new RuntimeException(sprintf('Failed to convert "%s" to DateTime object', $row['created_at']), 1759920528);
        }
        if (!is_string($row['last_updated_at'])) {
            throw new RuntimeException('Missing/invalid value for column "last_updated_at"', 1759920529);
        }
        $lastUpdatedAt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $row['last_updated_at']);
        if ($lastUpdatedAt === false) {
            throw new RuntimeException(sprintf('Failed to convert "%s" to DateTime object', $row['last_updated_at']), 1759920530);
        }
        return new SerializedPersistentProjectionStateEnvelope(
            $row['partition_key'],
            $row['state'],
            $createdAt,
            $lastUpdatedAt,
        );
    }

    public function saveStateEnvelope(SerializedPersistentProjectionStateEnvelope $state): void
    {
        $this->connection->executeStatement('INSERT INTO ' . $this->connection->quoteIdentifier($this->tableName) . ' (partition_key, state, created_at, last_updated_at) VALUES (:partitionKey, :state, :createdAt, :lastUpdatedAt) ON DUPLICATE KEY UPDATE state = :state, last_updated_at = :lastUpdatedAt', [
            'partitionKey' => $state->partitionKey,
            'state' => $state->serializedState,
            'createdAt' => $state->createdAt->format('Y-m-d H:i:s'),
            'lastUpdatedAt' => $state->lastUpdatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function find(PersistentProjectionFilter $filter): SerializedPersistentProjectionFilterResult
    {
        $queryBuilder = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->tableName);
        if ($filter->criteria !== null) {
            $this->addCriteriaConstraints($queryBuilder, $filter->criteria);
        }
        if ($filter->searchTerm !== null) {
            $this->addSearchTermConstraints($queryBuilder, $filter->searchTerm);
        }
        $totalCountResult = $queryBuilder->executeQuery()->fetchOne();
        if (!is_numeric($totalCountResult)) {
            throw new RuntimeException(sprintf('Expected total count result to be numeric, got: %s', get_debug_type($totalCountResult)), 1759920594);
        }
        $totalCount = (int)$totalCountResult;
        if ($totalCount === 0) {
            return SerializedPersistentProjectionFilterResult::createEmpty();
        }
        $queryBuilder->select('*');
        if ($filter->ordering !== null) {
            $this->applyOrdering($queryBuilder, $filter->ordering);
        }
        if ($filter->pagination !== null) {
            $this->applyPagination($queryBuilder, $filter->pagination);
        }
        return SerializedPersistentProjectionFilterResult::create($totalCount, array_map($this->convertRow(...), $queryBuilder->executeQuery()->fetchAllAssociative()));
    }

    private function addCriteriaConstraints(QueryBuilder $queryBuilder, PersistentProjectionFilterCriteria $criteria): void
    {
        $queryBuilder->andWhere($this->criteriaConstraints($queryBuilder, $criteria));
    }

    private function criteriaConstraints(QueryBuilder $queryBuilder, PersistentProjectionFilterCriteria $criteria): string
    {
        return match ($criteria::class) {
            AndCriteria::class => (string)$queryBuilder->expr()->and($this->criteriaConstraints($queryBuilder, $criteria->criteria1), $this->criteriaConstraints($queryBuilder, $criteria->criteria2)),
            NegateCriteria::class => 'NOT (' . $this->criteriaConstraints($queryBuilder, $criteria->criteria) . ')',
            OrCriteria::class => (string)$queryBuilder->expr()->or($this->criteriaConstraints($queryBuilder, $criteria->criteria1), $this->criteriaConstraints($queryBuilder, $criteria->criteria2)),
            ValueContains::class => $this->searchValueStatement($queryBuilder, $criteria->propertyName, '%' . $criteria->value . '%', $criteria->caseSensitive),
            ValueEndsWith::class => $this->searchValueStatement($queryBuilder, $criteria->propertyName, '%' . $criteria->value, $criteria->caseSensitive),
            ValueEquals::class => is_string($criteria->value) ? $this->searchValueStatement($queryBuilder, $criteria->propertyName, $criteria->value, $criteria->caseSensitive) : $this->compareValueStatement($queryBuilder, $criteria->propertyName, $criteria->value, '='),
            ValueGreaterThan::class => $this->compareValueStatement($queryBuilder, $criteria->propertyName, $criteria->value, '>'),
            ValueGreaterThanOrEqual::class => $this->compareValueStatement($queryBuilder, $criteria->propertyName, $criteria->value, '>='),
            ValueLessThan::class => $this->compareValueStatement($queryBuilder, $criteria->propertyName, $criteria->value, '<'),
            ValueLessThanOrEqual::class => $this->compareValueStatement($queryBuilder, $criteria->propertyName, $criteria->value, '<='),
            ValueStartsWith::class => $this->searchValueStatement($queryBuilder, $criteria->propertyName, $criteria->value . '%', $criteria->caseSensitive),
            default => throw new \InvalidArgumentException(sprintf('Invalid/unsupported filter criteria "%s"', get_debug_type($criteria)), 1679561062),
        };
    }

    private function addSearchTermConstraints(QueryBuilder $queryBuilder, SearchTerm $searchTerm): void
    {
        $queryBuilder->andWhere('JSON_SEARCH(state, "one", :searchTermPattern COLLATE utf8mb4_unicode_520_ci, NULL, "$.*") IS NOT NULL')->setParameter('searchTermPattern', '%' . $searchTerm->term . '%');
    }

    private function searchValueStatement(QueryBuilder $queryBuilder, string $propertyName, string $value, bool $caseSensitive): string
    {
        try {
            $escapedPropertyName = addslashes(json_encode($propertyName, JSON_THROW_ON_ERROR));
        } catch (\JsonException $e) {
            throw new \RuntimeException(sprintf('Failed to escape property name: %s', $e->getMessage()), 1679394579, $e);
        }

        $paramName = $this->createUniqueParameterName();
        $queryBuilder->setParameter($paramName, $value);

        if ($caseSensitive) {
            return 'JSON_SEARCH(state COLLATE utf8mb4_bin, \'one\', :' . $paramName . ' COLLATE utf8mb4_bin, NULL, \'$.' . $escapedPropertyName . '\') IS NOT NULL';
        }

        return 'JSON_SEARCH(state COLLATE utf8mb4_unicode_520_ci, \'one\', :' . $paramName . ' COLLATE utf8mb4_unicode_520_ci, NULL, \'$.' . $escapedPropertyName . '\') IS NOT NULL';
    }

    private function compareValueStatement(QueryBuilder $queryBuilder, string $propertyName, string|int|float|bool $value, string $operator): string
    {
        if (is_bool($value)) {
            return $this->extractPropertyValue($propertyName) . ' ' . $operator . ($value ? 'true' : 'false');
        }

        if (is_int($value)) {
            return $this->extractPropertyValue($propertyName) . ' ' . $operator . $value;
        }

        if (is_float($value)) {
            return $this->extractPropertyValue($propertyName) . ' ' . $operator . $value;
        }

        $paramName = $this->createUniqueParameterName();
        $queryBuilder->setParameter($paramName, $value);
        return $this->extractPropertyValue($propertyName) . ' ' . $operator . ' :' . $paramName;
    }

    private function createUniqueParameterName(): string
    {
        return 'param_' . (++$this->uniqueParamCount);
    }

    private function applyOrdering(QueryBuilder $queryBuilder, Ordering $ordering): void
    {
        foreach ($ordering as $orderingField) {
            $order = match ($orderingField->direction) {
                OrderingDirection::ASCENDING => 'ASC',
                OrderingDirection::DESCENDING => 'DESC',
            };
            if ($orderingField->field instanceof TimestampField) {
                $timestampColumnName = match ($orderingField->field) {
                    TimestampField::CREATED => 'created_at',
                    TimestampField::LAST_UPDATED_AT => 'last_updated_at',
                };
                $queryBuilder->addOrderBy($timestampColumnName, $order);
            } else {
                $queryBuilder->addOrderBy($this->extractPropertyValue($orderingField->field), $order);
            }
        }
    }

    private function applyPagination(QueryBuilder $queryBuilder, Pagination $pagination): void
    {
        $queryBuilder->setMaxResults($pagination->limit)->setFirstResult($pagination->offset);
    }

    private function extractPropertyValue(string $propertyName): string
    {
        try {
            $escapedPropertyName = addslashes(json_encode($propertyName, JSON_THROW_ON_ERROR));
        } catch (\JsonException $e) {
            throw new \RuntimeException(sprintf('Failed to escape property name: %s', $e->getMessage()), 1759675406, $e);
        }

        return 'JSON_EXTRACT(state, \'$.' . $escapedPropertyName . '\') COLLATE utf8mb4_unicode_520_ci';
    }

    public function setup(): void
    {
        try {
            $statements = $this->determineRequiredSqlStatements();
        } catch (DbalException $e) {
            throw new RuntimeException(sprintf('Failed to determine required SQL statements: %s', $e->getMessage()), 1759751588, $e);
        }
        foreach ($statements as $statement) {
            $this->connection->executeStatement($statement);
        }
    }

    /**
     * @return array<string>
     * @throws DbalException
     */
    private function determineRequiredSqlStatements(): array
    {
        $schemaManager = $this->connection->createSchemaManager();
        $platform = $this->connection->getDatabasePlatform();
        $isSQLite = $platform instanceof SqlitePlatform;
        $isPostgreSQL = $platform instanceof PostgreSQLPlatform;
        $schemaConfig = $schemaManager->createSchemaConfig();
        $schemaConfig->setDefaultTableOptions([
            'charset' => 'utf8mb4'
        ]);

        $tableSchema = new Table($this->tableName, [
            (new Column('partition_key', Type::getType(Types::STRING)))
                ->setLength(255)
                ->setPlatformOptions($isSQLite ? [] : ['charset' => 'ascii']),
            (new Column('state', Type::getType(Types::JSON)))
                ->setNotnull(false)
                ->setPlatformOptions($isPostgreSQL ? ['jsonb' => true] : []),
            (new Column('created_at', Type::getType(Types::DATETIME_IMMUTABLE))),
            (new Column('last_updated_at', Type::getType(Types::DATETIME_IMMUTABLE))),
        ]);
        $tableSchema->setPrimaryKey(['partition_key']);
        if (!$schemaManager->tablesExist([$this->tableName])) {
            return $platform->getCreateTableSQL($tableSchema);
        }
        $toSchema = new Schema(
            [$tableSchema],
            [],
            $schemaConfig,
        );
        $fromTableSchemas = [];
        foreach ($toSchema->getTables() as $tableSchema) {
            if ($schemaManager->tablesExist([$tableSchema->getName()])) {
                $fromTableSchemas[] = $schemaManager->introspectTable($tableSchema->getName());
            }
        }
        $fromSchema = new Schema($fromTableSchemas, [], $schemaManager->createSchemaConfig());
        $schemaDiff = (new Comparator())->compareSchemas($fromSchema, $toSchema);
        return $platform->getAlterSchemaSQL($schemaDiff);
    }

    public function flush(): void
    {
        $this->connection->executeStatement('TRUNCATE TABLE ' . $this->connection->quoteIdentifier($this->tableName));
    }
}
