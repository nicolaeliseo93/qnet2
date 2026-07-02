<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD authorization for the PersonalData resource.
 *
 * Maps every ability to the `personal_data.{ability}` permission (registered by
 * `php artisan permissions:sync`). Authorization is enforced server-side on
 * every endpoint, regardless of what the frontend shows or hides.
 */
class PersonalDataPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'personal_data';
    }
}
