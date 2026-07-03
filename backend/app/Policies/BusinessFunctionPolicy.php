<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `business-functions` resource (spec 0010).
 * No special overrides: every ability maps to "business-functions.{ability}"
 * via BasePolicy, auto-discovered by Laravel from the BusinessFunction model.
 */
class BusinessFunctionPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'business-functions';
    }
}
