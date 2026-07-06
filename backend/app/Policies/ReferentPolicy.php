<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `referents` resource (spec 0016). No special
 * overrides: every ability maps to "referents.{ability}" via BasePolicy,
 * auto-discovered by Laravel from the Referent model.
 */
class ReferentPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'referents';
    }
}
