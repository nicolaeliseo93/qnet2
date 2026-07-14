<?php

declare(strict_types=1);

namespace App\DataObjects\Projects;

use App\Models\Project;
use Illuminate\Support\Collection;

/**
 * Correlated result of ProjectService::index() (spec 0026): the page of
 * projects plus the total count and the echoed paging window. Returned as a
 * DTO (not a loose array) so the controller can forward it straight to
 * paginatedResponse(), mirroring NotificationListResult.
 */
final readonly class ProjectIndexResult
{
    /**
     * @param  Collection<int, Project>  $items
     */
    public function __construct(
        public Collection $items,
        public int $total,
        public int $offset,
        public int $limit,
    ) {}
}
