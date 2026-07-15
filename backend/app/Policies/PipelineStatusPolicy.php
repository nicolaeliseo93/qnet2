<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `pipeline-statuses` resource (spec 0023). No
 * special overrides: every ability maps to "pipeline-statuses.{ability}" via
 * BasePolicy, auto-discovered by Laravel from the PipelineStatus model.
 */
class PipelineStatusPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'pipeline-statuses';
    }
}
