<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Abstracts\BasePolicy;
use Illuminate\Database\Eloquent\Model;

/**
 * Standard CRUD policy for the `import-runs` module (spec 0034): the import
 * engine is shared by every domain (leads + 5 legacy), but the RUN itself is
 * now a first-class resource with its own permission set
 * (`import-runs.{viewAny,view,create,update,delete,export}`), independent of
 * each domain's own `{resource}.import` write ability that ImportController
 * still enforces on top (the "double gate": module + domain).
 *
 * `import` is dropped from the generated set (BasePolicy::abilities()
 * override): "importing an import-run" is meaningless — the module has no
 * such ability. `viewActivity` is dropped too: ImportRun carries no
 * activitylog (spec 0034 explicitly excludes it).
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
    protected function resource(): string
    {
        return 'import-runs';
    }

    public function view(User $user, Model $model): bool
    {
        return parent::view($user, $model) && $model->user_id === $user->id;
    }

    public function delete(User $user, Model $model): bool
    {
        return parent::delete($user, $model) && $model->user_id === $user->id;
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return ['viewAny', 'view', 'create', 'update', 'delete', 'export'];
    }
}
