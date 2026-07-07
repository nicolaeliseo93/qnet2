<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `sources` resource (spec 0018). No special
 * overrides: every ability maps to "sources.{ability}" via BasePolicy,
 * auto-discovered by Laravel from the Source model.
 */
class SourcePolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'sources';
    }
}
