<?php

namespace Okaufmann\LaravelNotificationLog\Listeners;

use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Okaufmann\LaravelNotificationLog\Contracts\EnsureUniqueNotification;
use Okaufmann\LaravelNotificationLog\Contracts\ResendableNotification;
use Okaufmann\LaravelNotificationLog\Loggers\NotificationLogger;

class NotificationEventListener
{
    public function __construct(
        protected readonly NotificationLogger $notificationLogger,
    ) {}

    public function handleSentNotification(NotificationSent $event): void
    {
        $this->notificationLogger->logSentNotification($event);
    }

    public function handleSendingNotification(NotificationSending $event): ?false
    {
        if ($this->shouldBeSkipped($event)
        ) {
            $this->notificationLogger->logSkippedNotification($event);

            return false;
        }

        $this->notificationLogger->logSendingNotification($event);

        return null;
    }

    public function handleFailedNotification(NotificationFailed $event): void
    {
        $this->notificationLogger->logFailedNotification($event);
    }

    public function subscribe($events): void
    {
        $events->listen(NotificationSent::class, [self::class, 'handleSentNotification']);
        $events->listen(NotificationSending::class, [self::class, 'handleSendingNotification']);
        $events->listen(NotificationFailed::class, [self::class, 'handleFailedNotification']);
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
