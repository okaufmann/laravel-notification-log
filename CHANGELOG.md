# Changelog

All notable changes to `laravel-notification-log` will be documented in this file.

## v6.0.0 - 2026-03-23

### What's Changed

* chore(deps): bump dependabot/fetch-metadata from 2.4.0 to 2.5.0 by @dependabot[bot] in https://github.com/okaufmann/laravel-notification-log/pull/20
* Add Laravel 13 Support by @okaufmann in https://github.com/okaufmann/laravel-notification-log/pull/22

**Full Changelog**: https://github.com/okaufmann/laravel-notification-log/compare/5.2.2...v6.0.0

## 5.2.2 - 2025-10-27

### What's Changed

* Model type hints by @okaufmann in https://github.com/okaufmann/laravel-notification-log/pull/18

**Full Changelog**: https://github.com/okaufmann/laravel-notification-log/compare/5.2.1...5.2.2

## 5.2.1 - 2025-10-27

### What's Changed

* Add missing cast for sent_at attribute by @okaufmann in https://github.com/okaufmann/laravel-notification-log/pull/17

**Full Changelog**: https://github.com/okaufmann/laravel-notification-log/compare/5.2.0...5.2.1

## 5.2.0 - 2025-10-24

### What's Changed

* Resolving Messages After Sending by @okaufmann in https://github.com/okaufmann/laravel-notification-log/pull/16

**Full Changelog**: https://github.com/okaufmann/laravel-notification-log/compare/5.1.0...5.2.0

## 5.1.0 - 2025-10-24

### What's Changed

* chore(deps): bump aglipanci/laravel-pint-action from 2.3.1 to 2.5 by @dependabot[bot] in https://github.com/okaufmann/laravel-notification-log/pull/7
* chore(deps): bump dependabot/fetch-metadata from 2.3.0 to 2.4.0 by @dependabot[bot] in https://github.com/okaufmann/laravel-notification-log/pull/8
* chore(deps): bump aglipanci/laravel-pint-action from 2.5 to 2.6 by @dependabot[bot] in https://github.com/okaufmann/laravel-notification-log/pull/10
* chore(deps): bump stefanzweifel/git-auto-commit-action from 5 to 7 by @dependabot[bot] in https://github.com/okaufmann/laravel-notification-log/pull/13
* chore(deps): bump actions/checkout from 4 to 5 by @dependabot[bot] in https://github.com/okaufmann/laravel-notification-log/pull/11
* Pest 4 by @okaufmann in https://github.com/okaufmann/laravel-notification-log/pull/15
* Add Interface to use resolveMessageForLogging in Notifications https://github.com/okaufmann/laravel-notification-log/commit/6a9fe7c964c52f88e8e78eb269df6599f5776bc2

### New Contributors

* @okaufmann made their first contribution in https://github.com/okaufmann/laravel-notification-log/pull/15

**Full Changelog**: https://github.com/okaufmann/laravel-notification-log/compare/5.0.0...5.1.0

## 5.0.0 - 2025-03-09

### What's Changed

* Support Laravel 12
* chore(deps): bump dependabot/fetch-metadata from 2.1.0 to 2.3.0 by @dependabot in https://github.com/okaufmann/laravel-notification-log/pull/3
* chore(deps): bump actions/checkout from 3 to 4 by @dependabot in https://github.com/okaufmann/laravel-notification-log/pull/6
* chore(deps): bump stefanzweifel/git-auto-commit-action from 4 to 5 by @dependabot in https://github.com/okaufmann/laravel-notification-log/pull/5
* chore(deps): bump aglipanci/laravel-pint-action from 1.0.0 to 2.3.1 by @dependabot in https://github.com/okaufmann/laravel-notification-log/pull/4

### New Contributors

* @dependabot made their first contribution in https://github.com/okaufmann/laravel-notification-log/pull/3

**Full Changelog**: https://github.com/okaufmann/laravel-notification-log/compare/4.6.0...5.0.0

## 4.6.0 - 2024-10-01

### What's Changed

- Allow to set a custom notification log model.

**Full Changelog**: https://github.com/okaufmann/laravel-notification-log/compare/4.5.0...4.6.0

## 4.5.0 - 2024-10-01

### What's Changed

- Store `message_id` if available for email channel messages.

**Full Changelog**: https://github.com/okaufmann/laravel-notification-log/compare/4.4.0...4.5.0

## 4.4.0 - 2024-09-26

### What's Changes

- Store flag into meta data if resendable notification was resent

**Full Changelog**: https://github.com/okaufmann/laravel-notification-log/compare/4.3.0...4.4.0

## 4.3.0 - 2024-09-26

### What's Changed

- Added support to check if a notification log can resend its notification

**Full Changelog**: https://github.com/okaufmann/laravel-notification-log/compare/4.2.0...4.3.0

## 4.2.0 - 2024-09-26

### What's Changed

- Added support to store serialized notifications in the logs table
- Added support to resend a notification

### Upgrade to 4.2.0

Be sure to add and run the following migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('notification_logs_sent_notifications', function (Blueprint $table) {
            $table->text('notification_serialized')->nullable()->after('sent_at');
        });
    }
};












```
Also you may want to publish the configs by running

```shell
php artisan vendor:publish --tag="notification-log-config"











```
**Full Changelog**: https://github.com/okaufmann/laravel-notification-log/compare/4.1.2...4.2.0

## 4.1.1 - 2024-09-26

### What's Changed

- Log mail sender (Now for real)

**Full Changelog**: https://github.com/okaufmann/laravel-notification-log/compare/4.1.0...4.1.1

## 4.1.0 - 2024-09-26

### What's Changed

- Log mail sender

**Full Changelog**: https://github.com/okaufmann/laravel-notification-log/compare/4.0.0...4.1.0

## 4.0.0 - 2024-09-25

### What's Changed

- Deprecated plain mail message logging; now only logging notifications.
- Notifications sending mail messages now log the mail content and metadata.
- Removed mail body compression—messages are now stored in plain text.
- Introduced `getExtraData` method for notifications to enrich data when sent.
- Updated to Pest 3.

### Upgrade from 3.0.0 to 4.0.0

Before upgrading, **ensure you create a database backup!**

We’ve added a migration that handles all schema changes and attempts to migrate logged mail messages, merging them with the associated notification log entries.

**Important**: This migration **drops** the `notification_logs_sent_messages` table. If you want to keep this table, remove the line `Schema::drop('notification_logs_sent_messages');` in the migration.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Okaufmann\LaravelNotificationLog\Models\SentNotificationLog;

return new class extends Migration {
    public function up()
    {
        Schema::table('notification_logs_sent_notifications', function (Blueprint $table) {
            $table->dateTime('sent_at')->nullable()->after('attempt');
            $table->longText('message')->nullable()->change();
        });

        DB::table('notification_logs_sent_messages')->get()->each(function ($messageLog) {
            $mailableId = $this->fromJson($messageLog->mailable)[1] ?? null;

            if (! $messageLog) {
                return;
            }

            $notification = SentNotificationLog::query()
                ->where('notification_id', $mailableId)
                ->first();

            if (! $notification) {
                return;
            }

            $data = [
                'to' => $this->fromJson($messageLog->to),
                'cc' => $this->fromJson($messageLog->cc),
                'bcc' => $this->fromJson($messageLog->bcc),
                'reply_to' =>  $this->fromJson($messageLog->reply_to),
                'from' => $this->fromJson($messageLog->sender),
                'subject' => $messageLog->subject,
                'attachments' => $this->fromJson($messageLog->attachments),
            ];

            SentNotificationLog::withoutTimestamps(function () use ($notification, $messageLog, $data) {
                $notification->update([
                    ...$notification->data ?? [],
                    'message' => $messageLog->body,
                    'sent_at' => $messageLog->sent_at,
                    'data' => $data,
                ]);
            });

            DB::table('notification_logs_sent_messages')
                ->where('id', $messageLog->id)
                ->delete();
        });

        Schema::drop('notification_logs_sent_messages');
    }

    protected function fromJson(?string $string): ?array
    {
        if (blank($string)) {
            return null;
        }

        $data =  json_decode($string, true, 512, JSON_THROW_ON_ERROR);

        if (blank($data)) {
            return null;
        }

        return $data;
    }
};















```
**Full Changelog**: https://github.com/okaufmann/laravel-notification-log/compare/3.0.0...4.0.0

## 3.0.0 - 2024-07-17

### What's Changed

- Use NotificationFailed event of Laravel and change response handling
- Add support for Twilio and Slack channels
- Handle channels that fire NotificationFailed events

**Full Changelog**: https://github.com/okaufmann/laravel-notification-log/compare/2.1.0...3.0.0

## 2.1.0 - 2024-07-17

### What's Changed

* Add ensure unique notification interface by @Sparclex in https://github.com/okaufmann/laravel-notification-log/pull/1
* Log skip unique notification  by @Sparclex in https://github.com/okaufmann/laravel-notification-log/pull/2

### New Contributors

* @Sparclex made their first contribution in https://github.com/okaufmann/laravel-notification-log/pull/1

**Full Changelog**: https://github.com/okaufmann/laravel-notification-log/compare/2.0.2...2.1.0

## 2.0.2 - 2024-07-16

**Full Changelog**: https://github.com/okaufmann/laravel-notification-log/compare/2.0.1...2.0.2

## 2.0.1 - 2024-07-16

**Full Changelog**: https://github.com/okaufmann/laravel-notification-log/compare/2.0.0...2.0.1

## 2.0.0 - 2024-07-16

**Full Changelog**: https://github.com/okaufmann/laravel-notification-log/compare/1.0.0...2.0.0

## 1.0.0 - 2024-07-05

**Full Changelog**: https://github.com/okaufmann/laravel-notification-log/commits/1.0.0
