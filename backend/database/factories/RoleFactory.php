<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\Permission\Guard;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'role_'.fake()->unique()->numerify('####').'_'.fake()->word(),
            // Same canonical guard as the permission catalogue and user role
            // assignment (see RoleService). Derived from the auth provider, not
            // the request's (mutable) default guard.
            'guard_name' => Guard::getDefaultName(User::class),
        ];
    }
}
