<?php

use Illuminate\Notifications\Events\NotificationSending;
use Okaufmann\LaravelNotificationLog\Listeners\MessageEventListener;
use Okaufmann\LaravelNotificationLog\NotificationDeliveryStatus;
use Okaufmann\LaravelNotificationLog\Tests\Support\DummyNotifiable;
use Okaufmann\LaravelNotificationLog\Tests\Support\DummyNotification;
use Okaufmann\LaravelNotificationLog\Tests\Support\DummyNotificationUnique;
use Okaufmann\LaravelNotificationLog\Tests\Support\TestUser;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

it('does not log a sending notification message when disabled in configuration', function () {
    $notifiable = new DummyNotifiable;
    $notification = new DummyNotification;
    $listener = resolve(MessageEventListener::class);

    config(['notification-log.resolve-notification-message' => false]);
    $listener->handleSendingNotification(new NotificationSending($notifiable, $notification, 'database'));

    assertDatabaseCount('notification_logs_sent_notifications', 1);
});

it('does log a sending notification event when enabled in configuration', function () {
    $notifiable = new DummyNotifiable;
    $notification = new DummyNotification;
    $listener = resolve(MessageEventListener::class);

    config(['notification-log.resolve-notification-message' => true]);
    $listener->handleSendingNotification(new NotificationSending($notifiable, $notification, 'database'));

    assertDatabaseCount('notification_logs_sent_notifications', 1);
});

it('skips notifications to non-unique fingerprint', function () {
    $notifiable = new TestUser;
    $notification = new DummyNotificationUnique;
    $nonUniqueNotification = clone $notification;
    $listener = resolve(MessageEventListener::class);

    config(['notification-log.resolve-notification-message' => true]);
    $listener->handleSendingNotification(new NotificationSending($notifiable, $notification, 'database'));
    $listener->handleSendingNotification(new NotificationSending($notifiable, $nonUniqueNotification, 'database'));

    assertDatabaseHas('notification_logs_sent_notifications', ['status' => NotificationDeliveryStatus::SENDING]);
    assertDatabaseHas('notification_logs_sent_notifications', ['status' => NotificationDeliveryStatus::SKIPPED]);
    assertDatabaseCount('notification_logs_sent_notifications', 2);
});

it('sends notifications with same fingerprint but different channels', function () {
    $notifiable = new TestUser;
    $notification = new DummyNotificationUnique;
    $nonUniqueNotification = clone $notification;
    $listener = resolve(MessageEventListener::class);

    config(['notification-log.resolve-notification-message' => true]);
    $listener->handleSendingNotification(new NotificationSending($notifiable, $notification, 'database'));
    $listener->handleSendingNotification(new NotificationSending($notifiable, $nonUniqueNotification, 'broadcast'));

    assertDatabaseHas('notification_logs_sent_notifications', ['status' => NotificationDeliveryStatus::SENDING]);
    assertDatabaseMissing('notification_logs_sent_notifications', ['status' => NotificationDeliveryStatus::SKIPPED]);
    assertDatabaseCount('notification_logs_sent_notifications', 2);
});
