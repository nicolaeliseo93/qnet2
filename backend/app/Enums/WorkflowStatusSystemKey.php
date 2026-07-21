<?php

namespace App\Enums;

/**
 * The mandatory system rows every OpportunityWorkflowStatus set (a
 * workflow's own, or the global default set) carries (spec 0047, AC-004):
 * an initial 'open' row and a terminal 'closed' row, pinned and
 * non-deletable. Persisted as `opportunity_workflow_statuses.system_key`
 * (nullable — custom rows have none). Never mass-assignable (only the
 * service that creates/syncs a workflow's status set writes it).
 */
enum WorkflowStatusSystemKey: string
{
    case Open = 'open';
    case Closed = 'closed';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
