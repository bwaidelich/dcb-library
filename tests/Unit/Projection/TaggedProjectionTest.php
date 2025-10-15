<?php

declare(strict_types=1);

namespace Unit\Projection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wwwision\DCBEventStore\Types\EventEnvelope;
use Wwwision\DCBEventStore\Types\StreamQuery\Criteria;
use Wwwision\DCBEventStore\Types\StreamQuery\Criteria\EventTypesAndTagsCriterion;
use Wwwision\DCBEventStore\Types\Tag;
use Wwwision\DCBEventStore\Types\Tags;
use Wwwision\DCBLibrary\DomainEvent;
use Wwwision\DCBLibrary\Projection\Projection;
use Wwwision\DCBLibrary\Projection\TaggedProjection;
use Wwwision\DCBLibrary\StreamCriteriaAware;

#[CoversClass(TaggedProjection::class)]
final class TaggedProjectionTest extends TestCase
{

    public static function getCriteria_dataProvider(): iterable
    {
        yield ['tags' => Tags::create(), 'wrappedCriteria' => null, 'expectedMergedCriteria' => [['eventTypes' => null, 'tags' => [], 'onlyLastEvent' => false]]];
        yield ['tags' => Tags::single('foo'), 'wrappedCriteria' => null, 'expectedMergedCriteria' => [['eventTypes' => null, 'tags' => ['foo'], 'onlyLastEvent' => false]]];
        yield ['tags' => Tags::single('foo'), 'wrappedCriteria' => Criteria::create(), 'expectedMergedCriteria' => [['eventTypes' => null, 'tags' => ['foo'], 'onlyLastEvent' => false]]];
        yield ['tags' => Tags::single('foo'), 'wrappedCriteria' => Criteria::create(EventTypesAndTagsCriterion::create(tags: 'foo')), 'expectedMergedCriteria' => [['eventTypes' => null, 'tags' => ['foo'], 'onlyLastEvent' => false]]];
        yield ['tags' => Tags::single('foo'), 'wrappedCriteria' => Criteria::create(EventTypesAndTagsCriterion::create(tags: 'bar')), 'expectedMergedCriteria' => [['eventTypes' => null, 'tags' => ['bar', 'foo'], 'onlyLastEvent' => false]]];
        yield ['tags' => Tags::single('foo'), 'wrappedCriteria' => Criteria::create(EventTypesAndTagsCriterion::create(eventTypes: 'EventType1')), 'expectedMergedCriteria' => [['eventTypes' => ['EventType1'], 'tags' => ['foo'], 'onlyLastEvent' => false]]];
        yield ['tags' => Tags::create(Tag::fromString('foo'), Tag::fromString('bar')), 'wrappedCriteria' => Criteria::create(EventTypesAndTagsCriterion::create(eventTypes: 'EventType1'), EventTypesAndTagsCriterion::create(eventTypes: ['EventType1', 'EventType2'], tags: 'baz'), EventTypesAndTagsCriterion::create(eventTypes: ['EventType3']), EventTypesAndTagsCriterion::create(tags: ['foos'])), 'expectedMergedCriteria' => [['eventTypes' => ['EventType1'], 'tags' => ['bar', 'foo'], 'onlyLastEvent' => false], ['eventTypes' => ['EventType1', 'EventType2'], 'tags' => ['bar', 'baz', 'foo'], 'onlyLastEvent' => false], ['eventTypes' => ['EventType3'], 'tags' => ['bar', 'foo'], 'onlyLastEvent' => false], ['eventTypes' => null, 'tags' => ['bar', 'foo', 'foos'], 'onlyLastEvent' => false]]];
    }

    #[DataProvider('getCriteria_dataProvider')]
    public function test_getCriteria(Tags|Tag $tags, Criteria|null $wrappedCriteria, array $expectedMergedCriteria): void
    {
        if ($wrappedCriteria === null) {
            $wrappedProjection = $this->createMock(Projection::class);
        } else {
            $wrappedProjection = new class ($wrappedCriteria) implements Projection, StreamCriteriaAware {
                public function __construct(private Criteria $criteria) {}
                public function initialState(): mixed {}
                public function apply(mixed $state, DomainEvent $domainEvent, EventEnvelope $eventEnvelope): mixed {}
                public function getCriteria(): Criteria
                {
                    return $this->criteria;
                }
            };
        }
        $taggedProjection = TaggedProjection::create($tags, $wrappedProjection);

        self::assertSame($expectedMergedCriteria, json_decode(json_encode($taggedProjection->getCriteria(), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR));
    }

}