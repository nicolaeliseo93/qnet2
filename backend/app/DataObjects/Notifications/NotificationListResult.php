<?php

namespace App\DataObjects\Notifications;

use Illuminate\Support\Collection;

/**
 * Correlated result of NotificationService::list(): the page of notifications
 * plus the total count and the echoed paging window. Returned as a DTO (not a
 * loose array) so the controller can forward it straight to paginatedResponse()
 * — see standards/architecture.md → Data Transfer Objects.
 */
final readonly class NotificationListResult
{
    /**
     * @param  Collection<int, \Illuminate\Notifications\DatabaseNotification>  $items
     */
    public function __construct(
        public Collection $items,
        public int $total,
        public int $offset,
        public int $limit,
    ) {}
}
