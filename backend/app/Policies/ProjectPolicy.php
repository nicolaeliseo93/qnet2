<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `projects` resource (spec 0023). No special
 * overrides: every ability maps to "projects.{ability}" via BasePolicy,
 * auto-discovered by Laravel from the Project model.
 */
class ProjectPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'projects';
    }
}
