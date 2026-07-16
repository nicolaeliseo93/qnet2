<?php

namespace App\Enums;

/**
 * The fixed 3-value classification every status configurator row carries
 * (pipeline_statuses.group/lead_statuses.group, spec 0039 pivot — replaces
 * the earlier "status groups" lookup entity): every row, system or custom,
 * is exactly one of Open/Pending/Closed. Never mass-assignable on a system
 * row (App\Services\Statuses\SystemStatusGuard rejects it outright).
 */
enum StatusGroup: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Closed = 'closed';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
