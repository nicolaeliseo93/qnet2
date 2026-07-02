<?php

namespace App\Http\Requests\Concerns;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Validation\Rule;

/**
 * Shared role-id handling for the user FormRequests (Store/Update).
 *
 * The user form submits role IDS (the for-select standard, ADR 0011), but the
 * privilege guard and UserService operate on role NAMES (the privileged role is
 * identified by name). This concern owns the single boundary between the two:
 *  - validate the submitted ids against the actor's ASSIGNABLE roles (so a non
 *    super-admin can never assign `super-admin`, even by id);
 *  - resolve the ids back to names just before the DTO is built, leaving the DTO
 *    / service / guard contract (names) untouched.
 *
 * The assignable id => name map is fetched once per request.
 */
trait ResolvesAssignableRoles
{
    /** @var array<int, string>|null */
    private ?array $assignableRoleMapCache = null;

    /**
     * The actor's assignable roles as an id => name map (memoized per request).
     *
     * @return array<int, string>
     */
    protected function assignableRoleMap(): array
    {
        if ($this->assignableRoleMapCache === null) {
            /** @var User $actor */
            $actor = $this->user();
            $this->assignableRoleMapCache = app(UserService::class)->assignableRoleMap($actor);
        }

        return $this->assignableRoleMapCache;
    }

    /**
     * Validation rule for each `roles.*` entry: an integer id restricted to the
     * actor's assignable roles. Mirrors the user-side privilege guard at the
     * request layer (the service re-filters as the final authority).
     *
     * @return array<int, mixed>
     */
    protected function assignableRoleIdRule(): array
    {
        return ['integer', Rule::in(array_keys($this->assignableRoleMap()))];
    }

    /**
     * Replace the submitted role IDS in the validated payload with the role NAMES
     * the service/guard consume, so the DTO contract stays name-based. No-op when
     * no roles were submitted. Every id is guaranteed to be in the map because
     * {@see assignableRoleIdRule} already validated it.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function withResolvedRoleNames(array $validated): array
    {
        if (! isset($validated['roles']) || ! is_array($validated['roles'])) {
            return $validated;
        }

        $map = $this->assignableRoleMap();

        $validated['roles'] = array_values(array_map(
            static fn ($id): string => $map[(int) $id],
            $validated['roles'],
        ));

        return $validated;
    }
}
