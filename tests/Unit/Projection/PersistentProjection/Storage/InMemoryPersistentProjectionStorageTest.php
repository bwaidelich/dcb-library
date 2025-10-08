<?php
declare(strict_types=1);

namespace Wwwision\DCBLibrary\Tests\Unit\Projection\PersistentProjection\Storage;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Criteria\OrCriteria;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Criteria\ValueEquals;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Criteria\ValueStartsWith;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Ordering\Ordering;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Ordering\OrderingDirection;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Ordering\OrderingField;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Ordering\TimestampField;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\Pagination\Pagination;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\PersistentProjectionFilter;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Filter\SearchTerm\SearchTerm;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Storage\InMemoryPersistentProjectionStorage;
use Wwwision\DCBLibrary\Projection\PersistentProjection\Storage\SerializedPersistentProjectionStateEnvelope;

#[CoversClass(InMemoryPersistentProjectionStorage::class)]
final class InMemoryPersistentProjectionStorageTest extends TestCase
{
    private InMemoryPersistentProjectionStorage $storage;

    protected function setUp(): void
    {
        $this->storage = new InMemoryPersistentProjectionStorage();
    }


    public function test_loadStateEnvelope_returns_null_by_default(): void
    {
        self::assertNull($this->storage->loadStateEnvelope('s1'));
    }

    public function test_loadStateEnvelope_returns_savedEnvelope(): void
    {
        $envelope = new SerializedPersistentProjectionStateEnvelope('s1', 'some-state', new DateTimeImmutable(), new DateTimeImmutable());
        $this->storage->saveStateEnvelope($envelope);
        self::assertSame($envelope, $this->storage->loadStateEnvelope('s1'));
    }

    public static function dataProvider_find(): iterable
    {
        yield ['filter' => PersistentProjectionFilter::create(), 'expectedTotalCount' => 3, 'expectedResults' => ['john', 'jane', 'jack']];
        yield ['filter' => PersistentProjectionFilter::create(criteria: ValueEquals::create('foo', 'bar', false)), 'expectedTotalCount' => 0, 'expectedResults' => []];
        yield ['filter' => PersistentProjectionFilter::create(criteria: ValueEquals::create('name', 'Jane Doe', false)), 'expectedTotalCount' => 1, 'expectedResults' => ['jane']];
        yield ['filter' => PersistentProjectionFilter::create(criteria: OrCriteria::create(ValueEquals::create('name', 'Jane Doe', true), ValueEquals::create('name', 'John Doe', false))), 'expectedTotalCount' => 2, 'expectedResults' => ['john', 'jane']];
        yield ['filter' => PersistentProjectionFilter::create(searchTerm: 'Ja'), 'expectedTotalCount' => 2, 'expectedResults' => ['jane', 'jack']];
        yield ['filter' => PersistentProjectionFilter::create(searchTerm: 'JA'), 'expectedTotalCount' => 2, 'expectedResults' => ['jane', 'jack']];
        yield ['filter' => PersistentProjectionFilter::create(searchTerm: 'Jacky'), 'expectedTotalCount' => 0, 'expectedResults' => []];
        yield ['filter' => PersistentProjectionFilter::create(ordering: Ordering::byProperty('name', OrderingDirection::ASCENDING)), 'expectedTotalCount' => 3, 'expectedResults' => ['jack', 'jane', 'john']];
        yield ['filter' => PersistentProjectionFilter::create(ordering: Ordering::fromArray([OrderingField::byTimestampField(TimestampField::CREATED, OrderingDirection::DESCENDING), OrderingField::byProperty('name', OrderingDirection::ASCENDING)])), 'expectedTotalCount' => 3, 'expectedResults' => ['jack', 'jane', 'john']];
        yield ['filter' => PersistentProjectionFilter::create(ordering: Ordering::fromArray([OrderingField::byTimestampField(TimestampField::CREATED, OrderingDirection::DESCENDING), OrderingField::byProperty('name', OrderingDirection::DESCENDING)])), 'expectedTotalCount' => 3, 'expectedResults' => ['jack', 'john', 'jane']];
        yield ['filter' => PersistentProjectionFilter::create(ordering: Ordering::fromArray([OrderingField::byTimestampField(TimestampField::CREATED, OrderingDirection::ASCENDING), OrderingField::byProperty('name', OrderingDirection::DESCENDING)])), 'expectedTotalCount' => 3, 'expectedResults' => ['john', 'jane', 'jack']];
        yield ['filter' => PersistentProjectionFilter::create(pagination: Pagination::fromLimitAndOffset(10, 3)), 'expectedTotalCount' => 3, 'expectedResults' => []];
        yield ['filter' => PersistentProjectionFilter::create(pagination: Pagination::fromLimitAndOffset(10, 1)), 'expectedTotalCount' => 3, 'expectedResults' => ['jane', 'jack']];
        yield ['filter' => PersistentProjectionFilter::create(pagination: Pagination::fromLimitAndOffset(1, 1)), 'expectedTotalCount' => 3, 'expectedResults' => ['jane']];
        yield ['filter' => PersistentProjectionFilter::create(criteria: ValueStartsWith::create('name', 'J', true), searchTerm: 'ja', ordering: Ordering::byTimestampField(TimestampField::LAST_UPDATED_AT, OrderingDirection::DESCENDING), pagination: Pagination::fromLimitAndOffset(10, 1)), 'expectedTotalCount' => 2, 'expectedResults' => ['jane']];
    }

    #[DataProvider('dataProvider_find')]
    public function test_find(PersistentProjectionFilter $filter, int $expectedTotalCount, array $expectedResults): void
    {
        $date1 = new DateTimeImmutable('@1234567891');
        $date2 = new DateTimeImmutable('@1234567892');
        $date3 = new DateTimeImmutable('@1234567893');
        $envelopes = [
            new SerializedPersistentProjectionStateEnvelope('john', '{"name":"John Doe","age":45,"isAdmin":true}', $date1, $date1),
            new SerializedPersistentProjectionStateEnvelope('jane', '{"name":"Jane Doe","age":43,"isAdmin":false}', $date1, $date2),
            new SerializedPersistentProjectionStateEnvelope('jack', '{"name":"Jack Daniels","age":63,"isAdmin":false}', $date2, $date3),
        ];
        foreach ($envelopes as $envelope) {
            $this->storage->saveStateEnvelope($envelope);
        }
        $actualResult = $this->storage->find($filter);
        self::assertSame($expectedTotalCount, $actualResult->totalCount);
        self::assertSame($expectedResults, $actualResult->map(fn (SerializedPersistentProjectionStateEnvelope $envelope) => $envelope->partitionKey));
    }

}
