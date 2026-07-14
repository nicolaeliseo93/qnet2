<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `lead-statuses` resource (spec 0029). No
 * special overrides: every ability maps to "lead-statuses.{ability}" via
 * BasePolicy, auto-discovered by Laravel from the LeadStatus model.
 */
class LeadStatusPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'lead-statuses';
    }
}
