<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `attributes` resource (spec 0017). No special
 * overrides: every ability maps to "attributes.{ability}" via BasePolicy,
 * auto-discovered by Laravel from the Attribute model.
 */
class AttributePolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'attributes';
    }
}
