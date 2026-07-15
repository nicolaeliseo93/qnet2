<?php

namespace App\Http\Controllers\Table;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Table\BulkDeleteTableRequest;
use App\Http\Requests\Table\TableFilterStateRequest;
use App\Http\Requests\Table\TablePreferencesRequest;
use App\Http\Requests\Table\TableRowsRequest;
use App\Http\Requests\Table\TableValuesRequest;
use App\Http\Resources\TableRowResource;
use App\Models\User;
use App\Services\TableBulkDeleteService;
use App\Services\TableFilterStateService;
use App\Services\TablePreferenceService;
use App\Services\TableService;
use App\Tables\TableDefinition;
use App\Tables\TableRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Generic, domain-driven Table endpoints (AG Grid SSRM).
 *
 * One pair of endpoints serves every domain. Thin controller: no business
 * logic, no queries. Both endpoints resolve the TableDefinition for the
 * {domain} route segment (unknown → 404), enforce the definition's viewAny
 * server-side (deny → 403) and delegate to TableService.
 *
 * @see TableService
 * @see docs/api/0002-generic-tables.md
 */
class TableController extends BaseApiController
{
    public function __construct(
        private readonly TableRegistry $registry,
        private readonly TableService $service,
        private readonly TablePreferenceService $preferences,
        private readonly TableFilterStateService $filters,
        private readonly TableBulkDeleteService $bulkDelete,
    ) {}

    /**
     * GET /api/tables/{domain}/columns — resolved table schema for the actor,
     * with their saved column preferences (order/width/visibility) merged in.
     */
    public function columns(Request $request, string $domain): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown

            /** @var User $actor */
            $actor = $request->user();
            $this->authorizeViewAny($definition->authorizeViewAny($actor));

            return $this->ok($this->resolvedConfig($definition, $actor));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * POST /api/tables/{domain}/preferences — upsert the current user's column
     * layout for {domain}. Self-scoped (always auth user; the client never sends
     * a user_id). Returns the freshly merged config. See ADR-0004.
     */
    public function savePreferences(TablePreferencesRequest $request, string $domain): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown

            /** @var User $actor */
            $actor = $request->user();
            $this->authorizeViewAny($definition->authorizeViewAny($actor));

            $this->preferences->save($definition, $actor, $request->columnsState());

            return $this->ok($this->resolvedConfig($definition, $actor), 'Preferences saved');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * DELETE /api/tables/{domain}/preferences — reset the current user's layout
     * for {domain} to the PHP default (explicit user action; nothing else clears
     * preferences). See ADR-0004.
     */
    public function resetPreferences(Request $request, string $domain): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown

            /** @var User $actor */
            $actor = $request->user();
            $this->authorizeViewAny($definition->authorizeViewAny($actor));

            $this->preferences->reset($definition, $actor);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * POST /api/tables/{domain}/filters — upsert the current user's applied
     * filterModel for {domain} so filters survive a reload. Self-scoped (always
     * auth user). An empty model clears the saved state. Returns the merged config.
     */
    public function saveFilters(TableFilterStateRequest $request, string $domain): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown

            /** @var User $actor */
            $actor = $request->user();
            $this->authorizeViewAny($definition->authorizeViewAny($actor));

            $this->filters->save($definition, $actor, $request->filterModel(), $request->advancedFilters());

            return $this->ok($this->resolvedConfig($definition, $actor), 'Filters saved');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * DELETE /api/tables/{domain}/filters — reset the current user's saved filters
     * for {domain} (explicit user action; nothing else clears them).
     */
    public function resetFilters(Request $request, string $domain): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown

            /** @var User $actor */
            $actor = $request->user();
            $this->authorizeViewAny($definition->authorizeViewAny($actor));

            $this->filters->reset($definition, $actor);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * Resolve the definition's default config and merge the actor's preferences
     * (column layout) and saved filter state.
     *
     * @return array<string, mixed>
     */
    private function resolvedConfig(TableDefinition $definition, User $actor): array
    {
        $config = $this->preferences->applyTo(
            $definition->resolveConfig($actor),
            $definition,
            $actor,
        );

        return $this->filters->applyTo($config, $definition, $actor);
    }

    /**
     * POST /api/tables/{domain}/rows — SSRM page of rows + total (paginated).
     */
    public function rows(TableRowsRequest $request, string $domain): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown

            /** @var User $actor */
            $actor = $request->user();
            $this->authorizeViewAny($definition->authorizeViewAny($actor));

            $result = $this->service->rows($definition, $actor, $request->validated());

            return $this->paginatedResponse(
                items: TableRowResource::collection($result->items),
                total: $result->total,
                offset: $result->offset,
                limit: $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * POST /api/tables/{domain}/values — distinct values for a single column
     * (Excel-like set filter), scoped by the filters active on every OTHER
     * column (the target column never auto-restricts its own list).
     */
    public function values(TableValuesRequest $request, string $domain): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown

            /** @var User $actor */
            $actor = $request->user();
            $this->authorizeViewAny($definition->authorizeViewAny($actor));

            $payload = $request->payload();

            $result = $this->service->distinctValues(
                $definition,
                $actor,
                $payload['columnId'],
                $payload['search'],
                $payload['filterModel'],
                $payload['limit'],
            );

            return $this->ok([
                'values' => $result->values,
                'hasMore' => $result->hasMore,
            ]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * POST /api/tables/{domain}/bulk-delete — best-effort delete of many rows
     * by id. Baseline authorization mirrors rows/columns (the definition's
     * viewAny); the per-row 'delete' ability and domain delete guards (e.g.
     * the last-super-admin guard) are enforced PER ID by TableBulkDeleteService,
     * never fatal to the rest of the batch — see the DTO/response shape in
     * BulkDeleteResult.
     */
    public function bulkDelete(BulkDeleteTableRequest $request, string $domain): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown

            /** @var User $actor */
            $actor = $request->user();
            $this->authorizeViewAny($definition->authorizeViewAny($actor));

            $result = $this->bulkDelete->delete($definition, $actor, $request->ids());

            return $this->ok([
                'deleted' => $result->deleted,
                'failed' => $result->failed,
            ], 'Bulk delete completed');
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * Single enforcement point: deny → AuthorizationException → 403.
     *
     * @throws AuthorizationException
     */
    private function authorizeViewAny(bool $allowed): void
    {
        if (! $allowed) {
            throw new AuthorizationException;
        }
    }
}
