<?php

namespace Okaufmann\LaravelNotificationLog\Contracts;

interface ResolveMessageForLogging
{
    /**
     * Resolve the message content for logging purposes.
     *
     * @param  mixed  $channel  The notification channel
     * @param  mixed  $notifiable  The notifiable entity
     * @return string The resolved message content
     */
    public function resolveMessageForLogging(mixed $channel, $notifiable): string;
}
