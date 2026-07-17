<?php

namespace App\Tables;

use App\Enums\ImportStatus;
use App\Models\ImportRun;
use App\Models\User;
use App\Tables\LeadImports\LeadImportColumnCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Table definition for the `import-runs` domain (spec 0034 — renamed from
 * `lead-imports`, module extraction): the actor's own lead import runs,
 * served through the generic backend-driven table engine (SSRM) so the
 * history renders as the same AG Grid table as every other module.
 *
 * One deviation from a plain CRUD definition, backend-driven:
 *  - `baseQuery` scopes to the current actor's OWN runs for the `leads`
 *    resource — the generic engine has no built-in per-actor scoping, so it
 *    lives here, exactly reproducing the old endpoint's WHERE clause. The
 *    module now shows only lead runs; other domains may join later without
 *    touching this class's scoping contract.
 *
 * `authorizeViewAny` is not overridden: AbstractTableDefinition's default
 * (`Gate::allows('viewAny', ImportRun::class)`) resolves through
 * ImportRunPolicy, which now checks the lead module's `leads.import` ability
 * (the former dedicated `import-runs.*` set was removed 2026-07-17).
 */
class LeadImportsTableDefinition extends AbstractTableDefinition
{
    /** The `import_runs.resource` key this table is scoped to. */
    private const RESOURCE = 'leads';

    public function domain(): string
    {
        return 'import-runs';
    }

    /**
     * @return class-string<ImportRun>
     */
    public function modelClass(): string
    {
        return ImportRun::class;
    }

    /**
     * The actor's own runs for the `leads` resource only. `Auth::id()` is
     * always set here (authorizeViewAny runs first and requires auth); a null
     * id would simply match no rows (never fail-open).
     *
     * @return Builder<ImportRun>
     */
    public function baseQuery(): Builder
    {
        return ImportRun::query()
            ->where('resource', self::RESOURCE)
            ->where('user_id', Auth::id());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return LeadImportColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return LeadImportColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return LeadImportColumnCatalog::actions();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'created_at', 'direction' => 'desc'],
        ];
    }

    /**
     * @return array{limit: int}
     */
    public function defaultPagination(): array
    {
        return ['limit' => 25];
    }

    /**
     * Badge metadata for the `status` column, driven by ImportStatus (color +
     * source label). The frontend localizes the label from `enumKeyFor`.
     *
     * @return array<int, array<string, mixed>>|null
     */
    protected function badgesFor(string $columnId, User $actor): ?array
    {
        if ($columnId !== 'status') {
            return null;
        }

        return array_map(static fn ($meta): array => $meta->toArray(), ImportStatus::options());
    }

    /**
     * The `status` badge label is localized on the frontend from its own i18n
     * resources (`enums.import_status.<value>`).
     */
    protected function enumKeyFor(string $columnId, User $actor): ?string
    {
        return $columnId === 'status' ? 'import_status' : null;
    }

    /**
     * Map an ImportRun to the row payload. `actions` is attached by the generic
     * TableService via actionsFor().
     *
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var ImportRun $row */
        return [
            'id' => $row->id,
            'created_at' => $row->created_at,
            'original_filename' => $row->original_filename,
            'total_rows' => $row->total_rows,
            'imported_rows' => $row->imported_rows,
            'invalid_rows' => $row->invalid_rows,
            'status' => $row->status->value,
        ];
    }

    /**
     * `view` reopens the run in the wizard (available to any actor that reached
     * a row, since the table is `leads.import`-gated). `delete` is exposed only
     * when ImportRunPolicy allows it for this specific row (ownership) — the
     * same gate the generic bulk-delete engine re-checks server-side.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        $allowed = ['view'];

        if (Gate::forUser($actor)->allows('delete', $row)) {
            $allowed[] = 'delete';
        }

        return $allowed;
    }
}
