<?php

namespace App\Http\Controllers\Geo;

use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Geo\StateForSelectRequest;
use App\Http\Resources\StateForSelectResource;
use App\Models\State;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Throwable;

/**
 * GET /api/states/for-select — minimal, searchable, paginated state list
 * feeding entity-backed selects (spec 0023, ADR 0011 the for-select
 * standard), mirroring SourceForSelectController.
 *
 * Deliberate architectural exception, consistent with GeoController: states
 * are read-only reference data with no Policy and no per-resource
 * permission, so this controller queries Eloquent directly instead of going
 * through a Service — a StateService would be an empty pass-through for a
 * bounded reference lookup. The only gate is auth:sanctum (plus the route
 * group's throttle). Unlike GET /api/states (country-scoped cascade step),
 * search here is free (no country_id filter) since the consumer is a
 * standalone project/campaign form field, not the address cascade.
 */
class StateForSelectController extends BaseApiController
{
    public function __invoke(StateForSelectRequest $request): JsonResponse
    {
        try {
            $result = $this->forSelect($request->toData());

            return $this->paginatedResponse(
                StateForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    private function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = $this->baseQuery();

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, State> $page */
        $page = $base->orderBy('name')
            ->orderBy('id')
            ->offset($query->offset)
            ->limit($query->limit)
            ->get();

        $items = $this->appendHydratedIds($page, $query);

        return new ForSelectResult(
            items: $items,
            total: $total,
            offset: $query->offset,
            limit: $query->limit,
        );
    }

    /**
     * @return Builder<State>
     */
    private function baseQuery(): Builder
    {
        return State::query()
            ->select(['id', 'name', 'country_id'])
            ->with('country:id,name');
    }

    /**
     * Append the explicitly-requested `ids[]` (edit-mode hydration) that are
     * not already on the page, deduplicated. They bypass search and the same
     * id/name/country projection applies. Total is unaffected.
     *
     * @param  Collection<int, State>  $page
     * @return Collection<int, State>
     */
    private function appendHydratedIds(Collection $page, ForSelectQuery $query): Collection
    {
        if (! $query->hasIds()) {
            return $page;
        }

        $presentIds = $page->pluck('id')->all();
        $missingIds = array_values(array_diff($query->ids, $presentIds));

        if ($missingIds === []) {
            return $page;
        }

        /** @var Collection<int, State> $hydrated */
        $hydrated = $this->baseQuery()
            ->whereIn('id', $missingIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }
}
