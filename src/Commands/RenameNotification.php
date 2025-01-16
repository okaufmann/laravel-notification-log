<?php

namespace Okaufmann\LaravelNotificationLog\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RenameNotification extends Command
{
    protected $signature = 'notification-log:rename {oldType : Old type of the Notification. Use slashes as namespace delimiter} {newType : New type of the Notification. Use slashes as namespace delimiter}';

    protected $description = 'If you ever move a Notification use this Command to fix the type in the database.';

    public function handle(): int
    {
        $oldType = Str::replace('/', '\\', $this->argument('oldType'));
        $newType = Str::replace('/', '\\', $this->argument('newType'));

        if (!class_exists($newType)) {
            $this->error("The class {$newType} does not exist.");
        }

        DB::transaction(function () use ($oldType, $newType) {
            $updatedLogs = 0;

            $this->getNotificationModelType()::withoutTimestamps(function () use ($oldType, $newType, &$updatedLogs) {
                $notificationLogs = $this->getNotificationModelType()::query()
                    ->where('notification_type', $oldType)
                    ->lazyById();

                foreach ($notificationLogs as $notificationLog) {
                    $notificationLog->update([
                        'notification_type' => $newType,
                        'notification_serialized' => Str::replace($oldType, $newType, $notificationLog->notification_serialized), // dump way to replace the namespaces does not work!
                    ]);

                    $updatedLogs++;
                }
            });

            $this->info("Updated {$updatedLogs} NotificationLogs.");
        });

        return Command::SUCCESS;
    }

    protected function getNotificationModelType(): string
    {
        return config('notification-log.model');
    }
}
