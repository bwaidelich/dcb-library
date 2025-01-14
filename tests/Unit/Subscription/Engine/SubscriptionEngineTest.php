<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Tests\Unit\Subscription\Engine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Wwwision\DCBEventStore\EventStore;
use Wwwision\DCBEventStore\Helpers\InMemoryEventStore;
use Wwwision\DCBEventStore\Types\AppendCondition;
use Wwwision\DCBEventStore\Types\Event;
use Wwwision\DCBEventStore\Types\EventEnvelope;
use Wwwision\DCBEventStore\Types\Events;
use Wwwision\DCBEventStore\Types\SequenceNumber;
use Wwwision\DCBLibrary\DomainEvent;
use Wwwision\DCBLibrary\EventHandling\EventHandler;
use Wwwision\DCBLibrary\EventSerializer;
use Wwwision\DCBLibrary\Subscription\Engine\SubscriptionEngine;
use Wwwision\DCBLibrary\Subscription\RetryStrategy\RetryStrategy;
use Wwwision\DCBLibrary\Subscription\RunMode;
use Wwwision\DCBLibrary\Subscription\Status;
use Wwwision\DCBLibrary\Subscription\Store\InMemorySubscriptionStore;
use Wwwision\DCBLibrary\Subscription\Subscriber\Subscriber;
use Wwwision\DCBLibrary\Subscription\Subscriber\Subscribers;
use Wwwision\DCBLibrary\Subscription\Subscription;
use Wwwision\DCBLibrary\Subscription\SubscriptionGroup;
use Wwwision\DCBLibrary\Subscription\SubscriptionId;
use Wwwision\DCBLibrary\Subscription\Subscriptions;

#[CoversClass(SubscriptionEngine::class)]
final class SubscriptionEngineTest extends TestCase
{
    private EventStore $eventStore;
    private InMemorySubscriptionStore $subscriptionStore;
    private EventSerializer&MockObject $eventSerializer;
    private RetryStrategy&MockObject $retryStrategy;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->eventStore = InMemoryEventStore::create();
        $this->eventStore->append(
            Events::fromArray([
                Event::create('EventType1', '{}', ['foo:bar']),
                Event::create('EventType2', '{}', ['foo:bar']),
                Event::create('EventType1', '{}', ['foo:baz']),
                Event::create('EventType1', '{}', ['bar:foos']),
            ]),
            AppendCondition::noConstraints(),
        );

        $this->subscriptionStore = new InMemorySubscriptionStore();
        $this->subscriptions = Subscriptions::none();
        $this->eventSerializer = $this->createMock(EventSerializer::class);
        $this->retryStrategy = $this->createMock(RetryStrategy::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    private function buildEngine(Subscribers $subscribers): SubscriptionEngine
    {
        return new SubscriptionEngine(
            $this->eventStore,
            $this->subscriptionStore,
            $subscribers,
            $this->eventSerializer,
            $this->retryStrategy,
            $this->logger,
        );
    }

    private function buildSubscription(
        string $id = null,
        string $group = null,
        RunMode $runMode = null,
        Status $status = null,
        int $position = null,
    ): Subscription {
        $subscription = Subscription::create(
            id: SubscriptionId::fromString($id ?? 'some-id'),
            group: SubscriptionGroup::fromString($group ?? 'some-group'),
            runMode: $runMode ?? RunMode::FROM_BEGINNING,
        );
        if ($status !== null) {
            $subscription = $subscription->with(status: $status);
        }
        if ($position !== null) {
            $subscription = $subscription->with(position: SequenceNumber::fromInteger($position));
        }
        return $subscription;
    }


    public function test(): void
    {
        $subscription = $this->buildSubscription(status: Status::ACTIVE);

        $mockEventHandler = new class implements EventHandler {
            public function handle(DomainEvent $domainEvent, EventEnvelope $eventEnvelope): void
            {
                if ($eventEnvelope->sequenceNumber->value === 3) {
                    throw new \Exception('Some example exception');
                }
            }
        };

        $subscriber = new Subscriber(
            $subscription->id,
            $subscription->group,
            $subscription->runMode,
            $mockEventHandler,
        );
        $this->subscriptionStore->add($subscription);


        $engine = $this->buildEngine(Subscribers::fromArray([$subscriber]));
        $engine->run();

        $updatedSubscription = $this->subscriptionStore->findOneById($subscription->id);
        self::assertSame(Status::ERROR, $updatedSubscription->status);
        self::assertSame(2, $updatedSubscription->position->value);
    }
}
