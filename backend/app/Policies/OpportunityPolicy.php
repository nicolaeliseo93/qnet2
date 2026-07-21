<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `opportunities` resource (spec 0040),
 * auto-discovered by Laravel from the Opportunity model. One addition beyond
 * BasePolicy: `viewDocuments` gates the opportunity documents section (the
 * reused polymorphic Attachment subsystem), mirroring how BasePolicy's own
 * `viewActivity` gates the activity-log section.
 */
class OpportunityPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'opportunities';
    }

    /**
     * Resource-level gate for the documents section on the opportunity
     * detail; the per-attachment boundary is enforced separately by
     * AttachmentPolicy on each attachment endpoint.
     */
    public function viewDocuments(User $user): bool
    {
        return $user->can($this->permission('viewDocuments'));
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return [...parent::abilities(), 'viewDocuments'];
    }
}
