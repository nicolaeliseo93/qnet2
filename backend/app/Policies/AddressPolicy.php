<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD authorization for the Addresses resource.
 *
 * Maps every ability to the `addresses.{ability}` permission (registered by
 * `php artisan permissions:sync`). Authorization is enforced server-side on
 * every endpoint, regardless of what the frontend shows or hides.
 */
class AddressPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'addresses';
    }
}
