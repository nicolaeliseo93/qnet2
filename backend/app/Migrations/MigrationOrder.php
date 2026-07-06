<?php

namespace App\Migrations;

/**
 * Recommended chronological order for running the migration sources (spec 0013).
 *
 * To import every record AND its relational links correctly, run the sources
 * phase by phase, in order. A source in a later phase references — via `old_id`
 * — records created by the earlier phases; running out of order leaves those
 * references as non-fatal "parent not yet migrated" warnings instead of resolved
 * links. Sources inside the same phase have no cross-dependency and may run in
 * any order.
 *
 * This is ordering guidance, not an enforced gate: the engine still imports
 * whatever single source is requested. It is deliberately distinct from the
 * declaration order in config/migrations.php (a registry map, not a plan).
 *
 * Single source of truth for the order — update PHASES here as sources are
 * added or their dependencies change.
 */
final class MigrationOrder
{
    /**
     * Ordered import phases. Each value is a MigrationSource::key() as
     * registered in config('migrations.definitions').
     *
     * @var array<int, array<int, string>>
     */
    public const PHASES = [
        // Phase 1 — independent anchor entities that users later link to.
        ['business-functions', 'companies', 'operational-sites'],

        // Phase 2 — users (reference the phase 1 entities via old_id).
        ['users'],

        // Phase 3 — junctions between phase 1 and phase 2 entities: the
        // user <-> business-function pivot needs both sides already migrated.
        ['user-business-functions'],
    ];

    /**
     * The ordered phases (each an ordered list of source keys).
     *
     * @return array<int, array<int, string>>
     */
    public static function phases(): array
    {
        return self::PHASES;
    }
}
