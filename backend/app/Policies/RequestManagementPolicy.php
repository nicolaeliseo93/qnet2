<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Abstracts\BasePolicy;

/**
 * Dedicated policy for the `request-management` resource (spec 0049,
 * decision D-2): the "Request Management" module is an operative view over
 * Opportunity records, but access is authorized through its OWN permission
 * set (`request-management.*`), never `opportunities.*`.
 *
 * Two additions beyond BasePolicy: `viewAll` lifts the manager-scoping guard
 * (spec 0049 D-3, `RequestManagementScope`) so the actor sees every
 * opportunity instead of only the ones where they are Account Manager, and
 * `viewDocuments` gates the documents surface (the reused polymorphic
 * Attachment subsystem) with this module's OWN permission, exactly as
 * OpportunityPolicy does for `opportunities.viewDocuments`.
 */
class RequestManagementPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'request-management';
    }

    /**
     * Resource-level gate lifting the manager-scoping guard (spec 0049 D-3):
     * with this ability the actor sees every opportunity in
     * request-management, not only the ones they manage.
     */
    public function viewAll(User $user): bool
    {
        return $user->can($this->permission('viewAll'));
    }

    /**
     * Resource-level gate for the documents surface of this module (row
     * action + dialog). The per-attachment boundary stays with
     * AttachmentPolicy (`attachments.*`) on each attachment endpoint.
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
        return [...parent::abilities(), 'viewAll', 'viewDocuments'];
    }
}
