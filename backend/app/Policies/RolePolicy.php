<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the Roles resource.
 *
 * Maps each ability to the `roles.{ability}` permission (created by
 * `php artisan permissions:sync`, which discovers every BasePolicy subclass).
 *
 * The system `super-admin` role is protected from update/delete, but that guard
 * lives in RoleService — NOT here — on purpose: Gate::before (AppServiceProvider)
 * short-circuits every ability to `true` for a super-admin actor, so a policy
 * method would never run for them. The service layer is the only place the guard
 * is always enforced, regardless of who the actor is.
 */
class RolePolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'roles';
    }
}
