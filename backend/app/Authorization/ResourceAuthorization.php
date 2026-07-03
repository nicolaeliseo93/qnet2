<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * A resource's single source of truth for authorization metadata (spec 0004):
 * standard CRUD + export/import, per-field capabilities and per-action
 * availability, all computed against the current actor and an optional
 * resource instance (null in create-context).
 *
 * Register an implementation in config/authorization.php under the resource
 * key served by `GET /api/meta/{resource}` and consumed by
 * `EnforcesFieldPermissions` on every write.
 */
interface ResourceAuthorization
{
    /**
     * The `{resource}` key, e.g. "users" — matches the Policy convention
     * `{resource}.{ability}` (BasePolicy) and the config/authorization.php key.
     */
    public function resource(): string;

    /**
     * The static field catalogue (key + form type + optional group).
     *
     * @return array<int, FieldDefinition>
     */
    public function fields(): array;

    /**
     * The domain action catalogue (e.g. "delete", "export", "upload_avatar").
     *
     * @return array<int, string>
     */
    public function actions(): array;

    /**
     * Standard CRUD (+export/import) availability for $actor, contextual to
     * $model when given (null in create-context).
     *
     * @return array<string, bool>
     */
    public function resourcePermissions(User $actor, ?Model $model): array;

    /**
     * Per-field capabilities for $actor against $model (null in create-context).
     *
     * @return array<string, FieldPermission>
     */
    public function fieldPermissions(User $actor, ?Model $model): array;

    /**
     * Per-action availability for $actor against $model (null in create-context).
     *
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array;
}
