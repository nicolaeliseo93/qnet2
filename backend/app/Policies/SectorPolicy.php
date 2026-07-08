<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `sectors` resource (spec 0018). No
 * special overrides: every ability maps to "sectors.{ability}" via
 * BasePolicy, auto-discovered by Laravel from the Sector model.
 */
class SectorPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'sectors';
    }
}
