<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `referent-types` resource (spec 0016). No
 * special overrides: every ability maps to "referent-types.{ability}" via
 * BasePolicy, auto-discovered by Laravel from the ReferentType model.
 */
class ReferentTypePolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'referent-types';
    }
}
