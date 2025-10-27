<?php

namespace Okaufmann\LaravelNotificationLog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Okaufmann\LaravelNotificationLog\Contracts\ResendableNotification;
use Okaufmann\LaravelNotificationLog\Loggers\NotificationLogger;
use Okaufmann\LaravelNotificationLog\NotificationDeliveryStatus;

/**
 * @property string $id
 * @property string $notification_id
 * @property string $notification_type
 * @property string $notifiable_id
 * @property string $notifiable_type
 * @property array $anonymous_notifiable_routes
 * @property string $fingerprint
 * @property string $channel
 * @property int $attempt
 * @property Carbon $sent_at
 * @property array $notifiable
 * @property bool $queued
 * @property string $message
 * @property array $data
 * @property NotificationDeliveryStatus $status
 * @property string $notification_serialized
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class SentNotificationLog extends Model
{
    use HasFactory;
    use HasUlids;
    use MassPrunable;

    protected $table = 'notification_logs_sent_notifications';

    protected $guarded = [];

    protected $casts = [
        'queued' => 'boolean',
        'data' => 'json',
        'anonymous_notifiable_routes' => 'array',
        'status' => NotificationDeliveryStatus::class,
        'sent_at' => 'immutable_datetime',
    ];

    public function prunable(): Builder
    {
        $threshold = config('notification-log.prune_after_days');

        return static::where('created_at', '<=', now()->subDays($threshold));
    }

    public function notifiable(): MorphTo
    {
        return $this->morphTo('notifiable');
    }

    public static function latestFor(
        $notifiable,
        ?string $fingerprint = null,
        string|array|null $notificationType = null,
        ?Carbon $before = null,
        ?Carbon $after = null,
        string|array|null $channel = null,
    ): ?self {
        return self::latestForQuery(...func_get_args())->first();
    }

    public static function latestForQuery(
        $notifiable,
        ?string $fingerprint = null,
        string|array|null $notificationType = null,
        ?Carbon $before = null,
        ?Carbon $after = null,
        string|array|null $channel = null,
    ): Builder {
        $channels = collect(Arr::wrap($channel))
            ->map(fn ($channel) => resolve(NotificationLogger::class)->resolveChannel($channel))
            ->toArray();

        return self::query()
            ->where('notifiable_type', $notifiable->getMorphClass())
            ->where('notifiable_id', $notifiable->getKey())
            ->when($fingerprint, fn (Builder $query) => $query->where('fingerprint', $fingerprint))
            ->when($notificationType, function (Builder $query) use ($notificationType) {
                $query->whereIn('notification_type', Arr::wrap($notificationType));
            })
            ->when($channel, function (Builder $query) use ($channels) {
                $query->whereIn('channel', $channels);
            })
            ->when($before, function (Builder $query) use ($before) {
                $query->where('created_at', '<', $before->toDateTimeString());
            })
            ->when($after, function (Builder $query) use ($after) {
                $query->where('created_at', '>', $after->toDateTimeString());
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function notification()
    {
        if (! config('notification-log.store_serialized_notifications')) {
            throw new \RuntimeException('Serialized notifications are not stored. Enable the config option `notification-log.store_serialized_notification` to use this method.');
        }

        return unserialize($this->notification_serialized, ['allowed_classes' => true]);
    }

    public function resend($notifiable, ?array $channels = null): void
    {
        if (! in_array(Notifiable::class, class_uses($notifiable), true)) {
            throw new \InvalidArgumentException('The notifiable must use the Illuminate\Notifications\Notifiable trait!');
        }

        $notification = $this->notification();

        if (! $notification instanceof ResendableNotification) {
            throw new \InvalidArgumentException('The notification must implement the Okaufmann\LaravelNotificationLog\Contracts\ResendableNotification interface!');
        }

        $notifiable->notifyNow($notification->markAsResending(), $channels);
    }

    public function canResendNotification(): bool
    {
        return ! empty($this->notification_serialized);
    }
}
