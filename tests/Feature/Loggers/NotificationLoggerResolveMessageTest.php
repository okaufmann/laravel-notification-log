<?php

use Okaufmann\LaravelNotificationLog\Tests\Support\DummyNotifiable;
use Okaufmann\LaravelNotificationLog\Tests\Support\DummyNotificationWithResolveMessage;

it('can use custom resolveMessageForLogging method when notification implements ResolveMessageForLogging interface', function () {
    $notifiable = new DummyNotifiable;
    $notification = new DummyNotificationWithResolveMessage;

    $logger = new \Okaufmann\LaravelNotificationLog\Loggers\NotificationLogger;
    config(['notification-log.resolve_notification_message' => true]);
    $log = $logger->logSendingNotification(new \Illuminate\Notifications\Events\NotificationSending($notifiable, $notification, 'database'));

    expect($log->message)->toBe('Custom message for Illuminate\Notifications\Channels\DatabaseChannel channel sent to Okaufmann\LaravelNotificationLog\Tests\Support\DummyNotifiable');
});

it('falls back to default message resolution when notification does not implement ResolveMessageForLogging interface', function () {
    $notifiable = new DummyNotifiable;
    $notification = new \Okaufmann\LaravelNotificationLog\Tests\Support\DummyNotification;

    $logger = new \Okaufmann\LaravelNotificationLog\Loggers\NotificationLogger;
    config(['notification-log.resolve_notification_message' => true]);
    $log = $logger->logSendingNotification(new \Illuminate\Notifications\Events\NotificationSending($notifiable, $notification, 'database'));

    expect($log->message)->toBe(json_encode(['message' => 'This is just a example message.']));
});
