<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `ea-sectors` resource (spec 0018). No
 * special overrides: every ability maps to "ea-sectors.{ability}" via
 * BasePolicy, auto-discovered by Laravel from the EaSector model.
 */
class EaSectorPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'ea-sectors';
    }
}
