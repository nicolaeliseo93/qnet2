<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `opportunity-statuses` resource (spec 0043).
 * No special overrides: every ability maps to "opportunity-statuses.{ability}"
 * via BasePolicy, auto-discovered by Laravel from the OpportunityStatus model.
 */
class OpportunityStatusPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'opportunity-statuses';
    }
}
