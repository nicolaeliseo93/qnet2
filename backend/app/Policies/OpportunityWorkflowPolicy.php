<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `opportunity-workflows` resource (spec 0047).
 * No special overrides: every ability maps to "opportunity-workflows.{ability}"
 * via BasePolicy, auto-discovered by Laravel from the OpportunityWorkflow
 * model.
 */
class OpportunityWorkflowPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'opportunity-workflows';
    }
}
