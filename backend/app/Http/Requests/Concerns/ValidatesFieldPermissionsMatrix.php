<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Authorization\AuthorizationRegistry;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Shared validation for the `field_permissions` matrix submitted from the
 * Role form (spec 0006). Each entry's `resource` must be a registered
 * authorization resource and its `field` must exist in THAT resource's field
 * catalogue — a cross-field check a static `Rule::in()` array cannot express
 * on its own, so it runs in the request's `withValidator()` `after()` hook,
 * alongside the base `field_permissions.*` array/boolean rules.
 */
trait ValidatesFieldPermissionsMatrix
{
    /**
     * Base shape rules: array of entries, each with a resource/field (typed,
     * cross-referenced against the registry in validateFieldPermissionsMatrix())
     * and three optional boolean flags. Absent key = leave untouched (handled
     * by CreateRoleData/UpdateRoleData); `[]` = clear the role's matrix.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function fieldPermissionsRules(): array
    {
        return [
            'field_permissions' => ['sometimes', 'array'],
            'field_permissions.*.resource' => ['required', 'string'],
            'field_permissions.*.field' => ['required', 'string'],
            'field_permissions.*.visible' => ['sometimes', 'boolean'],
            'field_permissions.*.editable' => ['sometimes', 'boolean'],
            'field_permissions.*.required' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Reject any entry whose `resource` is not registered, or whose `field`
     * does not exist in THAT resource's field catalogue — read from the
     * AuthorizationRegistry, never a hardcoded list, so a newly registered
     * resource is covered automatically.
     */
    protected function validateFieldPermissionsMatrix(Validator $validator): void
    {
        /** @var array<int, mixed> $entries */
        $entries = (array) $this->input('field_permissions', []);
        $registry = app(AuthorizationRegistry::class);

        foreach ($entries as $index => $entry) {
            if (! is_array($entry) || ! is_string($entry['resource'] ?? null) || ! is_string($entry['field'] ?? null)) {
                continue; // already flagged by the base required/string rules
            }

            $this->validateEntry($validator, $registry, (int) $index, $entry['resource'], $entry['field']);
        }
    }

    private function validateEntry(Validator $validator, AuthorizationRegistry $registry, int $index, string $resource, string $field): void
    {
        try {
            $fields = $registry->resolve($resource)->fields();
        } catch (ModelNotFoundException) {
            $validator->errors()->add("field_permissions.{$index}.resource", 'unknown resource');

            return;
        }

        $fieldKeys = array_map(static fn ($definition): string => $definition->key, $fields);

        if (! in_array($field, $fieldKeys, true)) {
            $validator->errors()->add("field_permissions.{$index}.field", 'unknown field for this resource');
        }
    }
}
