<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Abstracts\BasePolicy;
use Illuminate\Database\Eloquent\Model;

class UserPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'users';
    }

    /**
     * In addition to the standard `users.delete` permission, a user can never
     * delete their own account: this prevents an authenticated actor from
     * locking themselves (or the last admin) out via the table delete action.
     */
    public function delete(User $user, Model $model): bool
    {
        if ($user->is($model)) {
            return false;
        }

        return parent::delete($user, $model);
    }
}
