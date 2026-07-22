<?php

declare(strict_types=1);

namespace App\ActivityLog;

use App\ActivityLog\Contracts\ActivityLogAuthorizer;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Default read-gate of the aggregated activity log (spec 0034): the root
 * model's own Policy — `{resource}.viewActivity` on the class AND `view` on
 * the record, so an actor never reads the log of something they cannot see.
 *
 * Applies to every resource that does not declare its own `authorizer` in
 * config/activity-log.php.
 */
final class PolicyActivityLogAuthorizer implements ActivityLogAuthorizer
{
    public function authorize(User $user, Model $record): void
    {
        $gate = Gate::forUser($user);

        $gate->authorize('viewActivity', $record::class);
        $gate->authorize('view', $record);
    }
}
