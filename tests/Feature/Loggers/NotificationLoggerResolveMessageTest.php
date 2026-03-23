<?php

use Illuminate\Notifications\Events\NotificationSending;
use Okaufmann\LaravelNotificationLog\Loggers\NotificationLogger;
use Okaufmann\LaravelNotificationLog\Tests\Support\DummyNotifiable;
use Okaufmann\LaravelNotificationLog\Tests\Support\DummyNotification;
use Okaufmann\LaravelNotificationLog\Tests\Support\DummyNotificationWithResolveMessage;

it('can use custom resolveMessageForLogging method when notification implements ResolveMessageForLogging interface', function () {
    $notifiable = new DummyNotifiable;
    $notification = new DummyNotificationWithResolveMessage;

    $logger = new NotificationLogger;
    config(['notification-log.resolve_notification_message' => true]);
    $log = $logger->logSendingNotification(new NotificationSending($notifiable, $notification, 'database'));

    expect($log->message)->toBe('Custom message for Illuminate\Notifications\Channels\DatabaseChannel channel sent to Okaufmann\LaravelNotificationLog\Tests\Support\DummyNotifiable');
});

it('falls back to default message resolution when notification does not implement ResolveMessageForLogging interface', function () {
    $notifiable = new DummyNotifiable;
    $notification = new DummyNotification;

    $logger = new NotificationLogger;
    config(['notification-log.resolve_notification_message' => true]);
    $log = $logger->logSendingNotification(new NotificationSending($notifiable, $notification, 'database'));

    expect($log->message)->toBe(json_encode(['message' => 'This is just a example message.']));
});
