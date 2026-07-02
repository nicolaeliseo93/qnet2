<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/**
 * Resolves and validates the polymorphic owner a Store request attaches its
 * entity to (mirrors the StoreAttachmentRequest pattern, shared across the
 * PersonalData module so the logic is not duplicated three times).
 *
 * The owner travels on the wire as a public, stable alias + id
 * (e.g. contactable_type=personal_data, contactable_id=5). The alias is the
 * security boundary: only aliases listed in the resource's config allowlist can
 * be targeted, so a request can never attach an entity to an arbitrary class.
 * The concrete owner is resolved here, at the HTTP boundary, so the controller
 * and service deal only with real model instances.
 */
trait ResolvesOwner
{
    /** Config key holding the alias => model-class allowlist. */
    abstract protected function ownerConfigKey(): string;

    /** Request field carrying the owner alias (e.g. "contactable_type"). */
    abstract protected function ownerTypeField(): string;

    /** Request field carrying the owner id (e.g. "contactable_id"). */
    abstract protected function ownerIdField(): string;

    /**
     * Validation rules for the owner fields. Kept `sometimes` (presence is
     * enforced in validateOwner, not here) so they never alter the domain-only
     * validation that subclasses' rules() are unit-tested against in isolation.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function ownerRules(): array
    {
        return [
            $this->ownerTypeField() => ['sometimes', 'string', Rule::in($this->ownerAliases())],
            $this->ownerIdField() => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * Enforce that a valid, existing owner was supplied. The owner is required:
     * every entity created through the API belongs to an owner. Call from
     * withValidator()'s after() hook.
     */
    protected function validateOwner(Validator $validator): void
    {
        $alias = $this->input($this->ownerTypeField());
        $id = $this->input($this->ownerIdField());

        if ($alias === null || $id === null) {
            $validator->errors()->add($this->ownerTypeField(), 'An owner is required.');

            return;
        }

        $modelClass = $this->allowedTypes()[$alias] ?? null;

        if ($modelClass === null) {
            return; // already rejected by the Rule::in on the alias field
        }

        if (! $modelClass::query()->whereKey($id)->exists()) {
            $validator->errors()->add($this->ownerIdField(), 'The selected owner does not exist.');
        }
    }

    /**
     * The resolved owner model instance. Call only after validation passes (it
     * assumes the alias is allowlisted and the id exists).
     */
    public function owner(): Model
    {
        $alias = (string) $this->input($this->ownerTypeField());
        /** @var class-string<Model> $modelClass */
        $modelClass = $this->allowedTypes()[$alias];

        /** @var Model $owner */
        $owner = $modelClass::query()->findOrFail($this->input($this->ownerIdField()));

        return $owner;
    }

    /**
     * Alias => model class allowlist for this resource.
     *
     * @return array<string, class-string<Model>>
     */
    private function allowedTypes(): array
    {
        /** @var array<string, class-string<Model>> $types */
        $types = (array) config($this->ownerConfigKey());

        return $types;
    }

    /**
     * @return array<int, string>
     */
    private function ownerAliases(): array
    {
        return array_keys($this->allowedTypes());
    }
}
