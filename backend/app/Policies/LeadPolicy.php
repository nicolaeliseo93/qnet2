<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `leads` resource (spec 0024). No special
 * overrides: every ability maps to "leads.{ability}" via BasePolicy,
 * auto-discovered by Laravel from the Lead model.
 */
class LeadPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'leads';
    }
}
