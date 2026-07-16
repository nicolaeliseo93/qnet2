<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `opportunities` resource (spec 0040). No
 * special overrides: every ability maps to "opportunities.{ability}" via
 * BasePolicy, auto-discovered by Laravel from the Opportunity model.
 */
class OpportunityPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'opportunities';
    }
}
