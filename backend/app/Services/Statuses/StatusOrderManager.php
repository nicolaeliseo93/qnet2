<?php

namespace App\Services\Statuses;

use App\Enums\StatusSystemKey;
use App\Models\LeadStatus;
use App\Models\PipelineStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * `sort_order` placement/resequencing for both status configurators (spec
 * 0039, D-5): server-managed since the field left store/update. Generic on
 * the two sibling status models via a class-string (no speculative
 * interface — engineering.md §1.3): both share the exact same
 * name/system_key/sort_order shape.
 *
 * Sequence invariant, maintained by every method here: Nuovo=0,
 * custom=10,20,..., Chiuso=max(custom)+10.
 */
class StatusOrderManager
{
    private const int STEP = 10;

    /**
     * The `sort_order` a brand-new custom row should be created with: the
     * last custom's order + STEP (or STEP, i.e. Chiuso's current slot, when
     * there is no custom yet). Chiuso is bumped past it in the same
     * transaction so it always stays last.
     *
     * @param  class-string<PipelineStatus>|class-string<LeadStatus>  $modelClass
     */
    public function placeNew(string $modelClass): int
    {
        return DB::transaction(function () use ($modelClass): int {
            $lastCustomOrder = $modelClass::query()->whereNull('system_key')->max('sort_order');
            $newOrder = $lastCustomOrder !== null ? $lastCustomOrder + self::STEP : self::STEP;

            $modelClass::query()
                ->where('system_key', StatusSystemKey::Closed->value)
                ->update(['sort_order' => $newOrder + self::STEP]);

            return $newOrder;
        });
    }

    /**
     * Resequences every custom row to $orderedIds' order (10, 20, ...),
     * renormalizes Nuovo=0/Chiuso=max+10, and returns the fresh, complete,
     * ordered list. $orderedIds must be EXACTLY the set of custom ids (no
     * system row, no duplicate, none missing) — validated here so the
     * guard holds regardless of caller (defense in depth beyond the
     * FormRequest's own `distinct` rule).
     *
     * @param  class-string<PipelineStatus>|class-string<LeadStatus>  $modelClass
     * @param  array<int, int>  $orderedIds
     * @return Collection<int, PipelineStatus|LeadStatus>
     *
     * @throws HttpException 422
     */
    public function reorder(string $modelClass, array $orderedIds): Collection
    {
        return DB::transaction(function () use ($modelClass, $orderedIds): Collection {
            $this->assertValidReorderSet($modelClass, $orderedIds);

            $sortOrder = self::STEP;

            foreach ($orderedIds as $id) {
                $modelClass::query()->where('id', $id)->update(['sort_order' => $sortOrder]);
                $sortOrder += self::STEP;
            }

            $modelClass::query()->where('system_key', StatusSystemKey::New->value)->update(['sort_order' => 0]);
            $modelClass::query()->where('system_key', StatusSystemKey::Closed->value)->update(['sort_order' => $sortOrder]);

            return $modelClass::query()->orderBy('sort_order')->orderBy('name')->orderBy('id')->get();
        });
    }

    /**
     * $orderedIds must be exactly the custom (non-system) id set: no
     * duplicates, no system-row id, none missing.
     *
     * @param  class-string<PipelineStatus>|class-string<LeadStatus>  $modelClass
     * @param  array<int, int>  $orderedIds
     *
     * @throws HttpException 422
     */
    private function assertValidReorderSet(string $modelClass, array $orderedIds): void
    {
        if (count($orderedIds) !== count(array_unique($orderedIds))) {
            abort(422, 'ordered_ids contains duplicate ids.');
        }

        $customIds = $modelClass::query()->whereNull('system_key')->pluck('id')->all();

        if (array_diff($orderedIds, $customIds) !== [] || array_diff($customIds, $orderedIds) !== []) {
            abort(422, 'ordered_ids must contain exactly the custom statuses (no system status, none missing).');
        }
    }
}
