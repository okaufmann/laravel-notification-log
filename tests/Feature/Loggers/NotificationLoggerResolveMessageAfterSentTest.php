<?php

namespace Okaufmann\LaravelNotificationLog\Tests\Feature\Loggers;

use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Notifications\Events\NotificationSent;
use Okaufmann\LaravelNotificationLog\Loggers\NotificationLogger;
use Okaufmann\LaravelNotificationLog\Tests\Support\DummyNotificationWithResolveMessageAfterSent;
use Okaufmann\LaravelNotificationLog\Tests\Support\TestUser;

beforeEach(function () {
    $this->logger = new NotificationLogger;
    $this->user = TestUser::factory()->create();
    $this->notification = new DummyNotificationWithResolveMessageAfterSent;
});

it('resolves message after sending when notification implements ResolveMessageForLoggingAfterSent', function () {
    // Enable message resolution
    config(['notification-log.resolve_notification_message' => true]);

    // First, log the sending notification
    $sendingEvent = new NotificationSending(
        $this->user,
        $this->notification,
        'mail'
    );

    $sentNotificationLog = $this->logger->logSendingNotification($sendingEvent);
    expect($sentNotificationLog)->not()->toBeNull();
    // Message will be resolved during sending for mail notifications, that's expected

    // Then, log the sent notification
    $sentEvent = new NotificationSent(
        $this->user,
        $this->notification,
        'mail',
        'test-response'
    );

    $updatedLog = $this->logger->logSentNotification($sentEvent);
    expect($updatedLog)->not()->toBeNull();
    expect($updatedLog->message)->toBe("Resolved message after sending to {$this->user->email} via Illuminate\Notifications\Channels\MailChannel");
});

it('does not update message if notification does not implement ResolveMessageForLoggingAfterSent', function () {
    $notification = new \Okaufmann\LaravelNotificationLog\Tests\Support\DummyNotification;

    // First, log the sending notification
    $sendingEvent = new NotificationSending(
        $this->user,
        $notification,
        'database'
    );

    $sentNotificationLog = $this->logger->logSendingNotification($sendingEvent);
    expect($sentNotificationLog)->not()->toBeNull();

    // Then, log the sent notification
    $sentEvent = new NotificationSent(
        $this->user,
        $notification,
        'database',
        'test-response'
    );

    $updatedLog = $this->logger->logSentNotification($sentEvent);
    expect($updatedLog)->not()->toBeNull();
    expect($updatedLog->message)->toBeNull(); // Should remain null since notification doesn't implement the interface
});

it('handles exceptions gracefully in resolveMessageAfterSent', function () {
    $notification = new class extends DummyNotificationWithResolveMessageAfterSent
    {
        public function resolveMessageForLoggingAfterSent(mixed $channel, $notifiable, $response): ?string
        {
            throw new \Exception('Test exception');
        }
    };

    // First, log the sending notification
    $sendingEvent = new NotificationSending(
        $this->user,
        $notification,
        'mail'
    );

    $sentNotificationLog = $this->logger->logSendingNotification($sendingEvent);
    expect($sentNotificationLog)->not()->toBeNull();

    // Then, log the sent notification - should not throw exception
    $sentEvent = new NotificationSent(
        $this->user,
        $notification,
        'mail',
        'test-response'
    );

    $updatedLog = $this->logger->logSentNotification($sentEvent);
    expect($updatedLog)->not()->toBeNull();
    expect($updatedLog->message)->toBeNull(); // Should remain null due to exception
});

it('respects resolve_notification_message config setting', function () {
    // Disable message resolution
    config(['notification-log.resolve_notification_message' => false]);

    // First, log the sending notification
    $sendingEvent = new NotificationSending(
        $this->user,
        $this->notification,
        'mail'
    );

    $sentNotificationLog = $this->logger->logSendingNotification($sendingEvent);
    expect($sentNotificationLog)->not()->toBeNull();

    // Then, log the sent notification
    $sentEvent = new NotificationSent(
        $this->user,
        $this->notification,
        'mail',
        'test-response'
    );

    $updatedLog = $this->logger->logSentNotification($sentEvent);
    expect($updatedLog)->not()->toBeNull();
    expect($updatedLog->message)->toBeNull(); // Should remain null due to config setting
});
