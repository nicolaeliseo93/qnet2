<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `status-groups` resource (spec 0039). No
 * special overrides: every ability maps to "status-groups.{ability}" via
 * BasePolicy, auto-discovered by Laravel from the StatusGroup model.
 */
class StatusGroupPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'status-groups';
    }
}
