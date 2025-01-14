<?php

declare(strict_types=1);

namespace Wwwision\DCBLibrary\Subscription\Engine;

use Psr\Log\LoggerInterface;
use Wwwision\DCBEventStore\EventStore;
use Wwwision\DCBEventStore\Types\EventEnvelope;
use Wwwision\DCBEventStore\Types\ReadOptions;
use Wwwision\DCBEventStore\Types\SequenceNumber;
use Wwwision\DCBEventStore\Types\StreamQuery\StreamQuery;
use Wwwision\DCBLibrary\DomainEvent;
use Wwwision\DCBLibrary\EventSerializer;
use Wwwision\DCBLibrary\ProvidesSetup;
use Wwwision\DCBLibrary\Subscription\RetryStrategy\RetryStrategy;
use Wwwision\DCBLibrary\Subscription\RunMode;
use Wwwision\DCBLibrary\Subscription\Status;
use Wwwision\DCBLibrary\Subscription\Store\SubscriptionCriteria;
use Wwwision\DCBLibrary\Subscription\Store\SubscriptionStore;
use Wwwision\DCBLibrary\Subscription\Subscriber\Subscribers;
use Wwwision\DCBLibrary\Subscription\Subscription;
use Wwwision\DCBLibrary\Subscription\Subscriptions;

final class SubscriptionEngine
{

    public function __construct(
        private readonly EventStore $eventStore,
        private readonly SubscriptionStore $subscriptionStore,
        private readonly Subscribers $subscribers,
        private readonly EventSerializer $eventSerializer,
        private readonly RetryStrategy $retryStrategy,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function setup(
        SubscriptionEngineCriteria|null $criteria = null,
        int|null $limit = null,
    ): void {
        $criteria ??= SubscriptionEngineCriteria::noConstraints();
        $subscriptionCriteria = SubscriptionCriteria::create(
            ids: $criteria->ids,
            groups: $criteria->groups,
            status: [Status::NEW],
        );
        $this->runInternal($subscriptionCriteria, 'setup', $limit);
    }


    public function run(
        SubscriptionEngineCriteria|null $criteria = null,
        int|null $limit = null,
    ): void {
        $criteria ??= SubscriptionEngineCriteria::noConstraints();

        $subscriptionCriteria = SubscriptionCriteria::create(
            ids: $criteria->ids,
            groups: $criteria->groups,
            status: [Status::ACTIVE],
        );
        $this->runInternal($subscriptionCriteria, 'run', $limit);
    }

    private function lockSubscriptions(Subscriptions $subscriptions): void
    {
        foreach ($subscriptions as $subscription) {
            $sT = microtime(true);
            while (!$this->subscriptionStore->acquireLock($subscription->id)) {
                if (microtime(true) - $sT > 5) {
                    // TODO better exception handling
                    throw new \RuntimeException(sprintf('Failed to acquire lock for subscription "%s"', $subscription->id->value), 1721895494);
                }
            }
        }
    }

    private function releaseSubscriptions(Subscriptions $subscriptions): void
    {
        foreach ($subscriptions as $subscription) {
            $this->subscriptionStore->releaseLock($subscription->id);
        }
    }

    private function runInternal(SubscriptionCriteria $criteria, string $process, int|null $limit): void
    {
        $this->logger?->info(sprintf('Subscription Engine: %s: Start.', $process));
        $this->discoverNewSubscriptions();
        $this->discoverDetachedSubscriptions($criteria);
        $this->retrySubscriptions($criteria);
        $subscriptions = $this->subscriptionStore->findByCriteria($criteria);
        if ($subscriptions->isEmpty()) {
            $this->logger?->info(sprintf('Subscription Engine: %s: No subscriptions to process, finishing', $process));
            return;// new ProcessedResult(0, true);
        }

        $this->lockSubscriptions($subscriptions);

        $startSequenceNumber = $this->lowestSubscriptionPosition($subscriptions)->next();
        $this->logger?->debug(
            sprintf(
                'Subscription Engine: %s: Event stream is processed from position %d.',
                $process,
                $startSequenceNumber->value,
            ),
        );

        /** @var list<Error> $errors */
        $errors = [];
        $messageCounter = 0;
        $eventStream = $this->eventStore->read(StreamQuery::wildcard(), ReadOptions::create(from: $startSequenceNumber));
        $lastSequenceNumber = null;
        $subscriptionsToRun = $subscriptions;
        foreach ($eventStream as $eventEnvelope) {
            $domainEvent = $this->eventSerializer->convertEvent($eventEnvelope->event);
            foreach ($subscriptionsToRun as $subscription) {
                if ($subscription->position->value > $eventEnvelope->sequenceNumber->value) {
                    $this->logger?->debug(
                        sprintf(
                            'Subscription Engine: %s: Subscription "%s" is farther than the current position (%d > %d), skipped.',
                            $process,
                            $subscription->id->value,
                            $subscription->position->value,
                            $eventEnvelope->sequenceNumber->value,
                        ),
                    );
                    continue;
                }
                $error = $this->handleEvent($eventEnvelope, $domainEvent, $subscription);
                if (!$error) {
                    continue;
                }
                $errors[] = $error;
                $subscriptionsToRun = $subscriptionsToRun->without($subscription->id);
            }
            $messageCounter++;

            $this->logger?->debug(
                sprintf(
                    'Subscription Engine: %s: Current event stream position: %s',
                    $process,
                    $eventEnvelope->sequenceNumber->value,
                ),
            );
            $lastSequenceNumber = $eventEnvelope->sequenceNumber;
            if ($limit !== null && $messageCounter >= $limit) {
                $this->logger?->info(
                    sprintf(
                        'Subscription Engine: %s: Message limit (%d) reached, cancelled.',
                        $process,
                        $limit,
                    ),
                );
                $this->releaseSubscriptions($subscriptions);

                return;// new ProcessedResult($messageCounter, false, $errors);
            }
        }
        foreach ($subscriptions as $subscription) {
            $newSubscriptionStatus = $subscription->runMode === RunMode::ONCE ? Status::FINISHED : Status::ACTIVE;
            if ($subscription->status === $newSubscriptionStatus) {
                continue;
            }
            $this->subscriptionStore->update($subscription->id, fn(Subscription $subscription) => $subscription->with(
                status: $newSubscriptionStatus,
                retryAttempt: 0,
            )->withoutError());
            $this->logger?->info(sprintf(
                'Subscription Engine: %s: Subscription "%s" changed status from %s to %s.',
                $process,
                $subscription->id->value,
                $subscription->status->name,
                $newSubscriptionStatus->name
            ));
        }
        $this->logger?->info(
            sprintf(
                'Subscription Engine: %s: End of stream on position %d has been reached, finished.',
                $process,
                $lastSequenceNumber?->value ?? ($startSequenceNumber->value - 1),
            ),
        );
        $this->releaseSubscriptions($subscriptions);

        return;// new ProcessedResult($messageCounter, true, $errors);
    }

    private function handleEvent(EventEnvelope $eventEnvelope, DomainEvent $domainEvent, Subscription $subscription): Error|null
    {
        $subscriber = $this->subscribers->get($subscription->id);
        try {
            $subscriber->handler->handle($domainEvent, $eventEnvelope);
        } catch (\Throwable $e) {
            $this->logger?->error(
                sprintf(
                    'Subscription Engine: Subscriber "%s" for "%s" could not process the event "%s" (sequence number: %d): %s',
                    $subscriber::class,
                    $subscription->id->value,
                    $eventEnvelope->event->type->value,
                    $eventEnvelope->sequenceNumber->value,
                    $e->getMessage(),
                ),
            );
            $this->subscriptionStore->update($subscription->id, static fn(Subscription $subscription) => $subscription->withError($e));
            return new Error(
                $subscription->id,
                $e->getMessage(),
                $e,
            );
        }
        $this->logger?->debug(
            sprintf(
                'Subscription Engine: Subscriber "%s" for "%s" processed the event "%s" (sequence number: %d).',
                $subscriber->handler::class,
                $subscription->id->value,
                $eventEnvelope->event->type->value,
                $eventEnvelope->sequenceNumber->value,
            ),
        );
        $this->subscriptionStore->update($subscription->id, static fn(Subscription $subscription) => $subscription->with(
            position: $eventEnvelope->sequenceNumber,
            retryAttempt: 0,
        ));
        return null;
    }

    private function discoverNewSubscriptions(): void
    {
        $registeredSubscriptions = $this->subscriptionStore->findByCriteria(SubscriptionCriteria::noConstraints());
        foreach ($this->subscribers as $subscriber) {
            if ($registeredSubscriptions->contain($subscriber->id)) {
                continue;
            }
            if ($subscriber->handler instanceof ProvidesSetup) {
                $subscriber->handler->setup();
            }
            $subscription = Subscription::create(
                $subscriber->id,
                $subscriber->group,
                $subscriber->runMode,
            );
            if ($subscriber->runMode === RunMode::FROM_NOW) {
                $subscription = $subscription->with(
                    status: Status::ACTIVE,
                    position: $this->lastSequenceNumber(),
                );
            }
            $this->subscriptionStore->add($subscription);
            $this->logger?->info(
                sprintf(
                    'Subscription Engine: New Subscriber "%s" was found and added to the subscription store.',
                    $subscriber->id->value,
                ),
            );
        }
    }

    private function discoverDetachedSubscriptions(SubscriptionCriteria $criteria): void
    {
        $registeredSubscriptions = $this->subscriptionStore->findByCriteria(SubscriptionCriteria::create(
            $criteria->ids,
            $criteria->groups,
            [Status::ACTIVE, Status::PAUSED, Status::FINISHED],
        ));
        foreach ($registeredSubscriptions as $subscription) {
            if ($this->subscribers->contain($subscription->id)) {
                continue;
            }
            $this->subscriptionStore->update($subscription->id, fn(Subscription $subscription) => $subscription->with(
                status: Status::DETACHED,
            ));
            $this->logger?->info(
                sprintf(
                    'Subscription Engine: Subscriber for "%s" not found and has been marked as detached.',
                    $subscription->id->value,
                ),
            );
        }
    }

    private function retrySubscriptions(SubscriptionCriteria $criteria): void
    {
        $failedSubscriptions = $this->subscriptionStore->findByCriteria(
            SubscriptionCriteria::create(
                ids: $criteria->ids,
                groups: $criteria->groups,
                status: [Status::ERROR],
            )
        );
        foreach ($failedSubscriptions as $subscription) {
            if ($subscription->error === null) {
                continue;
            }
            $error = $subscription->error;
            $retryable = in_array(
                $error->previousStatus,
                [Status::NEW, Status::BOOTING, Status::ACTIVE],
                true,
            );
            if (!$retryable) {
                continue;
            }
            if (!$this->retryStrategy->shouldRetry($subscription)) {
                continue;
            }
            $this->subscriptionStore->update($subscription->id, static fn(Subscription $subscription) => $subscription->with(
                status: $error->previousStatus,
                retryAttempt: $subscription->retryAttempt + 1,
            )->withoutError());

            $this->logger?->info(
                sprintf(
                    'Subscription Engine: Retry subscription "%s" (%d) and set back to %s.',
                    $subscription->id->value,
                    $subscription->retryAttempt + 1,
                    $error->previousStatus->name,
                ),
            );
        }
    }


    private function lastSequenceNumber(): SequenceNumber
    {
        $events = $this->eventStore->read(StreamQuery::wildcard(), ReadOptions::create(backwards: true));
        return $events->first()?->sequenceNumber ?? SequenceNumber::fromInteger(0);
    }

    private function lowestSubscriptionPosition(Subscriptions $subscriptions): SequenceNumber
    {
        $min = null;
        foreach ($subscriptions as $subscription) {
            if ($min !== null && $subscription->position->value >= $min->value) {
                continue;
            }
            $min = $subscription->position;
        }
        return $min ?? SequenceNumber::fromInteger(0);
    }
}
