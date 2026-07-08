<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `registries` resource (spec 0020). No special
 * overrides: every ability maps to "registries.{ability}" via BasePolicy,
 * auto-discovered by Laravel from the Registry model.
 */
class RegistryPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'registries';
    }
}
