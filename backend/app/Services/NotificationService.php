<?php

namespace App\Services;

use App\DataObjects\Notifications\NotificationListData;
use App\DataObjects\Notifications\NotificationListResult;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Business logic + DB scoping for the user notification system. Every query is
 * scoped to the given user through the `notifications` / `unreadNotifications`
 * relationships, so there is no cross-user access path (see ADR-0005).
 */
class NotificationService
{
    /**
     * Page of the user's notifications, newest first, optionally restricted to
     * unread. Counts and fetches in one scoped query each (no N+1).
     */
    public function list(User $user, NotificationListData $data): NotificationListResult
    {
        $query = $user->notifications();

        if ($data->onlyUnread) {
            $query->whereNull('read_at');
        }

        $total = $query->count();

        $items = $query
            ->orderByDesc('created_at')
            ->skip($data->offset)
            ->take($data->limit)
            ->get();

        return new NotificationListResult(
            items: $items,
            total: $total,
            offset: $data->offset,
            limit: $data->limit,
        );
    }

    /**
     * Number of the user's unread notifications (cheap, for frequent polling).
     */
    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    /**
     * Mark a single notification of the user as read and return it. Resolved
     * through the user relationship, so a foreign / unknown uuid throws
     * ModelNotFoundException → 404. Idempotent: re-marking a read notification
     * leaves it unchanged.
     */
    public function markAsRead(User $user, string $id): DatabaseNotification
    {
        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()->findOrFail($id);

        $notification->markAsRead();

        return $notification;
    }

    /**
     * Mark every unread notification of the user as read and return how many
     * were marked.
     */
    public function markAllAsRead(User $user): int
    {
        $marked = $user->unreadNotifications()->count();

        $user->unreadNotifications->markAsRead();

        return $marked;
    }
}
