<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `project-statuses` resource (spec 0023). No
 * special overrides: every ability maps to "project-statuses.{ability}" via
 * BasePolicy, auto-discovered by Laravel from the ProjectStatus model.
 */
class ProjectStatusPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'project-statuses';
    }
}
