<?php

namespace Okaufmann\LaravelNotificationLog\Tests\Support;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Okaufmann\LaravelNotificationLog\Contracts\ResolveMessageForLoggingAfterSent;
use Okaufmann\LaravelNotificationLog\Contracts\ShouldLogNotification;
use Ramsey\Uuid\Uuid;

class DummyNotificationWithResolveMessageAfterSent extends Notification implements ResolveMessageForLoggingAfterSent, ShouldLogNotification
{
    private int $currentAttempt = 1;

    public function __construct()
    {
        $this->id = (string) Uuid::uuid4();
    }

    public function resolveMessageForLoggingAfterSent(mixed $channel, $notifiable, $response): ?string
    {
        // Simulate resolving message content after sending
        // In a real scenario, this might extract the actual message from the response
        return "Resolved message after sending to {$notifiable->email} via ".get_class($channel);
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Dummy Notification Subject')
            ->line('This is a dummy notification message.');
    }

    public function getCurrentAttempt(): int
    {
        return $this->currentAttempt;
    }

    public function setCurrentAttempt(?int $attempt = null): void
    {
        $this->currentAttempt = $attempt ?? $this->currentAttempt + 1;
    }

    public function fingerprint($notifiable)
    {
        return "dummy-fingerprint-{$this->id}";
    }
}
