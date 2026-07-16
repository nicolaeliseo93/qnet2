<?php

namespace App\Enums;

/**
 * The two mandatory system rows every status configurator carries (spec
 * 0039, D-2): "Nuovo" starts the workflow, "Chiuso" ends it. Persisted as
 * `pipeline_statuses.system_key`/`lead_statuses.system_key` (nullable —
 * custom rows have none). Never mass-assignable (App\Services\Statuses\
 * SystemStatusGuard/StatusOrderManager are the only writers).
 */
enum StatusSystemKey: string
{
    case New = 'new';
    case Closed = 'closed';
}
