<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Serializes a ResourceAuthorization's three permission maps into the frozen
 * `permissions` wire shape (spec 0004): `{ resource, fields, actions }`.
 * Stateless — no dependencies, so no constructor is needed.
 */
final class ResourcePermissionsBuilder
{
    /**
     * @return array{resource: array<string, bool>, fields: array<string, array<string, bool>>, actions: array<string, bool>}
     */
    public function build(ResourceAuthorization $authorization, User $actor, ?Model $model): array
    {
        return [
            'resource' => $authorization->resourcePermissions($actor, $model),
            'fields' => array_map(
                static fn (FieldPermission $permission): array => $permission->toArray(),
                $authorization->fieldPermissions($actor, $model),
            ),
            'actions' => $authorization->actionPermissions($actor, $model),
        ];
    }
}
