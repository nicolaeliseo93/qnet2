<?php

namespace App\Policies;

use App\Models\ImportRun;
use App\Models\User;

/**
 * Authorization for a single import run.
 *
 * An import run is not a CRUD resource with its own permission set: it belongs
 * to the module it was started against. So `view`/`delete` reuse that module's
 * `import` ability ({resource}.import, e.g. `leads.import`) AND require the
 * actor to OWN the run — the same two conditions the history table's
 * baseQuery/authorizeViewAny already enforce, restated here so the generic
 * bulk-delete engine (which Gate-checks `delete` per row) stays fail-closed.
 * Super-admin is handled globally by AppServiceProvider's Gate::before bypass.
 */
class ImportRunPolicy
{
    public function view(User $user, ImportRun $importRun): bool
    {
        return $this->ownsAndCanImport($user, $importRun);
    }

    public function delete(User $user, ImportRun $importRun): bool
    {
        return $this->ownsAndCanImport($user, $importRun);
    }

    private function ownsAndCanImport(User $user, ImportRun $importRun): bool
    {
        return $importRun->user_id === $user->id
            && $user->can("{$importRun->resource}.import");
    }
}
