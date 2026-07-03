<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `companies` resource (spec 0010). No special
 * overrides (no self-delete guard, unlike UserPolicy): every ability maps to
 * "companies.{ability}" via BasePolicy, auto-discovered from the Company model.
 */
class CompanyPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'companies';
    }
}
