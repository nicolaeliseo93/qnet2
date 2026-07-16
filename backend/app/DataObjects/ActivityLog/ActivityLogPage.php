<?php

namespace App\DataObjects\ActivityLog;

use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

/**
 * One paginated page of aggregated activity-log entries (spec 0034):
 * up to per_page Activity rows (causer eager-loaded), the FK label map
 * resolved for the whole page (spec 0034 FK-label extension — see
 * ForeignKeyLabelResolver), plus the opaque cursor for the next page, or
 * null when this is the last one.
 */
final readonly class ActivityLogPage
{
    /**
     * @param  Collection<int, Activity>  $items
     * @param  array<string, array<string, array<int, string>>>  $labels  [subject_type alias][field][id] => label
     */
    public function __construct(
        public Collection $items,
        public array $labels,
        public ?string $nextCursor,
    ) {}
}
