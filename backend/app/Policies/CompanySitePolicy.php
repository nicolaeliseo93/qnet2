<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `company-sites` resource (spec 0020). No
 * special overrides: every ability maps to "company-sites.{ability}" via
 * BasePolicy, auto-discovered from the CompanySite model. The `upload_logo`/
 * `delete_logo`/`set_default` actions are gated by the standard `update`
 * ability (see CompanySitesAuthorization::actionPermissions), not a
 * dedicated permission.
 */
class CompanySitePolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'company-sites';
    }
}
