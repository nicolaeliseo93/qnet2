<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `campaigns` resource (spec 0023). No special
 * overrides: every ability maps to "campaigns.{ability}" via BasePolicy,
 * auto-discovered by Laravel from the Campaign model. Campaigns carry no
 * delete-guard (unlike Projects/PipelineStatuses): deleting a campaign never
 * cascades a business-rule check.
 */
class CampaignPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'campaigns';
    }
}
