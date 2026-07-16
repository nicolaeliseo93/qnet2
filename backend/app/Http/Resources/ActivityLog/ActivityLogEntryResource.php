<?php

namespace App\Http\Resources\ActivityLog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

/**
 * Wire shape for one aggregated activity-log entry (spec 0034). Wraps the
 * stock Spatie `Activity` model — never exposed raw. `subject_type` already
 * IS the module alias (Relation::enforceMorphMap), so `module` is a straight
 * passthrough.
 *
 * @mixin Activity
 */
class ActivityLogEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Activity $activity */
        $activity = $this->resource;

        return [
            'id' => $activity->id,
            'logged_at' => $activity->created_at?->toIso8601String(),
            'event' => $activity->event,
            'module' => $activity->subject_type,
            'subject_id' => $activity->subject_id,
            'causer' => [
                'id' => $activity->causer?->id,
                'name' => $activity->causer?->name,
            ],
            'changes' => $this->changes($activity),
        ];
    }

    /**
     * Derive changes[] from Spatie's `properties` payload: `created`/
     * `restored` carry only `attributes` (old_value=null, mirrors the
     * "nothing existed before" semantics — AC-008); `updated` pairs
     * `attributes` with `old` (AC-007); `deleted` carries only `attributes` as
     * the final state, so it is exposed as old_value with new_value=null (the
     * field no longer exists after the row is gone).
     *
     * @return array<int, array{field: string, old_value: mixed, new_value: mixed}>
     */
    private function changes(Activity $activity): array
    {
        $properties = $activity->properties ?? new Collection;
        $attributes = collect($properties->get('attributes', []));
        $old = collect($properties->get('old', []));

        return $attributes->keys()
            ->map(fn (string $field): array => match ($activity->event) {
                'deleted' => ['field' => $field, 'old_value' => $attributes->get($field), 'new_value' => null],
                'updated' => ['field' => $field, 'old_value' => $old->get($field), 'new_value' => $attributes->get($field)],
                default => ['field' => $field, 'old_value' => null, 'new_value' => $attributes->get($field)],
            })
            ->values()
            ->all();
    }
}
