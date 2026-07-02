<?php

namespace App\DataObjects\Notifications;

/**
 * Validated query for listing a user's notifications (GET /api/notifications).
 *
 * Declared DTO (no "magic flying array") so the ListNotificationsRequest →
 * NotificationService contract is explicit — see standards/architecture.md →
 * Data Transfer Objects. `onlyUnread` collapses the `filter` query param
 * (all|unread) into a typed boolean the service reads directly.
 */
final readonly class NotificationListData
{
    public function __construct(
        public int $offset,
        public int $limit,
        public bool $onlyUnread,
    ) {}

    /**
     * Build from the validated ListNotificationsRequest payload. Defaults mirror
     * the frozen contract (offset 0, limit 15, filter all).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            offset: (int) ($data['offset'] ?? 0),
            limit: (int) ($data['limit'] ?? 15),
            onlyUnread: ($data['filter'] ?? 'all') === 'unread',
        );
    }
}
