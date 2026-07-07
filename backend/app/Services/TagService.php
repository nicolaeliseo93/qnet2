<?php

namespace App\Services;

use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\DataObjects\Tags\CreateTagData;
use App\DataObjects\Tags\UpdateTagData;
use App\Models\Tag;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the `tags` resource (spec 0019): a plain lookup entity
 * (id, name), mirroring SourceService, plus a restrictive delete — a tag
 * still attached to at least one record via the polymorphic `taggables`
 * pivot cannot be removed (it would silently orphan the association).
 */
class TagService
{
    public function create(CreateTagData $data): Tag
    {
        return Tag::create($data->attributes());
    }

    public function update(Tag $tag, UpdateTagData $data): Tag
    {
        $attributes = $data->submittedAttributes();

        if ($attributes !== []) {
            $tag->update($attributes);
        }

        return $tag->fresh();
    }

    /**
     * Restrictive delete: a tag attached to any record (taggables pivot)
     * cannot be removed, mirroring ProductCategoryService::delete.
     */
    public function delete(Tag $tag): void
    {
        if (DB::table('taggables')->where('tag_id', $tag->id)->exists()) {
            abort(409, 'This tag is attached to one or more records and cannot be deleted.');
        }

        $tag->delete();
    }

    /**
     * Minimal, searchable, paginated tag list for the for-select standard
     * (ADR 0011), mirroring SourceService::forSelect.
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = Tag::query()->select(['id', 'name']);

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, Tag> $page */
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
     * Append the explicitly-requested `ids[]` (edit-mode hydration) that are
     * not already on the page, deduplicated. They bypass search and the same
     * id/name projection applies. Total is unaffected.
     *
     * @param  Collection<int, Tag>  $page
     * @return Collection<int, Tag>
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

        /** @var Collection<int, Tag> $hydrated */
        $hydrated = Tag::query()
            ->select(['id', 'name'])
            ->whereIn('id', $missingIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }
}
