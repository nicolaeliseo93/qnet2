<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `operational-sites` resource (spec 0011). No
 * special overrides: every ability maps to "operational-sites.{ability}" via
 * BasePolicy, auto-discovered by Laravel from the OperationalSite model. A
 * site is not a user, so no self-delete guard applies.
 */
class OperationalSitePolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'operational-sites';
    }
}
