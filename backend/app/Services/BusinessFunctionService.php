<?php

namespace App\Services;

use App\DataObjects\BusinessFunctions\CreateBusinessFunctionData;
use App\DataObjects\BusinessFunctions\UpdateBusinessFunctionData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\BusinessFunction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the `business-functions` resource (spec 0010): create /
 * update / delete, including the `type` -> is_business_unit/is_business_service
 * boolean remap and the `users` full-replace sync. The controller stays thin;
 * this Service is the single authority.
 *
 * `type` is mutually exclusive by construction: 'business_unit' sets
 * is_business_unit=true/is_business_service=false, 'business_service' the
 * inverse, and null (or absent on create) sets both false.
 */
class BusinessFunctionService
{
    private const string TYPE_BUSINESS_UNIT = 'business_unit';

    private const string TYPE_BUSINESS_SERVICE = 'business_service';

    /**
     * Relations eager-loaded on every returned model, so the Resource never
     * N+1s while hydrating manager/users avatars.
     *
     * @var array<int, string>
     */
    private const array HYDRATED_RELATIONS = ['manager.avatar', 'users.avatar'];

    public function create(User $actor, CreateBusinessFunctionData $data): BusinessFunction
    {
        return DB::transaction(function () use ($data): BusinessFunction {
            /** @var BusinessFunction $businessFunction */
            $businessFunction = BusinessFunction::create(array_merge(
                ['name' => $data->name, 'manager_id' => $data->managerId],
                $this->typeToBooleans($data->type),
            ));

            if ($data->hasUsers()) {
                $businessFunction->users()->sync($data->users);
            }

            return $businessFunction->fresh(self::HYDRATED_RELATIONS);
        });
    }

    public function update(User $actor, BusinessFunction $businessFunction, UpdateBusinessFunctionData $data): BusinessFunction
    {
        return DB::transaction(function () use ($businessFunction, $data): BusinessFunction {
            $attributes = $data->submittedAttributes();

            if ($data->hasType()) {
                $attributes = array_merge($attributes, $this->typeToBooleans($data->type));
            }

            // Unconditional save: fire the model's saved event even when no native
            // attribute changed, so the HasCustomFields write pipeline (spec 0021)
            // persists a custom-fields-only edit. A clean save runs no UPDATE query.
            $businessFunction->fill($attributes)->save();

            if ($data->hasUsers()) {
                $businessFunction->users()->sync($data->users);
            }

            return $businessFunction->fresh(self::HYDRATED_RELATIONS);
        });
    }

    public function delete(BusinessFunction $businessFunction): void
    {
        // The business_function_user pivot rows cascade on delete (migration
        // FK constraint), so no explicit detach is needed here.
        $businessFunction->delete();
    }

    /**
     * Minimal, searchable, paginated business-function list for the for-select
     * standard (spec 0015, ADR 0011), mirroring UserService::forSelect: search
     * by `name`, order by name/id, ids[] hydrated without inflating total.
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = BusinessFunction::query()->select(['id', 'name']);

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, BusinessFunction> $page */
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
     * Append the explicitly-requested `ids[]` (edit-mode hydration) that are not
     * already on the page, deduplicated. They bypass search and the same id/name
     * projection applies. Total is unaffected.
     *
     * @param  Collection<int, BusinessFunction>  $page
     * @return Collection<int, BusinessFunction>
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

        /** @var Collection<int, BusinessFunction> $hydrated */
        $hydrated = BusinessFunction::query()
            ->select(['id', 'name'])
            ->whereIn('id', $missingIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }

    /**
     * Map the client-facing `type` selector onto the two mutually-exclusive
     * boolean columns.
     *
     * @return array{is_business_unit: bool, is_business_service: bool}
     */
    private function typeToBooleans(?string $type): array
    {
        return [
            'is_business_unit' => $type === self::TYPE_BUSINESS_UNIT,
            'is_business_service' => $type === self::TYPE_BUSINESS_SERVICE,
        ];
    }
}
