<?php

declare(strict_types=1);

namespace App\Http\Controllers\Meta;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\FieldDefinition;
use App\Authorization\ResourcePermissionsBuilder;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * GET /api/meta/{resource} — generic, registry-driven authorization metadata
 * (spec 0004). One controller serves every resource registered in
 * config/authorization.php, mirroring TableController's fail-closed pattern:
 * unknown {resource} → 404 (AuthorizationRegistry::resolve), missing
 * `{resource}.viewAny` → 403.
 *
 * Returns the create-context field catalogue (`data.fields`) plus the full
 * `permissions` block computed with model = null.
 */
class MetaController extends BaseApiController
{
    public function __construct(
        private readonly AuthorizationRegistry $registry,
        private readonly ResourcePermissionsBuilder $builder,
    ) {}

    public function show(Request $request, string $resource): JsonResponse
    {
        try {
            $authorization = $this->registry->resolve($resource); // 404 if unknown

            /** @var User $actor */
            $actor = $request->user();

            // Fail-closed: the standard `{resource}.viewAny` permission (the
            // same one BasePolicy::viewAny checks), mirroring
            // TableController::authorizeViewAny.
            $this->authorizeViewAny($actor->can("{$resource}.viewAny"));

            $fields = array_map(
                static fn (FieldDefinition $field): array => $field->toArray(),
                $authorization->fields(),
            );

            $permissions = $this->builder->build($authorization, $actor, null);

            return $this->okWithPermissions(['fields' => $fields], $permissions);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['resource' => $resource]);
        }
    }

    /**
     * @throws AuthorizationException
     */
    private function authorizeViewAny(bool $allowed): void
    {
        if (! $allowed) {
            throw new AuthorizationException;
        }
    }
}
