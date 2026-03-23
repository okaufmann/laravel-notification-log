<?php

namespace Okaufmann\LaravelNotificationLog\Contracts;

interface ResolveMessageForLoggingAfterSent
{
    /**
     * Resolve the message content for logging purposes after the notification has been sent.
     *
     * @param  mixed  $channel  The notification channel instance
     * @param  mixed  $notifiable  The notifiable entity (user, model, etc.)
     * @param  mixed  $response  The response from the channel after sending
     * @return string|null The resolved message content, or null if not available
     */
    public function resolveMessageForLoggingAfterSent(mixed $channel, $notifiable, $response): ?string;
}
