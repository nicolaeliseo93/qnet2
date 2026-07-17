<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Abstracts\BasePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * Authorization policy for the import module (spec 0034). The module has NO
 * permission set of its own: every surface (history, detail, wizard, export)
 * is gated by the lead module's single `leads.import` ability — the former
 * dedicated `import-runs.*` set was a duplicate and was removed (user decision
 * 2026-07-17). `leads` is the only registered import domain (config/imports.php),
 * so reusing its `import` ability is exact, not an approximation.
 *
 * `view`/`delete` additionally require OWNERSHIP (defense in depth): the
 * history table's baseQuery and ImportController's assertOwnedRun() already
 * scope every read/write to the actor's own runs, so this restates the same
 * invariant at the Policy layer for callers that gate through Gate/`can()`
 * directly (e.g. the generic bulk-delete engine). Super-admin bypasses
 * globally via AppServiceProvider's Gate::before.
 */
class ImportRunPolicy extends BasePolicy
{
    /**
     * The import module rides the lead module's permission namespace; it never
     * mints `import-runs.*` strings (see abilities() below).
     */
    protected function resource(): string
    {
        return 'leads';
    }

    public function viewAny(User $user): bool
    {
        return $user->can('leads.import');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can('leads.import') && $model->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('leads.import');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can('leads.import');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can('leads.import') && $model->user_id === $user->id;
    }

    public function export(User $user): bool
    {
        return $user->can('leads.import');
    }

    /**
     * The module contributes NO permissions to the catalog: it reuses
     * `leads.import` (minted by LeadPolicy). Empty here so SyncPermissions
     * generates no `import-runs.*` strings.
     *
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return [];
    }
}
