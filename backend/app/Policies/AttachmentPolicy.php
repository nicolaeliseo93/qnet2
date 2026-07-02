<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD authorization for the Attachments resource.
 *
 * Maps every ability to the `attachments.{ability}` permission (registered by
 * `php artisan permissions:sync`). Authorization is enforced server-side on
 * every endpoint, regardless of what the frontend shows or hides.
 */
class AttachmentPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'attachments';
    }
}
