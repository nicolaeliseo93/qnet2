<?php

namespace App\Notifications;

use App\DataObjects\Notifications\NotificationData;
use App\Enums\NotificationLevelEnum;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Domain-agnostic in-app notification persisted via the native `database`
 * channel. This is the reusable building block other modules use to notify a
 * user: pass a title/message and an optional severity level and action url.
 *
 * The stored payload follows the agreed convention
 * `{ title, message, level, action_url }` (see docs/api/0004-notifications.md),
 * where level ∈ info|success|warning|error.
 */
class GenericNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $title,
        private readonly string $message,
        private readonly string $level = 'info',
        private readonly ?string $actionUrl = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * The payload stored in the notifications `data` column. Built through the
     * NotificationData value object so the stored shape is the same guaranteed
     * contract the API resource emits (single source of truth).
     *
     * @return array{title: string|null, message: string|null, level: string, action_url: string|null}
     */
    public function toArray(object $notifiable): array
    {
        return (new NotificationData(
            title: $this->title,
            message: $this->message,
            level: NotificationLevelEnum::fromValue($this->level),
            actionUrl: $this->actionUrl,
        ))->toArray();
    }
}
