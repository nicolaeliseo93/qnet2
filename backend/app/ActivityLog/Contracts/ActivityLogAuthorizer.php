<?php

declare(strict_types=1);

namespace App\ActivityLog\Contracts;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Read-gate of one `config/activity-log.php` resource.
 *
 * The default (PolicyActivityLogAuthorizer) authorizes through the ROOT
 * MODEL's own Policy, which is the right rule whenever the resource key and
 * the model's permission set coincide (`users` -> UserPolicy, `companies` ->
 * CompanyPolicy, ...). A module that operates on a model it does not own —
 * an OPERATIVE view with its OWN permission set — declares its own
 * implementation instead, so its activity surface is gated by ITS
 * permissions rather than the model's (spec 0049 D-7, amended).
 *
 * Mirrors App\Notes\Contracts\NotableEntity: this interface stays agnostic in
 * app/ActivityLog/, a concrete per-module implementation belongs to the host
 * module's OWN namespace and is referenced as a pure class-string in config
 * (config:cache-safe).
 */
interface ActivityLogAuthorizer
{
    /**
     * Assert $user may read $record's aggregated activity log.
     *
     * @throws AuthorizationException|HttpException when not allowed
     */
    public function authorize(User $user, Model $record): void;
}
