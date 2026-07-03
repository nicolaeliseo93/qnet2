<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\FieldPermission;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;

/**
 * Write-path counterpart of the `permissions` metadata (spec 0004): resolves
 * the resource's ResourceAuthorization and rejects, with a 422 validator
 * error, any SUBMITTED key that maps to a non-editable field for the current
 * actor + target model. The same resolver that computes the metadata a
 * FormRequest's caller sees also guards the write, so a manipulated frontend
 * cannot bypass it.
 *
 * Concrete FormRequests declare which resource + model they target via
 * authorizationResource()/authorizationModel(), and call enforceFieldPermissions()
 * from their OWN withValidator() alongside their existing value-level rules —
 * this concern composes, it never replaces withValidator().
 */
trait EnforcesFieldPermissions
{
    /**
     * The `{resource}` key registered in config/authorization.php.
     */
    abstract protected function authorizationResource(): string;

    /**
     * The model instance being written, or null on create (store).
     */
    abstract protected function authorizationModel(): ?Model;

    /**
     * Reject every submitted key mapped to a non-editable field.
     *
     * Skipped when the actor lacks the resource's base write ability
     * (create/update): in that case the base CRUD authorization (enforced in
     * the controller via the Policy) is the relevant failure — a 403, not a
     * field-level 422 — so this never fires ahead of it.
     */
    protected function enforceFieldPermissions(Validator $validator): void
    {
        /** @var User $actor */
        $actor = $this->user();
        $resource = $this->authorizationResource();
        $model = $this->authorizationModel();

        $baseAbility = $model === null ? 'create' : 'update';

        if (! $actor->can("{$resource}.{$baseAbility}")) {
            return;
        }

        $authorization = app(AuthorizationRegistry::class)->resolve($resource);

        foreach ($authorization->fieldPermissions($actor, $model) as $field => $permission) {
            /** @var FieldPermission $permission */
            if (! $permission->editable && $this->has($field)) {
                $validator->errors()->add($field, 'field not editable');
            }
        }
    }
}
