<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `tags` resource (spec 0019). No special
 * overrides: every ability maps to "tags.{ability}" via BasePolicy,
 * auto-discovered by Laravel from the Tag model.
 */
class TagPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'tags';
    }
}
