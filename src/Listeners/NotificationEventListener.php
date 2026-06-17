<?php

namespace Okaufmann\LaravelNotificationLog\Listeners;

use Closure;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Cache;
use Okaufmann\LaravelNotificationLog\Contracts\EnsureUniqueNotification;
use Okaufmann\LaravelNotificationLog\Contracts\ResendableNotification;
use Okaufmann\LaravelNotificationLog\Contracts\ShouldLogNotification;
use Okaufmann\LaravelNotificationLog\Loggers\NotificationLogger;

class NotificationEventListener
{
    public function __construct(
        protected readonly NotificationLogger $notificationLogger,
    ) {}

    public function handleSentNotification(NotificationSent $event): void
    {
        $this->preventRaceCondition($event, fn () => $this->notificationLogger->logSentNotification($event));
    }

    public function handleSendingNotification(NotificationSending $event): bool
    {
        if (! $event->notification instanceof ShouldLogNotification) {
            return true;
        }

        return $this->preventRaceCondition($event, function () use ($event): bool {
            if ($this->shouldBeSkipped($event)
            ) {
                $this->notificationLogger->logSkippedNotification($event);

                return false;
            }

            $this->notificationLogger->logSendingNotification($event);

            return true;
        });
    }

    public function handleFailedNotification(NotificationFailed $event): void
    {
        $this->preventRaceCondition($event, fn () => $this->notificationLogger->logFailedNotification($event));
    }

    public function subscribe($events): void
    {
        $events->listen(NotificationSent::class, [self::class, 'handleSentNotification']);
        $events->listen(NotificationSending::class, [self::class, 'handleSendingNotification']);
        $events->listen(NotificationFailed::class, [self::class, 'handleFailedNotification']);
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    protected function preventRaceCondition(NotificationSent|NotificationSending|NotificationFailed $event, Closure $callback)
    {
        return Cache::lock($this->notificationLogger->getNotificationKey($event), 5)->block(5, $callback);
    }

    protected function shouldBeSkipped(NotificationSending $event): bool
    {
        if (! $event->notification instanceof EnsureUniqueNotification) {
            return false;
        }

        if ($event->notification instanceof ResendableNotification && $event->notification->isBeingResent()) {
            return false;
        }

        return $event->notification->wasSentTo($event->notifiable, withSameFingerprint: true)->onChannel($event->channel)->inThePast();
    }
}
