<?php

namespace App\Enums;

/**
 * The mandatory system rows every status configurator carries (spec 0039,
 * D-2): "Nuovo" starts the workflow. Pipeline statuses close on "Chiuso"
 * (`Closed`); lead statuses instead close on two distinct terminal rows —
 * "Chiuso con successo" (`Won`) and "Scartato" (`Discarded`, the renamed
 * former "Chiuso"). Persisted as `pipeline_statuses.system_key`/
 * `lead_statuses.system_key` (nullable — custom rows have none). Never
 * mass-assignable (App\Services\Statuses\SystemStatusGuard/
 * StatusOrderManager are the only writers).
 */
enum StatusSystemKey: string
{
    case New = 'new';
    case Closed = 'closed';
    case Won = 'won';
    case Discarded = 'discarded';
}
